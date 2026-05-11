<?php


namespace App\Livewire\Events;

use App\Enums\Events\EventStatus;
use App\Models\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class HubPage extends Component
{
    public ?string $editingId = null;

    public string $formTitle = '';

    public string $formSlug = '';

    public string $formStartsAt = '';

    public string $formEndsAt = '';

    public string $formTimezone = 'America/Sao_Paulo';

    public string $formStatus = 'draft';

    public ?int $formCapacity = null;

    public bool $formRequiresRef = false;

    public bool $formRequiresTurnstile = true;

    public bool $formRegistrationOpen = true;

    public string $formClosedMessage = '';

    public string $formInviteTemplateKey = '';

    public function startCreate(): void
    {
        $this->editingId = null;
        $this->resetFormDefaults();
        $this->resetValidation();
    }

    public function startEdit(string $eventId): void
    {
        $event = Event::query()->whereKey($eventId)->where('owner_id', auth()->id())->firstOrFail();
        $this->editingId = $event->id;
        $this->formTitle = $event->title;
        $this->formSlug = $event->slug;
        $this->formStartsAt = $event->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->formEndsAt = $event->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->formTimezone = $event->timezone ?? 'America/Sao_Paulo';
        $this->formStatus = $event->status->value;
        $this->formCapacity = $event->capacity;
        $this->formRequiresRef = $event->requires_ref;
        $this->formRequiresTurnstile = $event->requires_turnstile;
        $this->formRegistrationOpen = $event->registration_open;
        $this->formClosedMessage = (string) ($event->closed_message ?? '');
        $this->formInviteTemplateKey = (string) ($event->invite_template_key ?? '');
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->startCreate();
    }

    public function updatedFormTitle(string $value): void
    {
        if ($this->editingId === null) {
            $this->formSlug = Str::slug($value);
        }
    }

    public function saveEvent(): void
    {
        $this->validate($this->rules());

        $payload = [
            'title' => $this->formTitle,
            'slug' => $this->formSlug,
            'timezone' => $this->formTimezone,
            'status' => EventStatus::from($this->formStatus),
            'capacity' => $this->formCapacity,
            'requires_ref' => $this->formRequiresRef,
            'requires_turnstile' => $this->formRequiresTurnstile,
            'registration_open' => $this->formRegistrationOpen,
            'closed_message' => $this->formClosedMessage !== '' ? $this->formClosedMessage : null,
            'invite_template_key' => $this->formInviteTemplateKey !== '' ? $this->formInviteTemplateKey : null,
            'starts_at' => $this->formStartsAt !== '' ? $this->formStartsAt : null,
            'ends_at' => $this->formEndsAt !== '' ? $this->formEndsAt : null,
        ];

        if ($this->editingId !== null) {
            Event::query()
                ->whereKey($this->editingId)
                ->where('owner_id', auth()->id())
                ->firstOrFail()
                ->update($payload);
            session()->flash('events_hub_notice', 'Evento atualizado.');
        } else {
            $payload['owner_id'] = auth()->id();
            Event::query()->create($payload);
            session()->flash('events_hub_notice', 'Evento criado.');
        }

        $this->startCreate();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'formTitle' => ['required', 'string', 'max:255'],
            'formSlug' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('events', 'slug')->ignore($this->editingId),
            ],
            'formStartsAt' => ['nullable', 'date'],
            'formEndsAt' => ['nullable', 'date'],
            'formTimezone' => ['required', 'string', 'max:64'],
            'formStatus' => ['required', Rule::enum(EventStatus::class)],
            'formCapacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'formRequiresRef' => ['boolean'],
            'formRequiresTurnstile' => ['boolean'],
            'formRegistrationOpen' => ['boolean'],
            'formClosedMessage' => ['nullable', 'string', 'max:2000'],
            'formInviteTemplateKey' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
        ];
    }

    private function resetFormDefaults(): void
    {
        $this->formTitle = '';
        $this->formSlug = '';
        $this->formStartsAt = '';
        $this->formEndsAt = '';
        $this->formTimezone = 'America/Sao_Paulo';
        $this->formStatus = 'draft';
        $this->formCapacity = null;
        $this->formRequiresRef = false;
        $this->formRequiresTurnstile = true;
        $this->formRegistrationOpen = true;
        $this->formClosedMessage = '';
        $this->formInviteTemplateKey = '';
    }

    public function render()
    {
        $events = Event::query()
            ->where('owner_id', auth()->id())
            ->orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.events.hub-page', [
            'events' => $events,
        ]);
    }
}
