<?php

namespace App\Livewire\Threads;

use App\Jobs\ClassifyCommentsJob;
use App\Jobs\DispatchPendingThreadsClassificationJob;
use App\Jobs\ScrapeThreadsKeywordJob;
use App\Jobs\ScrapeThreadsUrlJob;
use App\Models\ThreadsCategory;
use App\Models\ThreadsComment;
use App\Models\ThreadsSource;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

final class HubPage extends Component
{
    #[Url(as: 'tab')]
    public string $currentTab = 'sources';

    #[Url(as: 'review_status')]
    public string $reviewStatus = 'all';

    #[Url(as: 'review_category')]
    public string $reviewCategory = 'all';

    #[Url(as: 'review_source')]
    public string $reviewSource = 'all';

    #[Url(as: 'review_without_summary')]
    public bool $reviewWithoutSummary = false;

    #[Url(as: 'review_sort')]
    public string $reviewSort = 'relevance';

    #[Url(as: 'pub_category')]
    public string $publishedCategory = 'all';

    #[Url(as: 'pub_source')]
    public string $publishedSource = 'all';

    #[Url(as: 'pub_sort')]
    public string $publishedSort = 'score';

    public string $newSourceType = 'keyword';

    public string $newSourceLabel = '';

    public string $newSourceKeyword = '';

    public string $newSourceTargetUrl = '';

    public bool $newSourceIsActive = true;

    public int $manualDispatchBatchSize = 1;

    /** @var array<int> */
    public array $selectedReviewCommentIds = [];

    /**
     * Estado de edição rápida por comentário publicado (somente ids visíveis na lista).
     *
     * @var array<int, array{ai_summary: string, threads_category_id: string, is_featured: bool}>
     */
    public array $publishedForms = [];

    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        $sources = ThreadsSource::query()
            ->latest('updated_at')
            ->get([
                'id',
                'type',
                'label',
                'keyword',
                'target_url',
                'is_active',
                'last_scraped_at',
            ]);

        $reviewCommentsQuery = ThreadsComment::query()
            ->with(['post:id,post_url,threads_source_id', 'post.source:id,label', 'category:id,name'])
            ->orderByRaw('CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END', ['pending_review', 'ignored'])
            ->when($this->reviewStatus !== 'all', fn ($query) => $query->where('status', $this->reviewStatus))
            ->when($this->reviewCategory !== 'all', fn ($query) => $query->where('threads_category_id', (int) $this->reviewCategory))
            ->when($this->reviewSource !== 'all', fn ($query) => $query
                ->whereHas('post', fn ($postQuery) => $postQuery->where('threads_source_id', (int) $this->reviewSource)))
            ->when($this->reviewWithoutSummary, fn ($query) => $query->whereNull('ai_summary'));

        if ($this->reviewSort === 'newest') {
            $reviewCommentsQuery->orderByDesc('created_at')->orderByDesc('id');
        } elseif ($this->reviewSort === 'score') {
            $reviewCommentsQuery->orderByDesc('score_total')->orderByDesc('id');
        } else {
            $reviewCommentsQuery->orderByDesc('ai_relevance_score')->orderByDesc('id');
        }

        $reviewComments = $reviewCommentsQuery->limit(100)->get();
        $reviewCommentIdsOnScreen = $reviewComments->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $this->selectedReviewCommentIds = array_values(array_map(
            'intval',
            array_intersect($this->selectedReviewCommentIds, $reviewCommentIdsOnScreen)
        ));

        $reviewSources = ThreadsSource::query()
            ->orderBy('label')
            ->get(['id', 'label']);

        $reviewCategories = ThreadsCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $publishedQuery = ThreadsComment::query()
            ->where('is_public', true)
            ->with(['post:id,post_url,threads_source_id', 'post.source:id,label', 'category:id,name'])
            ->when($this->publishedCategory !== 'all', fn ($query) => $query->where('threads_category_id', (int) $this->publishedCategory))
            ->when($this->publishedSource !== 'all', fn ($query) => $query
                ->whereHas('post', fn ($postQuery) => $postQuery->where('threads_source_id', (int) $this->publishedSource)));

