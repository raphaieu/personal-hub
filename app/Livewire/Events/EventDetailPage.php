<?php


namespace App\Livewire\Events;

use App\Enums\Events\GuestStatus;
use App\Mail\Events\GuestInviteMail;
use App\Models\Event;
use App\Models\Guest;
use App\Models\ReferralLink;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class EventDetailPage extends Component
{
    public Event $event;

    public ?string $guestEditingId = null;

    public string $guestFormName = '';

    public string $guestFormEmail = '';

    public string $guestFormPhone = '';

    public string $guestFormStatus = 'confirmed';

    public string $referralFormName = '';

    public function mount(Event $event): void
    {
        abort_if($event->owner_id !== auth()->id(), 403);
        $this->event = $event;
    }

    public function startCreateGuest(): void
    {
        $this->guestEditingId = null;
        $this->guestFormName = '';
        $this->guestFormEmail = '';
        $this->guestFormPhone = '';
        $this->guestFormStatus = GuestStatus::Confirmed->value;
        $this->resetValidation();
    }

    public function startEditGuest(string $guestId): void
    {
        $guest = Guest::query()
            ->where('event_id', $this->event->id)
            ->whereKey($guestId)
            ->firstOrFail();

        $this->guestEditingId = $guest->id;
        $this->guestFormName = $guest->name;
        $this->guestFormEmail = $guest->email;
        $this->guestFormPhone = (string) ($guest->phone ?? '');
        $this->guestFormStatus = $guest->status->value;
        $this->resetValidation();
    }

    public function saveGuest(): void
    {
        $this->validate([
            'guestFormName' => ['required', 'string', 'max:255'],
            'guestFormEmail' => [
                'required',
                'email',
                'max:255',
                Rule::unique('guests', 'email')
                    ->where(fn ($q) => $q->where('event_id', $this->event->id))
                    ->ignore($this->guestEditingId),
            ],
            'guestFormPhone' => ['nullable', 'string', 'max:32'],
            'guestFormStatus' => ['required', Rule::enum(GuestStatus::class)],
        ]);

        $email = strtolower(trim($this->guestFormEmail));

        $payload = [
            'name' => $this->guestFormName,
            'email' => $email,
            'phone' => $this->guestFormPhone !== '' ? $this->guestFormPhone : null,
            'status' => GuestStatus::from($this->guestFormStatus),
        ];

        if ($this->guestEditingId !== null) {
            Guest::query()
                ->where('event_id', $this->event->id)
                ->whereKey($this->guestEditingId)
                ->firstOrFail()
                ->update($payload);
            session()->flash('events_hub_notice', 'Convidado atualizado.');
        } else {
            $payload['event_id'] = $this->event->id;
            Guest::query()->create($payload);
            session()->flash('events_hub_notice', 'Convidado adicionado.');
        }

        $this->startCreateGuest();
        $this->event->refresh();
    }

    public function deleteGuest(string $guestId): void
    {
        Guest::query()
            ->where('event_id', $this->event->id)
            ->whereKey($guestId)
            ->delete();

        session()->flash('events_hub_notice', 'Convidado removido.');
        $this->startCreateGuest();
        $this->event->refresh();
    }

    public function sendInvite(string $guestId): void
    {
        $guest = Guest::query()
            ->where('event_id', $this->event->id)
            ->whereKey($guestId)
            ->firstOrFail();

        $guest->loadMissing('event');

        Mail::to($guest->email)->queue(new GuestInviteMail($guest));

        $guest->forceFill(['invite_sent_at' => now()])->save();

        session()->flash('events_hub_notice', 'Convite enfileirado para '.$guest->email.'.');
        $this->event->refresh();
    }

    public function createReferralLink(): void
    {
        $this->validate([
            'referralFormName' => ['required', 'string', 'max:255'],
        ]);

        ReferralLink::query()->create([
            'event_id' => $this->event->id,
            'name' => $this->referralFormName,
            'token' => Str::lower(Str::random(32)),
        ]);

        $this->referralFormName = '';
        session()->flash('events_hub_notice', 'Link de lista criado.');
        $this->event->refresh();
    }

    public function revokeReferral(string $referralId): void
    {
        ReferralLink::query()
            ->where('event_id', $this->event->id)
            ->whereKey($referralId)
            ->update(['revoked_at' => now()]);

        session()->flash('events_hub_notice', 'Link revogado.');
        $this->event->refresh();
    }

    public function render()
    {
        $guests = Guest::query()
            ->where('event_id', $this->event->id)
            ->orderBy('email')
            ->get();

        $referrals = ReferralLink::query()
            ->where('event_id', $this->event->id)
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.events.event-detail-page', [
            'guests' => $guests,
            'referrals' => $referrals,
            'publicRegistrationUrl' => rtrim((string) config('events.frontend_url'), '/').'/'.$this->event->slug,
        ]);
    }
}
