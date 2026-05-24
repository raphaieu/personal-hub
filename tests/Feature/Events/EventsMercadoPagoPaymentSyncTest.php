<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestPaymentStatus;
use App\Enums\Events\GuestStatus;
use App\Mail\Events\GuestTicketMail;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPayment;
use App\Models\MercadoPagoAccount;
use App\Models\User;
use App\Services\Events\MercadoPago\MercadoPagoPaymentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use MercadoPago\Resources\Payment;
use Tests\TestCase;

final class EventsMercadoPagoPaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_apply_approved_payment_confirms_guest(): void
    {
        $user = User::factory()->create();
        $account = MercadoPagoAccount::factory()->for($user, 'owner')->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'status' => EventStatus::Published,
            'requires_payment' => true,
        ]);

        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::PendingPayment,
        ]);

        $paymentRecord = GuestPayment::query()->create([
            'guest_id' => $guest->id,
            'mercado_pago_account_id' => $account->id,
            'status' => GuestPaymentStatus::Pending,
            'amount_cents' => 2000,
            'currency' => 'BRL',
        ]);

        $mpPayment = new Payment;
        $mpPayment->id = 123;
        $mpPayment->status = 'approved';
        $mpPayment->external_reference = $guest->id;

        app(MercadoPagoPaymentSyncService::class)->applyMercadoPagoPayment(
            $guest->fresh(['payment']),
            $paymentRecord,
            $mpPayment,
        );

        $guest->refresh();
        $this->assertSame(GuestStatus::Confirmed, $guest->status);
        Mail::assertQueued(GuestTicketMail::class);
    }

    public function test_apply_rejected_payment_deletes_guest(): void
    {
        $user = User::factory()->create();
        $account = MercadoPagoAccount::factory()->for($user, 'owner')->create();
        $event = Event::factory()->for($user, 'owner')->create();

        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::PendingPayment,
        ]);

        $paymentRecord = GuestPayment::query()->create([
            'guest_id' => $guest->id,
            'mercado_pago_account_id' => $account->id,
            'status' => GuestPaymentStatus::Pending,
            'amount_cents' => 2000,
            'currency' => 'BRL',
        ]);

        $mpPayment = new Payment;
        $mpPayment->id = 456;
        $mpPayment->status = 'rejected';

        app(MercadoPagoPaymentSyncService::class)->applyMercadoPagoPayment(
            $guest->fresh(['payment']),
            $paymentRecord,
            $mpPayment,
        );

        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
    }
}
