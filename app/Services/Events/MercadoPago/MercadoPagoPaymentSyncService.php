<?php

namespace App\Services\Events\MercadoPago;

use App\Enums\Events\GuestPaymentStatus;
use App\Enums\Events\GuestStatus;
use App\Enums\Events\GuestTicketIssuanceResult;
use App\Models\Guest;
use App\Models\GuestPayment;
use App\Models\MercadoPagoAccount;
use App\Services\Events\GuestTicketIssuanceService;
use Illuminate\Support\Facades\Log;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPSearchRequest;
use MercadoPago\Resources\Payment;

class MercadoPagoPaymentSyncService
{
    public function __construct(
        private readonly MercadoPagoClientFactory $clientFactory,
        private readonly GuestTicketIssuanceService $ticketIssuanceService,
    ) {}

    public function syncGuestPayment(Guest $guest): void
    {
        $guest->loadMissing(['payment.mercadoPagoAccount', 'event']);

        if ($guest->status !== GuestStatus::PendingPayment) {
            return;
        }

        $paymentRecord = $guest->payment;
        if ($paymentRecord === null) {
            return;
        }

        $account = $paymentRecord->mercadoPagoAccount;
        $mpPayment = $this->resolveMercadoPagoPayment($account, $paymentRecord, $guest);

        if ($mpPayment === null) {
            return;
        }

        $this->applyMercadoPagoPayment($guest, $paymentRecord, $mpPayment);
    }

    public function applyMercadoPagoPayment(Guest $guest, GuestPayment $paymentRecord, Payment $mpPayment): void
    {
        $status = (string) ($mpPayment->status ?? '');
        $paymentId = $mpPayment->id !== null ? (string) $mpPayment->id : null;

        $paymentRecord->forceFill([
            'payment_id' => $paymentId ?? $paymentRecord->payment_id,
            'mp_last_payload' => json_decode(json_encode($mpPayment), true),
        ]);

        if ($status === 'approved') {
            $paymentRecord->forceFill([
                'status' => GuestPaymentStatus::Approved,
                'paid_at' => now(),
            ])->save();

            $result = $this->ticketIssuanceService->confirmAndSendTicket($guest->fresh());

            if ($result === GuestTicketIssuanceResult::CapacityReached) {
                Log::warning('events.payment.capacity_reached_after_approval', [
                    'guest_id' => $guest->id,
                    'payment_id' => $paymentId,
                ]);
            }

            return;
        }

        if (in_array($status, ['rejected', 'cancelled'], true)) {
            $paymentRecord->forceFill([
                'status' => GuestPaymentStatus::Rejected,
            ])->save();

            $guest->delete();

            return;
        }

        $paymentRecord->save();
    }

    private function resolveMercadoPagoPayment(
        MercadoPagoAccount $account,
        GuestPayment $paymentRecord,
        Guest $guest,
    ): ?Payment {
        $client = $this->clientFactory->paymentClient($account);

        if ($paymentRecord->payment_id !== null) {
            try {
                return $client->get((int) $paymentRecord->payment_id);
            } catch (MPApiException $exception) {
                report($exception);
            }
        }

        try {
            $search = $client->search(new MPSearchRequest(
                limit: 1,
                offset: 0,
                filters: [
                    'external_reference' => $guest->id,
                ],
            ));

            $results = $search->results ?? [];

            return $results[0] ?? null;
        } catch (MPApiException $exception) {
            report($exception);

            return null;
        }
    }
}
