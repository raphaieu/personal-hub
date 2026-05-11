<?php


namespace App\Livewire\Events;

use App\Enums\Events\GuestStatus;
use App\Models\Guest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.events-checkin')]
final class EventCheckInPage extends Component
{
    public ?string $eventId = null;

    public ?string $guestId = null;

    public ?Guest $guestModel = null;

    public string $notice = '';

    public function mount(?string $event = null, ?string $guest = null): void
    {
        $event ??= request()->query('event');
        $guest ??= request()->query('guest');

        if ($event === null && $guest === null) {
            $this->eventId = null;
            $this->guestId = null;
            $this->guestModel = null;

            return;
        }

        if (! is_string($event) || ! is_string($guest) || ! Str::isUuid($event) || ! Str::isUuid($guest)) {
            $this->notice = 'Link inválido. Use a câmera nesta página ou abra o QR completo do ingresso.';

            return;
        }

        $this->eventId = $event;
        $this->guestId = $guest;
        $this->loadGuestModel();
    }

    public function loadGuestFromScan(string $eventId, string $guestId): void
    {
        $this->notice = '';

        if (! Str::isUuid($eventId) || ! Str::isUuid($guestId)) {
            $this->notice = 'QR inválido.';

            return;
        }

        $this->eventId = $eventId;
        $this->guestId = $guestId;
        $this->loadGuestModel();
    }

    public function resetScanner(): void
    {
        $this->guestModel = null;
        $this->eventId = null;
        $this->guestId = null;
        $this->notice = '';
    }

    public function confirmCheckIn(): void
    {
        $guest = $this->guestModel;
        if ($guest === null) {
            return;
        }

        if ($guest->status !== GuestStatus::Confirmed) {
            $this->notice = 'Este convidado não está confirmado para o evento.';

            return;
        }

        if ($guest->checked_in_at !== null) {
            $tz = $guest->event->timezone ?? 'America/Sao_Paulo';
            $this->notice = 'Check-in já registrado em '.$guest->checked_in_at->timezone($tz)->format('d/m/Y H:i');

            return;
        }

        $guest->forceFill(['checked_in_at' => now()])->save();
        $this->guestModel = $guest->fresh(['event']);

        session()->flash('events_checkin_notice', 'Entrada confirmada para '.$guest->name.'.');
        $this->notice = '';
    }

    public function denyEntry(): void
    {
        $this->notice = 'Entrada não autorizada neste momento. Confira os dados com o convidado antes de liberar.';
    }

    public function render(): View
    {
        return view('livewire.events.event-check-in-page');
    }

    private function loadGuestModel(): void
    {
        $this->guestModel = null;

        if ($this->eventId === null || $this->guestId === null) {
            return;
        }

        $guest = Guest::query()
            ->whereKey($this->guestId)
            ->where('event_id', $this->eventId)
            ->with('event')
            ->first();

        $this->guestModel = $guest;

        if ($guest === null) {
            $this->notice = 'Ingresso não encontrado ou inválido.';
        }
    }
}
