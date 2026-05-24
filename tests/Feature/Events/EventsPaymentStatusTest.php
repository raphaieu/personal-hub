<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestStatus;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventsPaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_endpoint_returns_pending_with_countdown(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'slug' => 'status-party',
            'status' => EventStatus::Published,
            'requires_payment' => true,
            'ticket_amount_cents' => 2000,
        ]);

        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::PendingPayment,
            'payment_return_token' => 'poll-token-123',
            'payment_expires_at' => now()->addMinutes(10),
            'name' => 'Ana',
            'email' => 'ana@example.com',
        ]);

        $response = $this->getJson('/events/payment/status/poll-token-123');

        $response->assertOk()
            ->assertJsonPath('status', 'pending_payment')
            ->assertJsonPath('event.slug', 'status-party')
            ->assertJsonPath('guestName', 'Ana')
            ->assertJsonStructure(['secondsRemaining', 'expiresAt', 'eventPageUrl']);
    }

    public function test_status_returns_not_found_after_guest_deleted(): void
    {
        $response = $this->getJson('/events/payment/status/missing-token');

        $response->assertOk()
            ->assertJsonPath('status', 'not_found');
    }
}
