<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestPaymentStatus;
use App\Enums\Events\GuestStatus;
use App\Jobs\Events\ExpireUnpaidEventGuestsJob;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPayment;
use App\Models\MercadoPagoAccount;
use App\Models\User;
use App\Services\Events\EventPaymentGuestCleanupService;
use App\Services\Events\MercadoPago\MercadoPagoPaymentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class EventsPaymentExpireJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_job_deletes_expired_unpaid_guest_when_still_pending(): void
    {
        $user = User::factory()->create();
        $account = MercadoPagoAccount::factory()->for($user, 'owner')->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'requires_payment' => true,
            'mercado_pago_account_id' => $account->id,
        ]);

        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::PendingPayment,
            'payment_expires_at' => now()->subMinute(),
        ]);

        GuestPayment::query()->create([
            'guest_id' => $guest->id,
            'mercado_pago_account_id' => $account->id,
            'status' => GuestPaymentStatus::Pending,
            'amount_cents' => 2000,
            'currency' => 'BRL',
        ]);

        $sync = $this->mock(MercadoPagoPaymentSyncService::class);
        $sync->shouldReceive('syncGuestPayment')->once();

        (new ExpireUnpaidEventGuestsJob)->handle(app(EventPaymentGuestCleanupService::class));

        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
    }

    public function test_job_keeps_guest_when_sync_confirms(): void
    {
        $user = User::factory()->create();
        $account = MercadoPagoAccount::factory()->for($user, 'owner')->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'status' => EventStatus::Published,
            'requires_payment' => true,
        ]);

        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::PendingPayment,
            'payment_expires_at' => now()->subMinute(),
        ]);

        $sync = $this->mock(MercadoPagoPaymentSyncService::class);
        $sync->shouldReceive('syncGuestPayment')
            ->once()
            ->andReturnUsing(function (Guest $syncGuest): void {
                $syncGuest->forceFill([
                    'status' => GuestStatus::Confirmed,
                    'email_confirmed_at' => now(),
                ])->save();
            });

        (new ExpireUnpaidEventGuestsJob)->handle(app(EventPaymentGuestCleanupService::class));

        $guest->refresh();
        $this->assertSame(GuestStatus::Confirmed, $guest->status);
        Mail::assertNothingOutgoing();
    }
}
