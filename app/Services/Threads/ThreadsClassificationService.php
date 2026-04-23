<?php

namespace App\Services\Threads;

use App\Enums\AiTask;
use App\Models\ThreadsCategory;
use App\Models\ThreadsComment;
use App\Services\NeuronAIService;
use Illuminate\Support\Arr;
use RuntimeException;

final class ThreadsClassificationService
{
    public function __construct(
        private readonly NeuronAIService $aiService,
    ) {}

    public function classifyComment(ThreadsComment $comment): ThreadsComment
    {
        $prompt = $this->buildPrompt($comment);
        $completion = $this->aiService->complete(
            userPrompt: $prompt,
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

        $categorySlug = $this->resolveCategorySlug(Arr::get($payload, 'category_slug'));
        $summary = $this->stringOrNull(Arr::get($payload, 'summary'));
        $normalizedScore = $this->normalizeScore(Arr::get($payload, 'relevance_score'));
        $threshold = $this->normalizedThreshold();
        $status = $normalizedScore < $threshold ? 'ignored' : 'pending_review';
        $categoryId = null;

        if ($categorySlug !== null) {
            $categoryId = ThreadsCategory::query()->where('slug', $categorySlug)->value('id');
        }

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
            ],
            'status' => $status,
        ])->save();

        return $comment->refresh();
    }

    private function buildPrompt(ThreadsComment $comment): string
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

    private function normalizeScore(mixed $score): float
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

    private function normalizedThreshold(): float
    {
        $threshold = (float) config('services.threads.relevance_threshold', 0.65);
        if ($threshold <= 1.0) {
            $threshold *= 100;
        }

        return max(0.0, min(100.0, round($threshold, 2)));
    }

    private function resolveCategorySlug(mixed $slug): ?string
    {
        $value = $this->stringOrNull($slug);
        if ($value === null) {
            return null;
        }

        $allowed = ['emprego-fixo', 'temporario', 'freela', 'renda-extra', 'outros'];

        return in_array($value, $allowed, true) ? $value : 'outros';
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
