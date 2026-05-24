<?php

namespace Tests\Feature\Events;

use App\Enums\Events\GuestPaymentStatus;
use App\Enums\Events\GuestStatus;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPayment;
use App\Models\MercadoPagoAccount;
use App\Models\User;
use App\Services\Events\MercadoPago\MercadoPagoPaymentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventsPaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_triggers_payment_sync_for_known_payment_id(): void
    {
        $user = User::factory()->create();
        $account = MercadoPagoAccount::factory()->for($user, 'owner')->create();
        $event = Event::factory()->for($user, 'owner')->create();

        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::PendingPayment,
        ]);

        GuestPayment::query()->create([
            'guest_id' => $guest->id,
            'mercado_pago_account_id' => $account->id,
            'payment_id' => '999001',
            'status' => GuestPaymentStatus::Pending,
            'amount_cents' => 1500,
            'currency' => 'BRL',
        ]);

        $this->mock(MercadoPagoPaymentSyncService::class)
            ->shouldReceive('syncGuestPayment')
            ->once()
            ->withArgs(fn (Guest $g): bool => $g->id === $guest->id);

        $this->post('/webhooks/mercadopago?data.id=999001', [
            'data' => ['id' => '999001'],
        ])->assertNoContent();
    }
}
