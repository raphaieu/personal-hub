<?php

namespace Tests\Feature\Events;

use App\Contracts\AiVisionServiceInterface;
use App\Data\AiCompletionResult;
use App\Enums\Events\EventStatus;
use App\Jobs\Events\ProcessEventFlyerJob;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventFlyerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EventsFlyerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    public function test_flyer_upload_creates_draft_and_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/me/events/flyer-draft', [
            'flyer' => UploadedFile::fake()->image('flyer.jpg', 1080, 1350),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.status', 'draft')
            ->assertJsonPath('data.event.aiStatus', 'pending');

        $event = Event::query()->where('owner_id', $user->id)->firstOrFail();

        $this->assertNotNull($event->flyer_path);
        Storage::disk('s3')->assertExists($event->flyer_path);

        Queue::assertPushed(ProcessEventFlyerJob::class, fn ($job) => $job->eventId === $event->id);
        Queue::assertPushedOn('ai', ProcessEventFlyerJob::class);
    }

    public function test_flyer_upload_validates_file(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/v1/me/events/flyer-draft', [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['flyer']);

        $this->post('/api/v1/me/events/flyer-draft', [
            'flyer' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['flyer']);
    }

    public function test_flyer_upload_respects_daily_limit(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Event::factory()->count(5)->for($user, 'owner')->create([
            'flyer_path' => 'events/flyers/x/flyer.jpg',
            'created_at' => now()->subHours(2),
        ]);

        $this->post('/api/v1/me/events/flyer-draft', [
            'flyer' => UploadedFile::fake()->image('flyer.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['flyer']);
    }

    public function test_ai_status_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $event = Event::factory()->for($owner, 'owner')->create(['ai_status' => 'ready']);

        Sanctum::actingAs($intruder);
        $this->getJson('/api/v1/me/events/'.$event->id.'/ai-status')->assertForbidden();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/me/events/'.$event->id.'/ai-status')
            ->assertOk()
            ->assertJsonPath('data.aiStatus', 'ready')
            ->assertJsonPath('data.done', true);
    }

    public function test_process_applies_ai_proposal_and_generates_og_image(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'status' => EventStatus::Draft,
            'slug' => 'evento-abc12345',
            'title' => 'Novo evento',
            'flyer_path' => null,
            'ai_status' => 'pending',
        ]);

        // Flyer "real" no storage fake (imagem GD válida)
        $flyerPath = "events/flyers/{$event->id}/flyer.jpg";
        Storage::disk('s3')->put($flyerPath, UploadedFile::fake()->image('flyer.jpg', 1080, 1350)->get());
        $event->forceFill(['flyer_path' => $flyerPath])->save();

        $proposal = [
            'title' => 'Villa Jr. Faz 40',
            // Data no passado (flyer sem ano) — a validação sobe o ano até ficar futura
            'starts_at' => '2024-06-06 13:00',
            'date_label' => '06 de Junho',
            'date_sub' => 'Sábado',
            'time_label' => '13 horas',
            'location_name' => 'Kasa Azul',
            'location_sub' => 'Pedra do Sal, Salvador, BA',
            'headline' => 'Troco gente chata por uma roda de samba',
            'subheadline' => 'Venha celebrar os 40 anos do Villa com muito samba!',
            'badge' => '★ VILLA 40 ★',
            'rules' => [
                ['icon' => 'shirt', 'title' => 'Dress Code: ALL BLUE', 'text' => 'Venha de azul!'],
                ['icon' => 'hack', 'title' => 'Ícone inválido vira star', 'text' => null],
            ],
            'palette' => [
                'primary' => '#1d4ed8',
                'secondary' => '#93c5fd',
                'background' => '#0f1b33',
                'surface' => '#16233f',
                'text' => '#e8e6f0',
            ],
            'fonts' => ['heading' => 'Bebas Neue', 'body' => 'Fonte Inexistente'],
            'mode' => 'dark',
        ];

        $this->mock(AiVisionServiceInterface::class, function ($mock) use ($proposal) {
            $mock->shouldReceive('completeWithVision')
                ->once()
                ->andReturn(new AiCompletionResult(
                    success: true,
                    text: json_encode($proposal),
                    provider: 'anthropic',
                    model: 'claude-test',
                    latencyMs: 100,
                    fallbackUsed: false,
                ));
        });

        app(EventFlyerService::class)->process($event);

        $event->refresh();

        $this->assertSame('ready', $event->ai_status);
        $this->assertSame('Villa Jr. Faz 40', $event->title);
        $this->assertSame('villa-jr-faz-40', $event->slug);
        // 2024 → 2025 → 2026 (jun/2026 já passou) → 2027, o primeiro ano futuro
        $this->assertSame('2027-06-06 13:00', $event->starts_at->format('Y-m-d H:i'));
        $this->assertTrue($event->starts_at->isFuture());

        $this->assertSame('#1d4ed8', $event->theme_json['colors']['primary']);
        $this->assertSame('#0f1b33', $event->theme_json['colors']['background']);
        $this->assertSame('Bebas Neue', $event->theme_json['fonts']['heading']);
        // Fonte fora da allowlist cai no default
        $this->assertSame('Inter', $event->theme_json['fonts']['body']);

        $this->assertSame('Troco gente chata por uma roda de samba', $event->content_json['hero']['headline']);
        $this->assertSame('Kasa Azul', $event->content_json['info']['locationName']);
        $this->assertTrue($event->content_json['countdown']['enabled']);
        $this->assertCount(2, $event->content_json['rules']['items']);
        // Ícone fora da allowlist normalizado
        $this->assertSame('star', $event->content_json['rules']['items'][1]['icon']);

        // OG image derivada gerada no storage
        $this->assertNotNull($event->og_image_path);
        Storage::disk('s3')->assertExists($event->og_image_path);
    }

    public function test_process_marks_failed_but_keeps_og_image_when_ai_is_unavailable(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'status' => EventStatus::Draft,
            'flyer_path' => null,
            'ai_status' => 'pending',
        ]);

        $flyerPath = "events/flyers/{$event->id}/flyer.jpg";
        Storage::disk('s3')->put($flyerPath, UploadedFile::fake()->image('flyer.jpg')->get());
        $event->forceFill(['flyer_path' => $flyerPath])->save();

        $this->mock(AiVisionServiceInterface::class, function ($mock) {
            $mock->shouldReceive('completeWithVision')
                ->once()
                ->andReturn(new AiCompletionResult(
                    success: false,
                    text: '',
                    provider: '',
                    model: '',
                    latencyMs: 0,
                    fallbackUsed: true,
                    errorType: 'no_provider',
                    errorDetail: 'Nenhum provedor configurado.',
                ));
        });

        app(EventFlyerService::class)->process($event);

        $event->refresh();

        $this->assertSame('failed', $event->ai_status);
        // Fallback da spec: og:image é gerada mesmo sem IA — usuário segue manual
        $this->assertNotNull($event->og_image_path);
        Storage::disk('s3')->assertExists($event->og_image_path);
    }
}
