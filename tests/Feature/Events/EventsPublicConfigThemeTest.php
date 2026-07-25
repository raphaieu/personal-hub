<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventsPublicConfigThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_exposes_default_theme_when_event_has_none(): void
    {
        $event = Event::factory()->for(User::factory(), 'owner')->create([
            'status' => EventStatus::Published,
            'theme_json' => null,
        ]);

        $response = $this->getJson('/api/v1/events/'.$event->slug.'/config');

        $response->assertOk()
            ->assertJsonPath('data.theme.colors.primary', '#6c5ce7')
            ->assertJsonPath('data.theme.fonts.heading', 'Outfit')
            ->assertJsonPath('data.theme.mode', 'dark')
            ->assertJsonPath('data.og.themeColor', '#0a0a0f')
            ->assertJsonPath('data.og.image', null);
    }

    public function test_config_merges_event_theme_with_defaults(): void
    {
        $event = Event::factory()->for(User::factory(), 'owner')->create([
            'status' => EventStatus::Published,
            'theme_json' => [
                'colors' => ['primary' => '#ff5500', 'background' => '#101020'],
            ],
            'content_json' => [
                'hero' => ['enabled' => true, 'headline' => 'Minha festa'],
            ],
        ]);

        $response = $this->getJson('/api/v1/events/'.$event->slug.'/config');

        $response->assertOk()
            ->assertJsonPath('data.theme.colors.primary', '#ff5500')
            ->assertJsonPath('data.theme.colors.secondary', '#a29bfe') // default preservado
            ->assertJsonPath('data.og.themeColor', '#101020')
            ->assertJsonPath('data.content.hero.headline', 'Minha festa');
    }

    public function test_config_exposes_og_image_url_when_present(): void
    {
        $event = Event::factory()->for(User::factory(), 'owner')->create([
            'status' => EventStatus::Published,
            'og_image_path' => 'events/flyers/abc/og.jpg',
        ]);

        $response = $this->getJson('/api/v1/events/'.$event->slug.'/config');

        $response->assertOk();
        $this->assertStringContainsString('events/flyers/abc/og.jpg', (string) $response->json('data.og.image'));
    }
}
