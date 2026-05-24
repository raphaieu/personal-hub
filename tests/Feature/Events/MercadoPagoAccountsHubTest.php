<?php

namespace Tests\Feature\Events;

use App\Enums\Events\MercadoPagoEnvironment;
use App\Livewire\Events\MercadoPagoAccountsPanel;
use App\Models\MercadoPagoAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class MercadoPagoAccountsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_mercado_pago_account(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(MercadoPagoAccountsPanel::class)
            ->set('formLabel', 'Conta Festa')
            ->set('formPublicKey', 'TEST-PUBLIC')
            ->set('formAccessToken', 'TEST-ACCESS')
            ->set('formEnvironment', MercadoPagoEnvironment::Sandbox->value)
            ->call('saveAccount')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mercado_pago_accounts', [
            'owner_id' => $user->id,
            'label' => 'Conta Festa',
            'public_key' => 'TEST-PUBLIC',
            'environment' => MercadoPagoEnvironment::Sandbox->value,
        ]);

        $account = MercadoPagoAccount::query()->where('owner_id', $user->id)->first();
        $this->assertNotNull($account);
        $this->assertSame('TEST-ACCESS', $account->access_token);
    }
}
