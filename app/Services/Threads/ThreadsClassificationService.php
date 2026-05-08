<?php

namespace App\Services\Threads;

use App\Data\Analysis\AnalysisExecutionInput;
use App\Enums\AiTask;
use App\Models\AnalysisProfile;
use App\Models\ThreadsCategory;
use App\Models\ThreadsComment;
use App\Services\Analysis\Adapters\ThreadsCommentToNormalizedContentAdapter;
use App\Services\Analysis\AnalysisExecutionService;
use App\Services\Analysis\AnalysisProfileResolver;
use App\Services\NeuronAIService;
use Illuminate\Support\Arr;
use RuntimeException;

final class ThreadsClassificationService
{
    public function __construct(
        private readonly AnalysisExecutionService $analysisExecutionService,
        private readonly AnalysisProfileResolver $analysisProfileResolver,
        private readonly ThreadsCommentToNormalizedContentAdapter $threadsAdapter,
        private readonly NeuronAIService $aiService,
    ) {}

    public function classifyComment(ThreadsComment $comment): ThreadsComment
    {
        $comment->loadMissing('post.source');
        $profile = $this->analysisProfileResolver->resolveForThreadsSource($comment->post?->source);
        if ($profile === null) {
            return $this->classifyLegacy($comment);
        }

        $analysisResult = $this->analysisExecutionService->execute(
            new AnalysisExecutionInput(
                item: $this->threadsAdapter->fromComment($comment),
                profile: $profile,
            )
        );

        $categorySlug = $this->resolveCategorySlug($analysisResult->category, $profile);
        $summary = $analysisResult->summary;
        $normalizedScore = $this->normalizeScore($analysisResult->relevanceScore);
        $threshold = $this->normalizedThreshold($profile);
        $status = $normalizedScore < $threshold ? 'ignored' : 'pending_review';
        $categoryId = $this->resolveCategoryId($categorySlug, $profile);

        $comment->forceFill([
            'threads_category_id' => $categoryId,
            'ai_relevance_score' => $normalizedScore,
            'ai_summary' => $summary,
            'ai_meta' => [
                ...$analysisResult->providerMeta,
                'threshold' => $threshold,
                'category_slug' => $categorySlug,
                'analysis_status' => $analysisResult->status,
                'raw_normalized' => $analysisResult->rawNormalized,
            ],
            'status' => $status,
        ])->save();

        return $comment->refresh();
    }

    private function classifyLegacy(ThreadsComment $comment): ThreadsComment
    {
        $completion = $this->aiService->complete(
            userPrompt: $this->buildLegacyPrompt($comment),
            task: AiTask::ThreadsOpportunityClassification,
            expectJson: true,
        );

        if (! $completion->success) {
            throw new RuntimeException($completion->errorDetail ?? 'Falha na classificação de comentário do Threads.');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($completion->text, true);
        if (! is_array($payload)) {
            throw new RuntimeException('Classificação do Threads retornou JSON inválido.');
        }

        $categorySlug = $this->resolveCategorySlug(Arr::get($payload, 'category_slug'), null);
        $summary = $this->stringOrNull(Arr::get($payload, 'summary'));
        $normalizedScore = $this->normalizeScore(Arr::get($payload, 'relevance_score'));
        $threshold = $this->normalizedThreshold(null);
        $status = $normalizedScore < $threshold ? 'ignored' : 'pending_review';
        $categoryId = $this->resolveCategoryId($categorySlug, null);

        $comment->forceFill([
            'threads_category_id' => $categoryId,
            'ai_relevance_score' => $normalizedScore,
            'ai_summary' => $summary,
            'ai_meta' => [
                'provider' => $completion->provider,
                'model' => $completion->model,
                'latency_ms' => $completion->latencyMs,
                'fallback_used' => $completion->fallbackUsed,
                'threshold' => $threshold,
                'category_slug' => $categorySlug,
                'analysis_profile_slug' => 'legacy-fallback',
            ],
            'status' => $status,
        ])->save();

        return $comment->refresh();
    }

    private function buildLegacyPrompt(ThreadsComment $comment): string
    {
        return implode("\n", [
            'Classifique este comentário de oportunidade de trabalho/freela.',
            'Retorne somente JSON válido.',
            '',
            'Comentario:',
            (string) ($comment->content ?? ''),
            '',
            'Autor:',
            (string) ($comment->author_handle ?? ''),
        ]);
    }

    private function normalizeScore(float|int|string|null $score): float
    {
        if (! is_numeric($score)) {
            return 0.0;
        }

        $value = (float) $score;
        if ($value <= 1.0) {
            $value *= 100;
        }

        return max(0.0, min(100.0, round($value, 2)));
    }

    private function normalizedThreshold(?AnalysisProfile $profile): float
    {
        $threshold = is_numeric($profile?->score_threshold)
            ? (float) $profile->score_threshold
            : (float) config('services.threads.relevance_threshold', 0.65);

        if ($threshold <= 1.0) {
            $threshold *= 100;
        }

        return max(0.0, min(100.0, round($threshold, 2)));
    }

    private function resolveCategorySlug(mixed $slug, ?AnalysisProfile $profile): ?string
    {
        $value = $this->stringOrNull($slug);
        if ($value === null) {
            return null;
        }

        $allowed = is_array($profile?->allowed_categories) && $profile->allowed_categories !== []
            ? array_values(array_filter($profile->allowed_categories, 'is_string'))
            : AnalysisProfile::THREADS_ALLOWED_CATEGORIES;

        return in_array($value, $allowed, true) ? $value : 'outros';
    }

    private function resolveCategoryId(?string $categorySlug, ?AnalysisProfile $profile): ?int
    {
        if ($categorySlug === null) {
            return null;
        }

        return ThreadsCategory::query()
            ->where('slug', $categorySlug)
            ->when(
                $profile !== null,
                fn ($query) => $query
                    ->where(function ($inner) use ($profile): void {
                        $inner->where('analysis_profile_id', $profile->id)
                            ->orWhereNull('analysis_profile_id');
                    })
                    ->orderByRaw('CASE WHEN analysis_profile_id = ? THEN 0 ELSE 1 END', [$profile->id])
            )
            ->value('id');
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
