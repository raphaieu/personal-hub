<?php


namespace App\Http\Controllers\Events;

use App\Enums\Events\GuestStatus;
use App\Http\Controllers\Controller;
use App\Mail\Events\GuestTicketMail;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class GuestEmailConfirmationController extends Controller
{
    public function __invoke(string $token): View
    {
        $guest = Guest::query()->where('email_confirmation_token', $token)->first();

        if ($guest === null) {
            return view('events.email-confirm-invalid');
        }

        $guest->loadMissing('event');

        if ($guest->status === GuestStatus::Confirmed) {
            return view('events.email-already-confirmed', ['guest' => $guest]);
        }

        $capacityReached = false;

        DB::transaction(function () use ($guest, &$capacityReached): void {
            $event = Event::query()->whereKey($guest->event_id)->lockForUpdate()->firstOrFail();

            if ($event->capacity !== null && $event->confirmedGuestsCount() >= $event->capacity) {
                $capacityReached = true;

                return;
            }

            $guest->forceFill([
                'status' => GuestStatus::Confirmed,
                'email_confirmed_at' => now(),
                'email_confirmation_token' => null,
            ])->save();
        });

        if ($capacityReached) {
            return view('events.email-confirm-capacity');
        }

        $guest->refresh()->loadMissing('event');

        Mail::to($guest->email)->queue(new GuestTicketMail($guest));

        return view('events.email-confirmed-success', ['guest' => $guest]);
    }
}
