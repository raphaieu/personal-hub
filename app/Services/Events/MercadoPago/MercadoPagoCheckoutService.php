<?php

namespace App\Services\Events\MercadoPago;

use App\Enums\Events\GuestPaymentStatus;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPayment;
use App\Models\MercadoPagoAccount;
use Illuminate\Validation\ValidationException;
use MercadoPago\Exceptions\MPApiException;

class MercadoPagoCheckoutService
{
    public function __construct(
        private readonly MercadoPagoClientFactory $clientFactory,
    ) {}

    /**
     * @throws ValidationException
     */
    public function createCheckout(Guest $guest, Event $event): string
    {
        $account = $this->resolveAccount($event);
        $amountCents = (int) $event->ticket_amount_cents;
        $returnUrl = $this->returnUrl($guest);
        $hubUrl = rtrim((string) config('events.hub_public_url'), '/');

        $request = [
            'items' => [
                [
                    'title' => $event->title,
                    'quantity' => 1,
                    'unit_price' => round($amountCents / 100, 2),
                    'currency_id' => 'BRL',
                ],
            ],
            'payer' => [
                'email' => $guest->email,
                'name' => $guest->name,
            ],
            'external_reference' => $guest->id,
            'notification_url' => $hubUrl.'/webhooks/mercadopago',
            'back_urls' => [
                'success' => $returnUrl,
                'failure' => $returnUrl,
                'pending' => $returnUrl,
            ],
            'auto_return' => 'approved',
            'payment_methods' => [
                'excluded_payment_types' => [
                    ['id' => 'ticket'],
                ],
            ],
        ];

        try {
            $preference = $this->clientFactory->preferenceClient($account)->create($request);
        } catch (MPApiException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'payment' => ['Não foi possível iniciar o pagamento. Tente novamente em instantes.'],
            ]);
        }

        $checkoutUrl = $this->clientFactory->checkoutUrl(
            $account,
            $preference->init_point,
            $preference->sandbox_init_point,
        );

        if ($checkoutUrl === null || $checkoutUrl === '') {
            throw ValidationException::withMessages([
                'payment' => ['Não foi possível obter o link de pagamento.'],
            ]);
        }

        GuestPayment::query()->updateOrCreate(
            ['guest_id' => $guest->id],
            [
                'mercado_pago_account_id' => $account->id,
                'preference_id' => $preference->id,
                'status' => GuestPaymentStatus::Pending,
                'amount_cents' => $amountCents,
                'currency' => 'BRL',
            ],
        );

        return $checkoutUrl;
    }

    private function resolveAccount(Event $event): MercadoPagoAccount
    {
        $account = $event->mercadoPagoAccount;

        if ($account === null) {
            throw ValidationException::withMessages([
                'payment' => ['Este evento não possui conta Mercado Pago configurada.'],
            ]);
        }

        return $account;
    }

    private function returnUrl(Guest $guest): string
    {
        $hubUrl = rtrim((string) config('events.hub_public_url'), '/');
        $token = (string) $guest->payment_return_token;

        return $hubUrl.'/events/payment/return/'.$token;
    }
}
