<?php

namespace App\Services\Events;

use App\Enums\Events\GuestPaymentStatus;
use App\Enums\Events\GuestStatus;
use App\Enums\Events\GuestTicketIssuanceResult;
use App\Models\Guest;
use App\Services\Events\MercadoPago\MercadoPagoPaymentSyncService;
use Illuminate\Support\Facades\Log;

final class EventPaymentGuestCleanupService
{
    public function __construct(
        private readonly MercadoPagoPaymentSyncService $paymentSyncService,
        private readonly GuestTicketIssuanceService $ticketIssuanceService,
    ) {}

    public function expireIfNeeded(Guest $guest): void
    {
        $guest->loadMissing(['payment', 'event']);

        if ($guest->status !== GuestStatus::PendingPayment) {
            return;
        }

        if ($guest->payment_expires_at !== null && $guest->payment_expires_at->isFuture()) {
            return;
        }

        $this->paymentSyncService->syncGuestPayment($guest);
        $guest->refresh();

        if ($guest->status === GuestStatus::Confirmed) {
            return;
        }

        if ($guest->payment?->status === GuestPaymentStatus::Approved) {
            $result = $this->ticketIssuanceService->confirmAndSendTicket($guest);
            if ($result === GuestTicketIssuanceResult::Confirmed) {
                return;
            }
        }

        Log::info('events.payment.guest_expired', ['guest_id' => $guest->id]);

        $guest->delete();
    }
}
