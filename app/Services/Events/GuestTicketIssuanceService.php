<?php

namespace App\Services\Events;

use App\Enums\Events\GuestStatus;
use App\Enums\Events\GuestTicketIssuanceResult;
use App\Mail\Events\GuestTicketMail;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class GuestTicketIssuanceService
{
    public function confirmAndSendTicket(Guest $guest): GuestTicketIssuanceResult
    {
        $guest->loadMissing('event');

        if ($guest->status === GuestStatus::Confirmed) {
            return GuestTicketIssuanceResult::AlreadyConfirmed;
        }

        $capacityReached = false;

        DB::transaction(function () use ($guest, &$capacityReached): void {
            $event = Event::query()->whereKey($guest->event_id)->lockForUpdate()->first();

            if ($event === null) {
                return;
            }

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
            return GuestTicketIssuanceResult::CapacityReached;
        }

        $guest->refresh()->loadMissing('event');

        if ($guest->status !== GuestStatus::Confirmed) {
            return GuestTicketIssuanceResult::GuestNotFound;
        }

        Mail::to($guest->email)->queue(new GuestTicketMail($guest));

        return GuestTicketIssuanceResult::Confirmed;
    }
}