        if ($this->publishedSort === 'newest') {
            $publishedQuery->orderByDesc('updated_at')->orderByDesc('id');
        } elseif ($this->publishedSort === 'relevance') {
            $publishedQuery->orderByDesc('ai_relevance_score')->orderByDesc('id');
        } else {
            $publishedQuery->orderByDesc('score_total')->orderByDesc('id');
        }

        $publishedComments = $publishedQuery->limit(100)->get();

        foreach ($publishedComments as $comment) {
            $cid = $comment->id;
            if (! array_key_exists($cid, $this->publishedForms)) {
                $this->publishedForms[$cid] = [
                    'ai_summary' => (string) ($comment->ai_summary ?? ''),
                    'threads_category_id' => $comment->threads_category_id !== null ? (string) $comment->threads_category_id : '',
                    'is_featured' => (bool) $comment->is_featured,
                ];
            }
        }

        return [
            'sources' => $sources,
            'reviewComments' => $reviewComments,
            'publishedComments' => $publishedComments,
            'publishedCategoryOptions' => $reviewCategories,
            'publishedSourceOptions' => $reviewSources,
            'publishedSortOptions' => [
                'score' => 'Score',
                'newest' => 'Atualizado',
                'relevance' => 'Relevancia IA',
            ],
            'reviewSelectedCount' => count($this->selectedReviewCommentIds),
            'tabLabels' => [
                'sources' => 'Sources',
                'review' => 'Review',
                'published' => 'Published',
            ],
            'createTypes' => [
                'keyword' => 'Keyword',
                'url' => 'URL',
            ],
            'reviewStatusOptions' => [
                'all' => 'Todos',
                'pending_review' => 'Pending Review',
                'ignored' => 'Ignored',
            ],
            'reviewCategoryOptions' => $reviewCategories,
            'reviewSourceOptions' => $reviewSources,
            'reviewSortOptions' => [
                'relevance' => 'Relevancia IA',
                'newest' => 'Mais novo',
                'score' => 'Score',
            ],
        ];
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['sources', 'review', 'published'], true)) {
            return;
        }

        $this->currentTab = $tab;
    }

    public function updatedNewSourceType(string $value): void
    {
        if (! in_array($value, ['keyword', 'url'], true)) {
            $this->newSourceType = 'keyword';
        }

        // Limpa estado e erros do alvo oposto para evitar validação "atrasada"
        // quando o usuário alterna rapidamente entre keyword e URL no formulário.
        $this->resetValidation(['newSourceKeyword', 'newSourceTargetUrl']);
        $this->newSourceKeyword = '';
        $this->newSourceTargetUrl = '';
    }

    public function updatedReviewStatus(string $value): void
    {
        if (! in_array($value, ['all', 'pending_review', 'ignored'], true)) {
            $this->reviewStatus = 'all';
        }
    }

    public function updatedReviewCategory(string $value): void
    {
        if ($value === 'all') {
            return;
        }

        if (! ctype_digit($value)) {
            $this->reviewCategory = 'all';
        }
    }

    public function updatedReviewSource(string $value): void
    {
        if ($value === 'all') {
            return;
        }

        if (! ctype_digit($value)) {
            $this->reviewSource = 'all';
        }
    }

    public function updatedReviewSort(string $value): void
    {
        if (! in_array($value, ['relevance', 'newest', 'score'], true)) {
            $this->reviewSort = 'relevance';
        }
    }

    public function updatedPublishedCategory(string $value): void
    {
        if ($value === 'all') {
            return;
        }

        if (! ctype_digit($value)) {
            $this->publishedCategory = 'all';
        }
    }

    public function updatedPublishedSource(string $value): void
    {
        if ($value === 'all') {
            return;
        }

        if (! ctype_digit($value)) {
            $this->publishedSource = 'all';
        }
    }

    public function updatedPublishedSort(string $value): void
    {
        if (! in_array($value, ['score', 'newest', 'relevance'], true)) {
            $this->publishedSort = 'score';
        }
    }

    public function updatedSelectedReviewCommentIds(): void
    {
        $this->selectedReviewCommentIds = $this->normalizedSelectedReviewCommentIds();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'newSourceType' => ['required', Rule::in(['keyword', 'url'])],
            'newSourceLabel' => ['required', 'string', 'min:3', 'max:120'],
            'newSourceKeyword' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->newSourceType === 'keyword')],
            'newSourceTargetUrl' => ['nullable', 'string', 'max:2048', Rule::requiredIf($this->newSourceType === 'url')],
            'newSourceIsActive' => ['boolean'],
        ];
    }

    public function createSource(): void
    {
        $data = $this->validate();

        ThreadsSource::query()->create([
            'type' => $data['newSourceType'],
            'label' => trim((string) $data['newSourceLabel']),
            'keyword' => $data['newSourceType'] === 'keyword' ? trim((string) $data['newSourceKeyword']) : null,
            'target_url' => $data['newSourceType'] === 'url' ? trim((string) $data['newSourceTargetUrl']) : null,
            'is_active' => (bool) $data['newSourceIsActive'],
        ]);

        $this->reset(['newSourceLabel', 'newSourceKeyword', 'newSourceTargetUrl']);
        $this->newSourceType = 'keyword';
        $this->newSourceIsActive = true;
        session()->flash('threads_hub_notice', 'Source criada com sucesso.');
    }

    public function toggleSource(int $sourceId): void
    {
        $source = ThreadsSource::query()->findOrFail($sourceId);
        $source->forceFill(['is_active' => ! $source->is_active])->save();

        session()->flash('threads_hub_notice', 'Status da source atualizado.');
    }

    public function scrapeNow(int $sourceId): void
    {
        $source = ThreadsSource::query()->findOrFail($sourceId);

        if ($source->type === 'keyword' && filled($source->keyword)) {
            $knownPostIds = $source->posts()
                ->orderByDesc('id')
                ->limit(500)
                ->pluck('external_id')
                ->filter()
                ->values()
                ->all();

            ScrapeThreadsKeywordJob::dispatch(
                keyword: (string) $source->keyword,
                maxPosts: (int) env('THREADS_MAX_POSTS_PER_KEYWORD', 20),
                includeComments: false,
                knownPostIds: $knownPostIds,
                onlyNew: true,
                knownStreakStop: (int) env('THREADS_KNOWN_STREAK_STOP', 20),
                threadsSourceId: $source->id,
            );

            session()->flash('threads_hub_notice', 'Scrape por keyword enfileirado.');

            return;
        }

        if ($source->type === 'url' && filled($source->target_url)) {
            ScrapeThreadsUrlJob::dispatch((string) $source->target_url, $source->id);
            session()->flash('threads_hub_notice', 'Scrape por URL enfileirado.');

            return;
        }

        session()->flash('threads_hub_notice', 'Source sem alvo válido para scrape.');
    }

    public function dispatchPendingClassification(): void
    {
        $batchSize = max(1, min(200, (int) $this->manualDispatchBatchSize));

        DispatchPendingThreadsClassificationJob::dispatch(
            batchSize: $batchSize,
            spacingSeconds: (int) env('THREADS_AI_DISPATCH_SPACING_SECONDS', 30),
            force: false,
        );

        session()->flash('threads_hub_notice', "Classificacao pendente enfileirada (batch: {$batchSize}).");
    }

    public function reclassifyComment(int $commentId): void
    {
        ClassifyCommentsJob::dispatch($commentId, true);
        session()->flash('threads_hub_notice', 'Reclassificacao manual enfileirada.');
    }

    public function batchMoveSelectedToPendingReview(): void
    {
        $affected = ThreadsComment::query()
            ->whereIn('id', $this->normalizedSelectedReviewCommentIds())
            ->update(['status' => 'pending_review']);

        $this->selectedReviewCommentIds = [];
        session()->flash('threads_hub_notice', $this->batchNotice($affected, 'comentario(s) movido(s) para pending_review.'));
    }

    public function batchIgnoreSelected(): void
    {
        $affected = ThreadsComment::query()
            ->whereIn('id', $this->normalizedSelectedReviewCommentIds())
            ->update(['status' => 'ignored']);

        $this->selectedReviewCommentIds = [];
        session()->flash('threads_hub_notice', $this->batchNotice($affected, 'comentario(s) marcado(s) como ignored.'));
    }

    public function batchPublishSelected(): void
    {
        $affected = ThreadsComment::query()
            ->whereIn('id', $this->normalizedSelectedReviewCommentIds())
            ->update(['is_public' => true]);

        $this->selectedReviewCommentIds = [];
        session()->flash('threads_hub_notice', $this->batchNotice($affected, 'comentario(s) publicado(s).'));
    }

    public function batchUnpublishSelected(): void
    {
        $affected = ThreadsComment::query()
            ->whereIn('id', $this->normalizedSelectedReviewCommentIds())
            ->update(['is_public' => false]);

        $this->selectedReviewCommentIds = [];
        session()->flash('threads_hub_notice', $this->batchNotice($affected, 'comentario(s) despublicado(s).'));
    }

    public function batchReclassifySelected(): void
    {
        $ids = $this->normalizedSelectedReviewCommentIds();

        foreach ($ids as $commentId) {
            ClassifyCommentsJob::dispatch($commentId, true);
        }

        $this->selectedReviewCommentIds = [];
        session()->flash('threads_hub_notice', $this->batchNotice(count($ids), 'comentario(s) enfileirado(s) para reclassificacao.'));
    }

    public function moveCommentToPendingReview(int $commentId): void
    {
        $comment = ThreadsComment::query()->findOrFail($commentId);
        $comment->forceFill(['status' => 'pending_review'])->save();

        session()->flash('threads_hub_notice', 'Comentario movido para pending_review.');
    }

    public function ignoreComment(int $commentId): void
    {
        $comment = ThreadsComment::query()->findOrFail($commentId);
        $comment->forceFill(['status' => 'ignored'])->save();

        session()->flash('threads_hub_notice', 'Comentario marcado como ignored.');
    }

    public function toggleCommentPublic(int $commentId): void
    {
        $comment = ThreadsComment::query()->findOrFail($commentId);
        $comment->forceFill(['is_public' => ! $comment->is_public])->save();

        session()->flash('threads_hub_notice', 'Visibilidade publica atualizada.');
    }

    public function savePublishedComment(int $commentId): void
    {
        ThreadsComment::query()->where('is_public', true)->findOrFail($commentId);

        $this->validate([
            "publishedForms.$commentId.ai_summary" => ['nullable', 'string', 'max:10000'],
            "publishedForms.$commentId.is_featured" => ['boolean'],
        ]);

        $form = $this->publishedForms[$commentId] ?? null;
        if ($form === null) {
            return;
        }

        $categoryRaw = $form['threads_category_id'] ?? '';
        $categoryId = ($categoryRaw === '' || $categoryRaw === null) ? null : (int) $categoryRaw;

        if ($categoryId !== null && ! ThreadsCategory::query()->whereKey($categoryId)->exists()) {
            $this->addError(
                'publishedForms.'.$commentId.'.threads_category_id',
                'Categoria invalida.'
            );

            return;
        }

        ThreadsComment::query()->whereKey($commentId)->where('is_public', true)->update([
            'ai_summary' => filled($form['ai_summary'] ?? null) ? trim((string) $form['ai_summary']) : null,
            'threads_category_id' => $categoryId,
            'is_featured' => (bool) ($form['is_featured'] ?? false),
        ]);

        session()->flash('threads_hub_notice', 'Publicacao atualizada.');
    }

    public function unpublishPublishedComment(int $commentId): void
    {
        $comment = ThreadsComment::query()->where('is_public', true)->findOrFail($commentId);
        $comment->forceFill(['is_public' => false])->save();

        unset($this->publishedForms[$commentId]);

        session()->flash('threads_hub_notice', 'Comentario despublicado.');
    }

    /**
     * @return array<int>
     */
    private function normalizedSelectedReviewCommentIds(): array
    {
        return array_values(array_unique(array_map('intval', array_filter(
            $this->selectedReviewCommentIds,
            static fn ($id): bool => is_numeric($id) && (int) $id > 0
        ))));
    }

    private function batchNotice(int $affected, string $suffix): string
    {
        if ($affected < 1) {
            return 'Nenhum comentario selecionado.';
        }

        return "{$affected} {$suffix}";
    }

    public function render()
    {
        return view('livewire.threads.hub-page', $this->viewData())
            ->layout('layouts.app');
    }
}
