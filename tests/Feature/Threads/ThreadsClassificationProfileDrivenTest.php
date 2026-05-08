<?php

namespace Tests\Feature\Threads;

use App\Data\AiCompletionResult;
use App\Models\AnalysisProfile;
use App\Models\ThreadsCategory;
use App\Models\ThreadsComment;
use App\Models\ThreadsPost;
use App\Models\ThreadsSource;
use App\Services\NeuronAIService;
use App\Services\Threads\ThreadsClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ThreadsClassificationProfileDrivenTest extends TestCase
{
    use RefreshDatabase;

    public function test_threads_classification_uses_profile_prompt_and_threshold(): void
    {
        $profile = AnalysisProfile::query()->updateOrCreate([
            'slug' => AnalysisProfile::THREADS_OPPORTUNITIES_SLUG,
        ], [
            'name' => 'Threads Opportunities',
            'channel' => 'threads',
            'analysis_type' => 'classification',
            'system_prompt' => 'PROMPT PROFILE THREADS',
            'allowed_categories' => ['freela', 'outros'],
            'score_threshold' => 70,
            'output_schema' => [
                'required' => ['category_slug', 'summary', 'relevance_score'],
            ],
            'settings' => [
                'ai_task' => 'classification',
            ],
            'is_active' => true,
        ]);

        $category = ThreadsCategory::query()->create([
            'slug' => 'freela',
            'name' => 'Freela',
            'analysis_profile_id' => $profile->id,
            'is_active' => true,
        ]);

        $source = ThreadsSource::query()->create([
            'type' => 'keyword',
            'label' => 'Freelas',
            'keyword' => 'freela laravel',
            'analysis_profile_id' => $profile->id,
            'is_active' => true,
        ]);

        $post = ThreadsPost::query()->create([
            'external_id' => 'post-123',
            'threads_source_id' => $source->id,
        ]);

        $comment = ThreadsComment::query()->create([
            'threads_post_id' => $post->id,
            'external_id' => 'comment-123',
            'content' => 'Tenho uma vaga de freela',
            'status' => 'pending_review',
        ]);

        $this->mock(NeuronAIService::class)
            ->shouldReceive('complete')
            ->once()
            ->withArgs(function (string $userPrompt, $task, ?string $systemPromptOverride, bool $expectJson): bool {
                return $systemPromptOverride === 'PROMPT PROFILE THREADS'
                    && $task->value === 'classification'
                    && $expectJson === true
                    && str_contains($userPrompt, 'Tenho uma vaga de freela');
            })
            ->andReturn(new AiCompletionResult(
                success: true,
                text: json_encode([
                    'category_slug' => 'freela',
                    'summary' => 'Oportunidade de freela',
                    'relevance_score' => 0.90,
                ], JSON_THROW_ON_ERROR),
                provider: 'groq',
                model: 'llama',
                latencyMs: 300,
                fallbackUsed: false,
            ));

        $classified = app(ThreadsClassificationService::class)->classifyComment($comment);

        $this->assertSame('pending_review', $classified->status);
        $this->assertSame($category->id, $classified->threads_category_id);
        $this->assertSame('90.00', (string) $classified->ai_relevance_score);
        $this->assertSame('threads-opportunities', $classified->ai_meta['profile_slug']);
    }
}
