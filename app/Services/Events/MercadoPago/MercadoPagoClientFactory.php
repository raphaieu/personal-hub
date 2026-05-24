<?php

namespace App\Services\Events\MercadoPago;

use App\Enums\Events\MercadoPagoEnvironment;
use App\Models\MercadoPagoAccount;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\User\UserClient;
use MercadoPago\MercadoPagoConfig;

final class MercadoPagoClientFactory
{
    public function configure(MercadoPagoAccount $account): void
    {
        MercadoPagoConfig::setAccessToken($account->access_token);
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::SERVER);
    }

    public function preferenceClient(MercadoPagoAccount $account): PreferenceClient
    {
        $this->configure($account);

        return new PreferenceClient;
    }

    public function paymentClient(MercadoPagoAccount $account): PaymentClient
    {
        $this->configure($account);

        return new PaymentClient;
    }

    public function userClient(MercadoPagoAccount $account): UserClient
    {
        $this->configure($account);

        return new UserClient;
    }

    public function checkoutUrl(MercadoPagoAccount $account, ?string $initPoint, ?string $sandboxInitPoint): ?string
    {
        return $account->environment === MercadoPagoEnvironment::Sandbox
            ? ($sandboxInitPoint ?? $initPoint)
            : ($initPoint ?? $sandboxInitPoint);
    }
}
