<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestStatus;
use App\Livewire\Events\EventCheckInPage;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class EventsCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_page_redirects_guests_to_login(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Published]);
        $guest = Guest::factory()->for($event)->create(['status' => GuestStatus::Confirmed]);

        $url = route('events.checkin.show').'?'.http_build_query([
            'event' => $event->id,
            'guest' => $guest->id,
        ]);

        $this->get($url)->assertRedirect();
    }

    public function test_authenticated_user_can_open_check_in_without_query_for_scanner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('events.checkin.show'));

        $response->assertOk();
        $response->assertSee('Aponte para o QR', false);
    }

    public function test_portaria_can_confirm_check_in_via_livewire(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create(['status' => EventStatus::Published]);
        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::Confirmed,
            'checked_in_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(EventCheckInPage::class, [
                'event' => $event->id,
                'guest' => $guest->id,
            ])
            ->call('confirmCheckIn')
            ->assertHasNoErrors();

        $guest->refresh();

        $this->assertNotNull($guest->checked_in_at);
    }
}
