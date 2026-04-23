<?php

namespace Tests\Feature;

use App\Data\AiCompletionResult;
use App\Models\User;
use App\Services\NeuronAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_chat_page(): void
    {
        $response = $this->get(route('chat'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_chat_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('chat'));

        $response->assertOk();
        $response->assertSee('Chat IA', escape: false);
    }

    public function test_chat_options_returns_json_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('api.ai.chat-options'));

        $response->assertOk();
        $response->assertJsonStructure([
            'providers',
            'transcription',
            'image_generation',
            'defaults',
            'meta',
        ]);
    }

    public function test_chat_completion_accepts_json_and_calls_neuron_facade(): void
    {
        $user = User::factory()->create();

        $this->mock(NeuronAIService::class, function ($mock): void {
            $mock->shouldReceive('completeDirect')
                ->once()
                ->andReturn(new AiCompletionResult(
                    success: true,
                    text: 'Resposta de teste.',
                    provider: 'openai',
                    model: 'gpt-4o-mini',
                    latencyMs: 5,
                    fallbackUsed: false,
                ));
        });

        $response = $this->actingAs($user)->postJson(route('api.ai.chat'), [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'message' => 'Olá',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('content', 'Resposta de teste.');
        $response->assertJsonPath('provider', 'openai');
    }

    public function test_transcribe_openai_uses_http_client(): void
    {
        Config::set('services.openai.api_key', 'sk-test-key');

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Transcrito.'], 200),
        ]);

        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('clip.webm', 50, 'audio/webm');

        $response = $this->actingAs($user)->post(route('api.ai.transcribe'), [
            'audio' => $file,
            'engine' => 'openai',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('text', 'Transcrito.');
        Http::assertSent(function ($request): bool {
            return str_contains((string) $request->url(), 'api.openai.com/v1/audio/transcriptions');
        });
    }

    public function test_image_generation_openai_uses_http_client(): void
    {
        Config::set('services.openai.api_key', 'sk-test-key');

        Http::fake([
            'https://api.openai.com/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/img.png'],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.ai.images'), [
            'prompt' => 'Um gato astronauta',
            'response_format' => 'url',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('url', 'https://example.com/img.png');
    }
}
