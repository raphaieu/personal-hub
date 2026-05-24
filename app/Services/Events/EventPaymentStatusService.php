<?php

namespace App\Services\Events;

use App\Enums\Events\GuestPaymentStatus;
use App\Enums\Events\GuestStatus;
use App\Models\Guest;

final class EventPaymentStatusService
{
    public function __construct(
        private readonly MercadoPago\MercadoPagoPaymentSyncService $paymentSyncService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(string $returnToken): array
    {
        $guest = Guest::query()
            ->with('event')
            ->where('payment_return_token', $returnToken)
            ->first();

        if ($guest === null) {
            return [
                'status' => 'not_found',
                'secondsRemaining' => 0,
                'expiresAt' => null,
                'event' => null,
                'eventPageUrl' => null,
            ];
        }

        if ($guest->status === GuestStatus::PendingPayment) {
            $this->paymentSyncService->syncGuestPayment($guest);
            $guest->refresh();
        }

        $event = $guest->event;
        $eventPageUrl = $event !== null
            ? rtrim((string) config('events.frontend_url'), '/').'/'.$event->slug
            : null;

        $expiresAt = $guest->payment_expires_at;
        $secondsRemaining = $expiresAt !== null
            ? max(0, $expiresAt->getTimestamp() - now()->getTimestamp())
            : 0;

        $status = match (true) {
            $guest->status === GuestStatus::Confirmed => 'confirmed',
            $guest->status === GuestStatus::PendingPayment && $secondsRemaining <= 0 => 'expired',
            $guest->status === GuestStatus::PendingPayment => 'pending_payment',
            $guest->payment?->status === GuestPaymentStatus::Rejected => 'rejected',
            default => 'not_found',
        };

        return [
            'status' => $status,
            'secondsRemaining' => $secondsRemaining,
            'expiresAt' => $expiresAt?->toIso8601String(),
            'event' => $event !== null ? [
                'title' => $event->title,
                'slug' => $event->slug,
            ] : null,
            'eventPageUrl' => $eventPageUrl,
            'guestName' => $guest->name,
            'guestEmail' => $guest->email,
        ];
    }
}
