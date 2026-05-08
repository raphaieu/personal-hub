<?php

namespace App\Livewire\AnalysisProfiles;

use App\Models\AnalysisProfile;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class HubPage extends Component
{
    public ?int $editingId = null;

    public string $formSlug = '';

    public string $formName = '';

    public string $formDescription = '';

    public string $formChannel = '';

    public string $formAnalysisType = 'classification';

    public string $formSystemPrompt = '';

    public string $formScoreThreshold = '';

    public bool $formIsActive = true;

    public string $formOutputSchema = '';

    public string $formAllowedCategories = '';

    public string $formSettings = '';

    public function mount(): void
    {
        $this->resetFormDefaults();
    }

    public function startCreate(): void
    {
        $this->editingId = null;
        $this->resetFormDefaults();
        $this->resetValidation();
    }

    public function startEdit(int $id): void
    {
        $profile = AnalysisProfile::query()->findOrFail($id);

        $this->editingId = $profile->id;
        $this->formSlug = $profile->slug;
        $this->formName = $profile->name;
        $this->formDescription = (string) ($profile->description ?? '');
        $this->formChannel = (string) ($profile->channel ?? '');
        $this->formAnalysisType = (string) ($profile->analysis_type ?? 'classification');
        $this->formSystemPrompt = $profile->system_prompt;
        $this->formScoreThreshold = is_numeric($profile->score_threshold)
            ? (string) ((float) $profile->score_threshold)
            : '';
        $this->formIsActive = (bool) $profile->is_active;
        $this->formOutputSchema = $this->prettyJson($profile->output_schema);
        $this->formAllowedCategories = $this->prettyJson($profile->allowed_categories);
        $this->formSettings = $this->prettyJson($profile->settings);
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetFormDefaults();
        $this->resetValidation();
    }

    public function saveProfile(): void
    {
        $editing = $this->editingId !== null
            ? AnalysisProfile::query()->findOrFail($this->editingId)
            : null;

        $this->validate($this->rules($editing));

        if ($editing?->isDefaultThreadsProfile()) {
            if ($this->formSlug !== AnalysisProfile::THREADS_OPPORTUNITIES_SLUG) {
                $this->addError('formSlug', 'O slug do profile padrão não pode ser alterado.');

                return;
            }

            if (($this->formChannel !== 'threads') && ($this->formChannel !== '')) {
                $this->addError('formChannel', 'O channel do profile padrão deve permanecer threads.');

                return;
            }

            if (! $this->formIsActive) {
                $this->addError('formIsActive', 'O profile padrão não pode ser desativado nesta etapa.');

                return;
            }
        }

        $outputSchema = $this->decodeJsonField($this->formOutputSchema, 'formOutputSchema');
        $allowedCategories = $this->decodeJsonField($this->formAllowedCategories, 'formAllowedCategories');
        $settings = $this->decodeJsonField($this->formSettings, 'formSettings');

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $payload = [
            'slug' => trim($this->formSlug),
            'name' => trim($this->formName),
            'description' => $this->nullableString($this->formDescription),
            'channel' => $this->nullableString($this->formChannel),
            'analysis_type' => trim($this->formAnalysisType),
            'system_prompt' => trim($this->formSystemPrompt),
            'score_threshold' => $this->nullableFloat($this->formScoreThreshold),
            'is_active' => $this->formIsActive,
            'output_schema' => $outputSchema,
            'allowed_categories' => $allowedCategories,
            'settings' => $settings,
        ];

        if ($editing !== null) {
            $editing->forceFill($payload)->save();
            session()->flash('analysis_profiles_hub_notice', 'Profile atualizado.');
        } else {
            AnalysisProfile::query()->create($payload);
            session()->flash('analysis_profiles_hub_notice', 'Profile criado.');
        }

        $this->editingId = null;
        $this->resetFormDefaults();
    }

    public function toggleActive(int $id): void
    {
        $profile = AnalysisProfile::query()->findOrFail($id);

        if ($profile->isDefaultThreadsProfile() && $profile->is_active) {
            session()->flash(
                'analysis_profiles_hub_notice',
                'O profile padrão threads-opportunities não pode ser desativado nesta etapa.'
            );

            return;
        }

        $profile->forceFill(['is_active' => ! $profile->is_active])->save();

        session()->flash('analysis_profiles_hub_notice', 'Status do profile atualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?AnalysisProfile $editing): array
    {
        return [
            'formSlug' => [
                'required',
                'string',
                'min:3',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('analysis_profiles', 'slug')->ignore($editing?->id),
            ],
            'formName' => ['required', 'string', 'min:3', 'max:120'],
            'formDescription' => ['nullable', 'string', 'max:1000'],
            'formChannel' => ['nullable', Rule::in(['', 'threads', 'whatsapp'])],
            'formAnalysisType' => ['required', 'string', 'max:80'],
            'formSystemPrompt' => ['required', 'string', 'min:8'],
            'formScoreThreshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'formIsActive' => ['boolean'],
            'formOutputSchema' => ['nullable', 'string'],
            'formAllowedCategories' => ['nullable', 'string'],
            'formSettings' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    private function decodeJsonField(string $value, string $field): array|null
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            $this->addError($field, 'JSON inválido para este campo.');

            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $value
     */
    private function prettyJson(array|null $value): string
    {
        if (! is_array($value) || $value === []) {
            return '';
        }

        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
    }

    private function nullableString(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableFloat(string $value): ?float
    {
        $trimmed = trim($value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return (float) $trimmed;
    }

    private function resetFormDefaults(): void
    {
        $this->formSlug = '';
        $this->formName = '';
        $this->formDescription = '';
        $this->formChannel = '';
        $this->formAnalysisType = 'classification';
        $this->formSystemPrompt = '';
        $this->formScoreThreshold = '';
        $this->formIsActive = true;
        $this->formOutputSchema = '';
        $this->formAllowedCategories = '';
        $this->formSettings = '';
    }

    public function render()
    {
        $profiles = AnalysisProfile::query()
            ->withCount(['threadsSources', 'monitoredSources', 'threadsCategories'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('livewire.analysis-profiles.hub-page', [
            'profiles' => $profiles,
        ])->layout('layouts.app');
    }
}
