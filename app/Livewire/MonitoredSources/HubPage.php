<?php

namespace App\Livewire\MonitoredSources;

use App\Models\AnalysisProfile;
use App\Models\MessageLog;
use App\Models\MonitoredSource;
use App\Services\Analysis\MessageLogAnalysisReprocessingService;
use DomainException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class HubPage extends Component
{
    public ?int $editingId = null;

    public string $formKind = 'group';

    public string $formIdentifier = '';

    public string $formLabel = '';

    public bool $formIsActive = true;

    public string $formProfileId = '';

    public string $formNotes = '';

    /** @var array<int, string> */
    public array $sourceProfileForms = [];

    public function mount(): void
    {
        $this->resetFormDefaults();
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        $profiles = AnalysisProfile::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('channel', 'whatsapp')
                    ->orWhereNull('channel');
            })
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        $sources = MonitoredSource::query()
            ->with('analysisProfile:id,slug,name')
            ->addSelect([
                'latest_ai_pipeline_status' => MessageLog::query()
                    ->select('ai_pipeline_status')
                    ->whereColumn('monitored_source_id', 'monitored_sources.id')
                    ->latest('id')
                    ->limit(1),
                'latest_message_at' => MessageLog::query()
                    ->select('created_at')
                    ->whereColumn('monitored_source_id', 'monitored_sources.id')
                    ->latest('id')
                    ->limit(1),
            ])
            ->withCount([
                'messageLogs',
                'messageLogs as classified_count' => fn ($query) => $query->where('ai_pipeline_status', 'classified'),
                'messageLogs as pending_count' => fn ($query) => $query->whereIn('ai_pipeline_status', ['pending_media_processing', 'pending_text_extraction']),
                'messageLogs as skipped_count' => fn ($query) => $query->where('ai_pipeline_status', 'skipped_no_profile'),
            ])
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'kind',
                'identifier',
                'label',
                'notes',
                'is_active',
                'analysis_profile_id',
                'updated_at',
            ]);

        foreach ($sources as $source) {
            $sourceId = (int) $source->id;
            if (! array_key_exists($sourceId, $this->sourceProfileForms)) {
                $this->sourceProfileForms[$sourceId] = $source->analysis_profile_id !== null
                    ? (string) $source->analysis_profile_id
                    : '';
            }
        }

        $recentLogs = MessageLog::query()
            ->with([
                'monitoredSource:id,label,kind,is_active,analysis_profile_id',
                'monitoredSource.analysisProfile:id,name,slug,channel,is_active',
            ])
            ->latest('id')
            ->limit(25)
            ->get([
                'id',
                'monitored_source_id',
                'message_type',
                'body',
                'ai_pipeline_status',
                'is_processed',
                'created_at',
            ]);

        return [
            'profiles' => $profiles,
            'sources' => $sources,
            'recentLogs' => $recentLogs,
        ];
    }

    public function saveSourceProfile(int $sourceId): void
    {
        $source = MonitoredSource::query()->findOrFail($sourceId);
        $profileId = $this->resolvedProfileId($this->sourceProfileForms[$sourceId] ?? '', 'whatsapp');

        $source->forceFill([
            'analysis_profile_id' => $profileId,
        ])->save();

        $this->sourceProfileForms[$sourceId] = $profileId !== null ? (string) $profileId : '';

        session()->flash('monitored_sources_hub_notice', 'Profile da fonte monitorada atualizado.');
    }

    public function startCreate(): void
    {
        $this->editingId = null;
        $this->resetFormDefaults();
        $this->resetValidation();
    }

    public function startEdit(int $sourceId): void
    {
        $source = MonitoredSource::query()->findOrFail($sourceId);

        $this->editingId = $source->id;
        $this->formKind = $source->kind;
        $this->formIdentifier = $source->identifier;
        $this->formLabel = $source->label;
        $this->formIsActive = (bool) $source->is_active;
        $this->formProfileId = $source->analysis_profile_id !== null ? (string) $source->analysis_profile_id : '';
        $this->formNotes = (string) ($source->notes ?? '');
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetFormDefaults();
        $this->resetValidation();
    }

    public function saveSource(): void
    {
        $editing = $this->editingId !== null
            ? MonitoredSource::query()->findOrFail($this->editingId)
            : null;

        $this->validate([
            'formKind' => ['required', Rule::in(['self', 'contact', 'group'])],
            'formIdentifier' => [
                'required',
                'string',
                'max:255',
                Rule::unique('monitored_sources', 'identifier')->ignore($editing?->id),
            ],
            'formLabel' => ['required', 'string', 'min:3', 'max:120'],
            'formIsActive' => ['boolean'],
            'formProfileId' => ['nullable', 'string'],
            'formNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $profileId = $this->resolvedProfileId($this->formProfileId, 'whatsapp');

        if ($this->formProfileId !== '' && $profileId === null) {
            $this->addError('formProfileId', 'Selecione um profile ativo compatível com WhatsApp.');

            return;
        }

        $payload = [
            'kind' => $this->formKind,
            'identifier' => trim($this->formIdentifier),
            'label' => trim($this->formLabel),
            'is_active' => $this->formIsActive,
            'analysis_profile_id' => $profileId,
            'notes' => $this->nullableString($this->formNotes),
        ];

        if ($editing !== null) {
            $editing->forceFill($payload)->save();
            session()->flash('monitored_sources_hub_notice', 'Fonte monitorada atualizada.');
        } else {
            MonitoredSource::query()->create($payload);
            session()->flash('monitored_sources_hub_notice', 'Fonte monitorada criada.');
        }

        $this->editingId = null;
        $this->resetFormDefaults();
    }

    public function toggleSource(int $sourceId): void
    {
        $source = MonitoredSource::query()->findOrFail($sourceId);

        $source->forceFill([
            'is_active' => ! $source->is_active,
        ])->save();

        session()->flash('monitored_sources_hub_notice', 'Status da fonte monitorada atualizado.');
    }

    public function reprocessMessageLog(int $messageLogId, MessageLogAnalysisReprocessingService $service): void
    {
        $message = MessageLog::query()->findOrFail($messageLogId);
        Gate::authorize('reprocessAnalysis', $message);

        try {
            $profile = $service->enqueue($message);
        } catch (DomainException $exception) {
            session()->flash(
                'monitored_sources_hub_notice',
                $exception->getMessage()
            );

            return;
        }

        session()->flash(
            'monitored_sources_hub_notice',
            "Reprocessamento enfileirado com {$profile->name} ({$profile->slug}) na fila ai."
        );
    }

    private function resolvedProfileId(mixed $profileValue, string $channel): ?int
    {
        $profileId = null;

        if (is_string($profileValue) && ctype_digit($profileValue)) {
            $profileId = (int) $profileValue;
        } elseif (is_int($profileValue) && $profileValue > 0) {
            $profileId = $profileValue;
        }

        if ($profileId === null) {
            return null;
        }

        $exists = AnalysisProfile::query()
            ->whereKey($profileId)
            ->where('is_active', true)
            ->where(function ($query) use ($channel): void {
                $query->where('channel', $channel)
                    ->orWhereNull('channel');
            })
            ->exists();

        return $exists ? $profileId : null;
    }

    private function nullableString(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resetFormDefaults(): void
    {
        $this->formKind = 'group';
        $this->formIdentifier = '';
        $this->formLabel = '';
        $this->formIsActive = true;
        $this->formProfileId = '';
        $this->formNotes = '';
    }

    public function render()
    {
        return view('livewire.monitored-sources.hub-page', $this->viewData())
            ->layout('layouts.app');
    }
}
