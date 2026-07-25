<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MeEventsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_own_events(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Event::factory()->for($user, 'owner')->create(['title' => 'Meu evento']);
        Event::factory()->for($other, 'owner')->create(['title' => 'Evento alheio']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me/events');

        $response->assertOk()
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.title', 'Meu evento')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_store_creates_draft_with_generated_slug(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/me/events', [
            'title' => 'Aniversário da Joana 2026',
            'starts_at' => now()->addMonth()->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.slug', 'aniversario-da-joana-2026')
            ->assertJsonPath('data.event.status', 'draft')
            ->assertJsonPath('data.event.registrationOpen', false);

        $this->assertDatabaseHas('events', [
            'owner_id' => $user->id,
            'slug' => 'aniversario-da-joana-2026',
            'status' => 'draft',
        ]);
    }

    public function test_store_rejects_reserved_slug_and_increments_duplicates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/events', [
            'title' => 'Evento um',
            'slug' => 'app',
        ])->assertUnprocessable();

        Event::factory()->for($user, 'owner')->create(['slug' => 'festa']);

        $response = $this->postJson('/api/v1/me/events', [
            'title' => 'Festa',
            'slug' => 'festa',
        ]);

        // Slug informado já existe: unique na request barra — o service só resolve
        // quando o slug vem do título. Aqui a validação rejeita o duplicado.
        $response->assertUnprocessable();
    }

    public function test_store_auto_slug_gets_suffix_on_collision(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Event::factory()->for($user, 'owner')->create(['slug' => 'minha-festa']);

        $response = $this->postJson('/api/v1/me/events', ['title' => 'Minha Festa']);

        $response->assertCreated()
            ->assertJsonPath('data.event.slug', 'minha-festa-2');
    }

    public function test_show_update_and_policy_enforcement(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create(['status' => EventStatus::Draft]);

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/me/events/'.$event->id)->assertForbidden();
        $this->patchJson('/api/v1/me/events/'.$event->id, ['title' => 'Invasão'])->assertForbidden();
        $this->postJson('/api/v1/me/events/'.$event->id.'/publish')->assertForbidden();
        $this->postJson('/api/v1/me/events/'.$event->id.'/archive')->assertForbidden();

        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/me/events/'.$event->id, [
            'title' => 'Título novo',
            'capacity' => 50,
            'requires_ref' => true,
        ])->assertOk()
            ->assertJsonPath('data.event.title', 'Título novo')
            ->assertJsonPath('data.event.capacity', 50)
            ->assertJsonPath('data.event.requiresRef', true);
    }

    public function test_update_persists_theme_and_sanitizes_custom_css(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create(['status' => EventStatus::Draft]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me/events/'.$event->id, [
            'theme' => [
                'colors' => ['primary' => '#ff5500'],
                'fonts' => ['heading' => 'Bebas Neue'],
                'mode' => 'dark',
                'customCss' => '.hero-title { letter-spacing: 2px; }',
            ],
        ])->assertOk()
            ->assertJsonPath('data.event.theme.colors.primary', '#ff5500')
            ->assertJsonPath('data.event.theme.fonts.heading', 'Bebas Neue');

        // CSS com padrões perigosos é rejeitado
        $this->patchJson('/api/v1/me/events/'.$event->id, [
            'theme' => ['customCss' => 'body { position: fixed; }'],
        ])->assertUnprocessable();

        $this->patchJson('/api/v1/me/events/'.$event->id, [
            'theme' => ['customCss' => '.x { background: url(javascript:alert(1)); }'],
        ])->assertUnprocessable();

        // Cor inválida e fonte fora da allowlist são rejeitadas
        $this->patchJson('/api/v1/me/events/'.$event->id, [
            'theme' => ['colors' => ['primary' => 'red']],
        ])->assertUnprocessable();

        $this->patchJson('/api/v1/me/events/'.$event->id, [
            'theme' => ['fonts' => ['heading' => 'Comic Sans']],
        ])->assertUnprocessable();
    }

    public function test_update_persists_content_sections(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create(['status' => EventStatus::Draft]);
        Sanctum::actingAs($user);

        $content = [
            'hero' => ['enabled' => true, 'headline' => 'Festa de 40 anos', 'subheadline' => 'Vem celebrar!'],
            'rules' => [
                'enabled' => true,
                'title' => 'Regras da casa',
                'items' => [
                    ['icon' => 'beer', 'title' => 'Traga uma caixa', 'text' => 'Contribua!'],
                ],
            ],
        ];

        $this->patchJson('/api/v1/me/events/'.$event->id, ['content' => $content])
            ->assertOk()
            ->assertJsonPath('data.event.content.hero.headline', 'Festa de 40 anos')
            ->assertJsonPath('data.event.content.rules.items.0.icon', 'beer');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
        ]);
        $this->assertSame('Festa de 40 anos', $event->fresh()->content_json['hero']['headline']);
    }

    public function test_slug_is_frozen_after_publish(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'status' => EventStatus::Published,
            'slug' => 'festa-publicada',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me/events/'.$event->id, ['slug' => 'novo-slug'])
            ->assertUnprocessable();

        $this->assertSame('festa-publicada', $event->fresh()->slug);
    }

    public function test_publish_requires_verified_email_and_starts_at(): void
    {
        $user = User::factory()->unverified()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'status' => EventStatus::Draft,
            'starts_at' => now()->addWeek(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/events/'.$event->id.'/publish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $user->markEmailAsVerified();
        Sanctum::actingAs($user->fresh());

        $event->forceFill(['starts_at' => null])->save();
        $this->postJson('/api/v1/me/events/'.$event->id.'/publish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);

        $event->forceFill(['starts_at' => now()->addWeek()])->save();
        $this->postJson('/api/v1/me/events/'.$event->id.'/publish')
            ->assertOk()
            ->assertJsonPath('data.event.status', 'published')
            ->assertJsonPath('data.event.registrationOpen', true);

        $this->assertNotNull($event->fresh()->published_at);
    }

    public function test_publish_respects_max_published_events_limit(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Event::factory()->count(3)->for($user, 'owner')->create(['status' => EventStatus::Published]);
        $fourth = Event::factory()->for($user, 'owner')->create([
            'status' => EventStatus::Draft,
            'starts_at' => now()->addWeek(),
        ]);

        $this->postJson('/api/v1/me/events/'.$fourth->id.'/publish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_archive_sets_status_and_closes_registration(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create(['status' => EventStatus::Published]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/events/'.$event->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.event.status', 'archived')
            ->assertJsonPath('data.event.registrationOpen', false);
    }

    public function test_limits_endpoint_returns_limits_and_usage(): void
    {
        $user = User::factory()->create();
        Event::factory()->count(2)->for($user, 'owner')->create(['status' => EventStatus::Published]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/limits')
            ->assertOk()
            ->assertJsonPath('data.limits.max_published_events', 3)
            ->assertJsonPath('data.usage.publishedEvents', 2)
            ->assertJsonPath('data.usage.totalEvents', 2);
    }

    public function test_super_admin_can_manage_any_event(): void
    {
        $admin = User::factory()->create(['global_role' => 'super_admin']);
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner, 'owner')->create(['status' => EventStatus::Draft]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/me/events/'.$event->id)->assertOk();
        $this->patchJson('/api/v1/me/events/'.$event->id, ['title' => 'Moderado'])
            ->assertOk()
            ->assertJsonPath('data.event.title', 'Moderado');
    }
}
