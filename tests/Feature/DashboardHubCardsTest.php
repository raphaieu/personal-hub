<?php


namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardHubCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_hub_cards_and_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Chat IA', false);
        $response->assertSee('Threads Hub', false);
        $response->assertSee('Utilidades', false);
        $response->assertSee('Álbuns', false);
        $response->assertSee('Eventos', false);
        $response->assertSee(route('chat', [], false), false);
        $response->assertSee(route('albums.hub', [], false), false);
        $response->assertSee(route('events.hub', [], false), false);
    }
}
