<?php

namespace App\Services\Events\MercadoPago;

use App\Enums\Events\GuestPaymentStatus;
use App\Enums\Events\GuestStatus;
use App\Models\Guest;
use App\Models\GuestPayment;
use App\Models\MercadoPagoAccount;
use Illuminate\Support\Facades\Log;
use MercadoPago\Exceptions\MPApiException;

final class MercadoPagoWebhookService
{
    public function __construct(
        private readonly MercadoPagoClientFactory $clientFactory,
        private readonly MercadoPagoPaymentSyncService $paymentSyncService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, ?string $queryPaymentId = null): void
    {
        $paymentId = $queryPaymentId
            ?? (string) ($payload['data']['id'] ?? $payload['id'] ?? '');

        if ($paymentId === '') {
            return;
        }

        $guestPayment = GuestPayment::query()
            ->where('payment_id', $paymentId)
            ->with('guest')
            ->first();

        if ($guestPayment?->guest !== null) {
            $this->paymentSyncService->syncGuestPayment($guestPayment->guest);

            return;
        }

        $guest = $this->resolveGuestByMercadoPagoPaymentId($paymentId);

        if ($guest === null) {
            Log::info('events.mercadopago.webhook_orphan', ['payment_id' => $paymentId]);

            return;
        }

        $guest->payment?->forceFill(['payment_id' => $paymentId])->save();

        $this->paymentSyncService->syncGuestPayment($guest);
    }

    private function resolveGuestByMercadoPagoPaymentId(string $paymentId): ?Guest
    {
        $accountIds = GuestPayment::query()
            ->where('status', GuestPaymentStatus::Pending)
            ->distinct()
            ->pluck('mercado_pago_account_id');

        foreach ($accountIds as $accountId) {
            $account = MercadoPagoAccount::query()->find($accountId);
            if ($account === null) {
                continue;
            }

            try {
                $mpPayment = $this->clientFactory->paymentClient($account)->get((int) $paymentId);
            } catch (MPApiException $exception) {
                continue;
            }

            $externalReference = (string) ($mpPayment->external_reference ?? '');
            if ($externalReference === '') {
                continue;
            }

            $guest = Guest::query()
                ->whereKey($externalReference)
                ->where('status', GuestStatus::PendingPayment)
                ->first();

            if ($guest !== null) {
                return $guest;
            }
        }

        return null;
    }
}
