<?php

namespace App\Http\Controllers;

use App\Models\ThreadsCategory;
use App\Models\ThreadsComment;
use App\Models\ThreadsSource;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ThreadsOpportunitiesController extends Controller
{
    public function __invoke(Request $request): View
    {
        $categoryId = $request->query('category');
        $sourceId = $request->query('source');
        $sort = $request->string('sort')->toString() ?: 'relevance';
        $q = $request->query('q');

        $query = ThreadsComment::query()
            ->where('is_public', true)
            ->with([
                'category:id,name,slug',
                'post:id,post_url,threads_source_id',
                'post.source:id,label',
            ]);

        if (is_string($categoryId) && ctype_digit($categoryId)) {
            $query->where('threads_category_id', (int) $categoryId);
        }

        if (is_string($sourceId) && ctype_digit($sourceId)) {
            $query->whereHas('post', fn ($postQuery) => $postQuery->where('threads_source_id', (int) $sourceId));
        }

        if (filled($q) && is_string($q)) {
            $term = trim($q);
            $pattern = '%'.$term.'%';
            $query->where(function ($sub) use ($pattern) {
                $sub->where('ai_summary', 'like', $pattern)
                    ->orWhere('content', 'like', $pattern);
            });
        }

        match ($sort) {
            'votes' => $query->orderByDesc('score_total')->orderByDesc('id'),
            'newest' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            default => $query->orderByDesc('ai_relevance_score')->orderByDesc('id'),
        };

        $comments = $query->paginate(20)->withQueryString();

        $categories = ThreadsCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $sources = ThreadsSource::query()
            ->orderBy('label')
            ->get(['id', 'label']);

        return view('threads.opportunities', [
            'comments' => $comments,
            'categories' => $categories,
            'sources' => $sources,
            'filters' => [
                'category' => is_string($categoryId) ? $categoryId : null,
                'source' => is_string($sourceId) ? $sourceId : null,
                'sort' => $sort,
                'q' => is_string($q) ? $q : null,
            ],
        ]);
    }
}
