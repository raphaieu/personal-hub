<?php


namespace Tests\Feature\Events;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventsHubPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_from_hub(): void
    {
        $this->get(route('events.hub'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_hub(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('events.hub'))
            ->assertOk();
    }
}
