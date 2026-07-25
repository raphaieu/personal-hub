<?php

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

    public function test_non_owner_cannot_load_guest_for_check_in(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $event = Event::factory()->for($owner, 'owner')->create(['status' => EventStatus::Published]);
        $guest = Guest::factory()->for($event)->create(['status' => GuestStatus::Confirmed]);

        Livewire::actingAs($intruder)
            ->test(EventCheckInPage::class, [
                'event' => $event->id,
                'guest' => $guest->id,
            ])
            ->assertSet('notice', 'Você não tem permissão para operar o check-in deste evento.')
            ->assertSet('guestModel', null);
    }

    public function test_non_owner_cannot_confirm_check_in_even_with_hydrated_guest(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $event = Event::factory()->for($owner, 'owner')->create(['status' => EventStatus::Published]);
        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::Confirmed,
            'checked_in_at' => null,
        ]);

        Livewire::actingAs($intruder)
            ->test(EventCheckInPage::class)
            ->set('guestModel', $guest)
            ->call('confirmCheckIn')
            ->assertSet('notice', 'Você não tem permissão para operar o check-in deste evento.');

        $this->assertNull($guest->refresh()->checked_in_at);
    }

    public function test_super_admin_can_check_in_any_event(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['global_role' => 'super_admin']);
        $event = Event::factory()->for($owner, 'owner')->create(['status' => EventStatus::Published]);
        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::Confirmed,
            'checked_in_at' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(EventCheckInPage::class, [
                'event' => $event->id,
                'guest' => $guest->id,
            ])
            ->call('confirmCheckIn')
            ->assertHasNoErrors();

        $this->assertNotNull($guest->refresh()->checked_in_at);
    }
}
