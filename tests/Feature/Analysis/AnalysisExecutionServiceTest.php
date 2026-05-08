<?php

namespace Tests\Feature\Analysis;

use App\Data\AiCompletionResult;
use App\Data\Analysis\AnalysisExecutionInput;
use App\Data\Analysis\NormalizedContentItem;
use App\Models\AnalysisProfile;
use App\Services\Analysis\AnalysisExecutionService;
use App\Services\NeuronAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class AnalysisExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returns_structured_result_when_payload_matches_schema(): void
    {
        $profile = AnalysisProfile::query()->firstOrCreate([
            'slug' => AnalysisProfile::THREADS_OPPORTUNITIES_SLUG,
        ], [
            'name' => 'Threads',
            'analysis_type' => 'classification',
            'system_prompt' => 'prompt',
            'output_schema' => [
                'type' => 'object',
                'required' => ['category_slug', 'summary', 'relevance_score'],
                'properties' => [
                    'category_slug' => ['type' => 'string'],
                    'summary' => ['type' => 'string'],
                    'relevance_score' => ['type' => 'number'],
                ],
            ],
            'settings' => [
                'ai_task' => 'classification',
            ],
            'is_active' => true,
        ]);

        $this->mock(NeuronAIService::class)
            ->shouldReceive('complete')
            ->once()
            ->andReturn(new AiCompletionResult(
                success: true,
                text: json_encode([
                    'category_slug' => 'freela',
                    'summary' => 'Resumo objetivo',
                    'relevance_score' => 0.81,
                    'labels' => ['remoto'],
                ], JSON_THROW_ON_ERROR),
                provider: 'groq',
                model: 'llama',
                latencyMs: 500,
                fallbackUsed: false,
            ));

        $result = app(AnalysisExecutionService::class)->execute(
            new AnalysisExecutionInput(
                item: new NormalizedContentItem(
                    channel: 'threads',
                    itemType: 'threads_comment',
                    externalId: 'comment-1',
                    contentText: 'texto teste'
                ),
                profile: $profile,
            )
        );

        $this->assertSame('completed', $result->status);
        $this->assertSame('freela', $result->category);
        $this->assertSame('Resumo objetivo', $result->summary);
        $this->assertSame(0.81, $result->relevanceScore);
        $this->assertSame(['remoto'], $result->labels);
    }

    public function test_execute_throws_when_required_schema_field_is_missing(): void
    {
        $profile = AnalysisProfile::query()->firstOrCreate([
            'slug' => AnalysisProfile::THREADS_OPPORTUNITIES_SLUG,
        ], [
            'name' => 'Threads',
            'analysis_type' => 'classification',
            'system_prompt' => 'prompt',
            'output_schema' => [
                'type' => 'object',
                'required' => ['category_slug', 'summary', 'relevance_score'],
            ],
            'settings' => [
                'ai_task' => 'classification',
            ],
            'is_active' => true,
        ]);

        $this->mock(NeuronAIService::class)
            ->shouldReceive('complete')
            ->once()
            ->andReturn(new AiCompletionResult(
                success: true,
                text: json_encode([
                    'category_slug' => 'freela',
                    'summary' => 'sem score',
                ], JSON_THROW_ON_ERROR),
                provider: 'groq',
                model: 'llama',
                latencyMs: 500,
                fallbackUsed: false,
            ));

        $this->expectException(RuntimeException::class);

        app(AnalysisExecutionService::class)->execute(
            new AnalysisExecutionInput(
                item: new NormalizedContentItem(
                    channel: 'threads',
                    itemType: 'threads_comment',
                    externalId: 'comment-2',
                    contentText: 'texto teste'
                ),
                profile: $profile,
            )
        );
    }
}
