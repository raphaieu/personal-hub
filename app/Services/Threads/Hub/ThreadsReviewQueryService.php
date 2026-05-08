<?php

namespace App\Services\Threads\Hub;

use App\Models\ThreadsComment;
use Illuminate\Database\Eloquent\Builder;

final class ThreadsReviewQueryService
{
    public function build(
        string $reviewStatus,
        string $reviewCategory,
        string $reviewSource,
        bool $reviewWithoutSummary,
        string $reviewSort,
    ): Builder {
        $query = ThreadsComment::query()
            ->with(['post:id,post_url,threads_source_id', 'post.source:id,label', 'category:id,name'])
            ->orderByRaw('CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END', ['pending_review', 'ignored'])
            ->when($reviewStatus !== 'all', fn ($q) => $q->where('status', $reviewStatus))
            ->when($reviewCategory !== 'all', fn ($q) => $q->where('threads_category_id', (int) $reviewCategory))
            ->when($reviewSource !== 'all', fn ($q) => $q
                ->whereHas('post', fn ($postQuery) => $postQuery->where('threads_source_id', (int) $reviewSource)))
            ->when($reviewWithoutSummary, fn ($q) => $q->whereNull('ai_summary'));

        if ($reviewSort === 'newest') {
            $query->orderByDesc('created_at')->orderByDesc('id');
        } elseif ($reviewSort === 'score') {
            $query->orderByDesc('score_total')->orderByDesc('id');
        } else {
            $query->orderByDesc('ai_relevance_score')->orderByDesc('id');
        }

        return $query;
    }
}
