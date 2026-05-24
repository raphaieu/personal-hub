<?php

namespace Database\Factories;

use App\Enums\Events\MercadoPagoEnvironment;
use App\Models\MercadoPagoAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MercadoPagoAccount>
 */
final class MercadoPagoAccountFactory extends Factory
{
    protected $model = MercadoPagoAccount::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'label' => 'Conta teste',
            'public_key' => 'TEST-public-key',
            'access_token' => 'TEST-access-token',
            'environment' => MercadoPagoEnvironment::Sandbox,
        ];
    }
}
