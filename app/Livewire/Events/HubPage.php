<?php

namespace App\Livewire\Events;

use App\Enums\Events\EventStatus;
use App\Models\Event;
use App\Models\MercadoPagoAccount;
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

    public bool $formRequiresPhoto = true;

    public bool $formRegistrationOpen = true;

    public bool $formSkipEmailConfirmation = false;

    public string $formClosedMessage = '';

    public string $formInviteTemplateKey = '';

    public bool $formRequiresPayment = false;

    public string $formTicketAmount = '';

    public ?string $formMercadoPagoAccountId = null;

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
        $this->formRequiresPhoto = $event->requires_photo;
        $this->formRegistrationOpen = $event->registration_open;
        $this->formSkipEmailConfirmation = (bool) $event->skip_email_confirmation;
        $this->formClosedMessage = (string) ($event->closed_message ?? '');
        $this->formInviteTemplateKey = (string) ($event->invite_template_key ?? '');
        $this->formRequiresPayment = (bool) $event->requires_payment;
        $this->formTicketAmount = $event->ticket_amount_cents !== null
            ? number_format($event->ticket_amount_cents / 100, 2, '.', '')
            : '';
        $this->formMercadoPagoAccountId = $event->mercado_pago_account_id;
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

    public function updatedFormRequiresPayment(bool $value): void
    {
        if ($value) {
            $this->formSkipEmailConfirmation = false;
        }
    }

    public function saveEvent(): void
    {
        $this->validate($this->rules());

        $ticketAmountCents = null;
        if ($this->formRequiresPayment) {
            $normalized = str_replace(',', '.', trim($this->formTicketAmount));
            $ticketAmountCents = (int) round(((float) $normalized) * 100);
        }

        $payload = [
            'title' => $this->formTitle,
            'slug' => $this->formSlug,
            'timezone' => $this->formTimezone,
            'status' => EventStatus::from($this->formStatus),
            'capacity' => $this->formCapacity,
            'requires_ref' => $this->formRequiresRef,
            'requires_turnstile' => $this->formRequiresTurnstile,
            'requires_photo' => $this->formRequiresPhoto,
            'registration_open' => $this->formRegistrationOpen,
            'skip_email_confirmation' => $this->formRequiresPayment ? false : $this->formSkipEmailConfirmation,
            'closed_message' => $this->formClosedMessage !== '' ? $this->formClosedMessage : null,
            'invite_template_key' => $this->formInviteTemplateKey !== '' ? $this->formInviteTemplateKey : null,
            'starts_at' => $this->formStartsAt !== '' ? $this->formStartsAt : null,
            'ends_at' => $this->formEndsAt !== '' ? $this->formEndsAt : null,
            'requires_payment' => $this->formRequiresPayment,
            'ticket_amount_cents' => $this->formRequiresPayment ? $ticketAmountCents : null,
            'mercado_pago_account_id' => $this->formRequiresPayment ? $this->formMercadoPagoAccountId : null,
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
            'formRequiresPhoto' => ['boolean'],
            'formRegistrationOpen' => ['boolean'],
            'formSkipEmailConfirmation' => ['boolean'],
            'formClosedMessage' => ['nullable', 'string', 'max:2000'],
            'formInviteTemplateKey' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'formRequiresPayment' => ['boolean'],
            'formTicketAmount' => [
                Rule::requiredIf($this->formRequiresPayment),
                'nullable',
                'numeric',
                'min:0.01',
                'max:99999.99',
            ],
            'formMercadoPagoAccountId' => [
                Rule::requiredIf($this->formRequiresPayment),
                'nullable',
                'uuid',
                Rule::exists('mercado_pago_accounts', 'id')->where(fn ($q) => $q->where('owner_id', auth()->id())),
            ],
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
        $this->formRequiresPhoto = true;
        $this->formRegistrationOpen = true;
        $this->formSkipEmailConfirmation = false;
        $this->formClosedMessage = '';
        $this->formInviteTemplateKey = '';
        $this->formRequiresPayment = false;
        $this->formTicketAmount = '';
        $this->formMercadoPagoAccountId = null;
    }

    public function render()
    {
        $events = Event::query()
            ->where('owner_id', auth()->id())
            ->orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->get();

        $mercadoPagoAccounts = MercadoPagoAccount::query()
            ->where('owner_id', auth()->id())
            ->orderBy('label')
            ->get();

        return view('livewire.events.hub-page', [
            'events' => $events,
            'mercadoPagoAccounts' => $mercadoPagoAccounts,
        ]);
    }
}
