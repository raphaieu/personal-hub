<?php

namespace App\Services\Threads\Hub;

use App\Models\ThreadsComment;
use Illuminate\Database\Eloquent\Builder;

final class ThreadsPublishedQueryService
{
    public function build(
        string $publishedCategory,
        string $publishedSource,
        string $publishedSort,
    ): Builder {
        $query = ThreadsComment::query()
            ->where('is_public', true)
            ->with(['post:id,post_url,threads_source_id', 'post.source:id,label', 'category:id,name'])
            ->when($publishedCategory !== 'all', fn ($inner) => $inner->where('threads_category_id', (int) $publishedCategory))
            ->when($publishedSource !== 'all', fn ($inner) => $inner
                ->whereHas('post', fn ($postQuery) => $postQuery->where('threads_source_id', (int) $publishedSource)));

        if ($publishedSort === 'newest') {
            $query->orderByDesc('updated_at')->orderByDesc('id');
        } elseif ($publishedSort === 'relevance') {
            $query->orderByDesc('ai_relevance_score')->orderByDesc('id');
        } else {
            $query->orderByDesc('score_total')->orderByDesc('id');
        }

        return $query;
    }
}
