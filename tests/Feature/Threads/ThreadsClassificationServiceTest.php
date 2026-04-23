<?php

namespace Tests\Feature\Threads;

use App\Data\AiCompletionResult;
use App\Jobs\ClassifyCommentsJob;
use App\Jobs\DispatchPendingThreadsClassificationJob;
use App\Models\ThreadsCategory;
use App\Models\ThreadsComment;
use App\Models\ThreadsPost;
use App\Services\NeuronAIService;
use App\Services\Threads\ThreadsClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class ThreadsClassificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_classification_marks_comment_as_ignored_when_score_is_below_threshold(): void
    {
        Config::set('services.threads.relevance_threshold', 0.65);

        $category = ThreadsCategory::query()->create([
            'slug' => 'outros',
            'name' => 'Outros',
            'is_active' => true,
        ]);

        $comment = $this->makeComment('Comentário de baixa relevância');

        $aiMock = $this->mock(NeuronAIService::class);
        $aiMock->shouldReceive('complete')->once()->andReturn(new AiCompletionResult(
            success: true,
            text: json_encode([
                'category_slug' => 'outros',
                'summary' => 'Baixa aderência',
                'relevance_score' => 0.40,
            ], JSON_THROW_ON_ERROR),
            provider: 'groq',
            model: 'llama',
            latencyMs: 1200,
            fallbackUsed: false,
        ));

        $service = app(ThreadsClassificationService::class);
        $classified = $service->classifyComment($comment);

        $this->assertSame('ignored', $classified->status);
        $this->assertSame($category->id, $classified->threads_category_id);
        $this->assertSame('Baixa aderência', $classified->ai_summary);
        $this->assertSame('40.00', (string) $classified->ai_relevance_score);
    }

    public function test_classification_marks_comment_as_pending_review_when_score_is_above_threshold(): void
    {
        Config::set('services.threads.relevance_threshold', 65);

        $category = ThreadsCategory::query()->create([
            'slug' => 'freela',
            'name' => 'Freela',
            'is_active' => true,
        ]);

        $comment = $this->makeComment('Comentário de alta relevância');

        $aiMock = $this->mock(NeuronAIService::class);
        $aiMock->shouldReceive('complete')->once()->andReturn(new AiCompletionResult(
            success: true,
            text: json_encode([
                'category_slug' => 'freela',
                'summary' => 'Boa oportunidade',
                'relevance_score' => 82.5,
            ], JSON_THROW_ON_ERROR),
            provider: 'groq',
            model: 'llama',
            latencyMs: 1100,
            fallbackUsed: false,
        ));

        $service = app(ThreadsClassificationService::class);
        $classified = $service->classifyComment($comment);

        $this->assertSame('pending_review', $classified->status);
        $this->assertSame($category->id, $classified->threads_category_id);
        $this->assertSame('Boa oportunidade', $classified->ai_summary);
        $this->assertSame('82.50', (string) $classified->ai_relevance_score);
    }

    public function test_classify_comments_job_processes_single_comment_on_ai_queue(): void
    {
        $this->mock(NeuronAIService::class)
            ->shouldReceive('complete')
            ->andReturn(new AiCompletionResult(
                success: true,
                text: json_encode([
                    'category_slug' => 'outros',
                    'summary' => 'Resumo',
                    'relevance_score' => 0.9,
                ], JSON_THROW_ON_ERROR),
                provider: 'groq',
                model: 'llama',
                latencyMs: 800,
                fallbackUsed: false,
            ));

        ThreadsCategory::query()->create([
            'slug' => 'outros',
            'name' => 'Outros',
            'is_active' => true,
        ]);

        $comment = $this->makeComment('Comentário A');

        $job = new ClassifyCommentsJob($comment->id);
        $this->assertSame('ai', $job->queue);

        $job->handle(app(ThreadsClassificationService::class));

        $this->assertSame('pending_review', (string) $comment->fresh()?->status);
    }

    public function test_dispatch_pending_classification_job_dispatches_comments_one_by_one(): void
    {
        Bus::fake();

        $commentA = $this->makeComment('Comentario sem IA 1');
        $commentB = $this->makeComment('Comentario sem IA 2');

        $commentB->forceFill(['ai_summary' => 'Ja classificado'])->save();

        $job = new DispatchPendingThreadsClassificationJob(batchSize: 10, spacingSeconds: 30);
        $this->assertSame('ai', $job->queue);

        $job->handle();

        Bus::assertDispatched(ClassifyCommentsJob::class, function (ClassifyCommentsJob $job) use ($commentA): bool {
            return $job->commentId === $commentA->id && $job->force === false;
        });

        Bus::assertNotDispatched(ClassifyCommentsJob::class, function (ClassifyCommentsJob $job) use ($commentB): bool {
            return $job->commentId === $commentB->id;
        });
    }

    private function makeComment(string $content): ThreadsComment
    {
        $post = ThreadsPost::query()->create([
            'external_id' => uniqid('post-', true),
        ]);

        return ThreadsComment::query()->create([
            'threads_post_id' => $post->id,
            'external_id' => uniqid('comment-', true),
            'content' => $content,
            'status' => 'pending_review',
        ]);
    }
}
