<?php

namespace Tests\Feature;

use App\Data\AiCompletionResult;
use App\Services\NeuronAIService;
use Tests\TestCase;

final class IaraGatewayTest extends TestCase
{
    public function test_prompt_is_required(): void
    {
        $this->postJson('/iara', [])
            ->assertUnprocessable();
    }

    public function test_returns_completion_json(): void
    {
        $this->mock(NeuronAIService::class, function ($mock): void {
            $mock->shouldReceive('complete')
                ->once()
                ->andReturn(new AiCompletionResult(
                    success: true,
                    text: 'Resposta simulada.',
                    provider: 'groq',
                    model: 'llama-3.3-70b-versatile',
                    latencyMs: 12,
                    fallbackUsed: false,
                ));
        });

        $this->postJson('/iara', ['prompt' => 'Olá'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('content', 'Resposta simulada.')
            ->assertJsonPath('provider', 'groq')
            ->assertJsonPath('fallback_used', false);
    }

    public function test_returns_503_when_providers_fail(): void
    {
        $this->mock(NeuronAIService::class, function ($mock): void {
            $mock->shouldReceive('complete')
                ->once()
                ->andReturn(new AiCompletionResult(
                    success: false,
                    text: '',
                    provider: '',
                    model: '',
                    latencyMs: 0,
                    fallbackUsed: true,
                    errorType: 'no_provider',
                    errorDetail: 'nenhum',
                ));
        });

        $this->postJson('/iara', ['prompt' => 'Olá'])
            ->assertStatus(503)
            ->assertJsonPath('ok', false);
    }
}
