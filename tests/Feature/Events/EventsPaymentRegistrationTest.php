<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestStatus;
use App\Enums\Events\MercadoPagoEnvironment;
use App\Mail\Events\GuestInterestConfirmationMail;
use App\Models\Event;
use App\Models\Guest;
use App\Models\MercadoPagoAccount;
use App\Models\User;
use App\Services\Events\MercadoPago\MercadoPagoCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class EventsPaymentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_register_paid_event_returns_checkout_flow(): void
    {
        $checkoutUrl = 'https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-test-123';

        $this->mock(MercadoPagoCheckoutService::class)
            ->shouldReceive('createCheckout')
            ->once()
            ->andReturn($checkoutUrl);

        $user = User::factory()->create();
        $account = MercadoPagoAccount::factory()->for($user, 'owner')->create([
            'environment' => MercadoPagoEnvironment::Sandbox,
        ]);
        $event = Event::factory()->for($user, 'owner')->create([
            'slug' => 'paid-party',
            'status' => EventStatus::Published,
            'requires_turnstile' => false,
            'requires_photo' => false,
            'requires_payment' => true,
            'ticket_amount_cents' => 2000,
            'mercado_pago_account_id' => $account->id,
        ]);

        $response = $this->post('/api/v1/events/'.$event->slug.'/register', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'consent_terms' => '1',
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('flow', 'checkout')
            ->assertJsonPath('checkoutUrl', $checkoutUrl);

        $this->assertDatabaseHas('guests', [
            'event_id' => $event->id,
            'email' => 'maria@example.com',
            'status' => GuestStatus::PendingPayment->value,
        ]);

        $guest = Guest::query()->where('event_id', $event->id)->first();
        $this->assertNotNull($guest->payment_return_token);

        Mail::assertNotQueued(GuestInterestConfirmationMail::class);
    }

    public function test_register_free_event_returns_email_confirmation_flow(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'slug' => 'free-party',
            'status' => EventStatus::Published,
            'requires_turnstile' => false,
            'requires_payment' => false,
        ]);

        $photo = UploadedFile::fake()->image('face.jpg', 200, 200);

        $response = $this->post('/api/v1/events/'.$event->slug.'/register', [
            'name' => 'Maria Silva',
            'email' => 'maria-free@example.com',
            'consent_terms' => '1',
            'photo' => $photo,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('flow', 'email_confirmation');

        Mail::assertQueued(GuestInterestConfirmationMail::class);
    }
}
