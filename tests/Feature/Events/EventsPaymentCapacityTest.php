<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestStatus;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventsPaymentCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_payment_reduces_spots_left_in_config(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'slug' => 'cap-party',
            'status' => EventStatus::Published,
            'requires_payment' => true,
            'ticket_amount_cents' => 1000,
            'capacity' => 1,
        ]);

        Guest::factory()->for($event)->create([
            'status' => GuestStatus::PendingPayment,
            'payment_expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->getJson('/api/v1/events/'.$event->slug.'/config');

        $response->assertOk()
            ->assertJsonPath('data.registration.open', false)
            ->assertJsonPath('data.registration.spotsLeft', 0);
    }

    public function test_reserved_guests_count_includes_pending_payment(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'capacity' => 2,
            'requires_payment' => true,
        ]);

        Guest::factory()->for($event)->create(['status' => GuestStatus::PendingPayment]);
        Guest::factory()->for($event)->create(['status' => GuestStatus::Confirmed]);

        $this->assertSame(2, $event->reservedGuestsCount());
        $this->assertSame(1, $event->confirmedGuestsCount());
    }
}
