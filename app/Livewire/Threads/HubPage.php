<?php

namespace App\Livewire\Threads;

use App\Jobs\ClassifyCommentsJob;
use App\Jobs\DispatchPendingThreadsClassificationJob;
use App\Jobs\ScrapeThreadsKeywordJob;
use App\Jobs\ScrapeThreadsUrlJob;
use App\Models\AnalysisProfile;
use App\Models\ThreadsCategory;
use App\Models\ThreadsComment;
use App\Models\ThreadsSource;
use App\Services\Threads\Hub\ThreadsPublishedQueryService;
use App\Services\Threads\Hub\ThreadsSourcesQueryService;
use App\Services\Threads\Hub\ThreadsReviewQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class HubPage extends Component
{
    use WithPagination;

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

    #[Url(as: 'review_per_page')]
    public int $reviewPerPage = 50;

    #[Url(as: 'pub_category')]
    public string $publishedCategory = 'all';

    #[Url(as: 'pub_source')]
    public string $publishedSource = 'all';

    #[Url(as: 'pub_sort')]
    public string $publishedSort = 'score';

    #[Url(as: 'pub_per_page')]
    public int $publishedPerPage = 25;

    public string $newSourceType = 'keyword';

    public string $newSourceLabel = '';

    public string $newSourceKeyword = '';

    public string $newSourceTargetUrl = '';

    public bool $newSourceIsActive = true;

    public string $newSourceProfileId = '';

    public int $manualDispatchBatchSize = 1;

    /** @var array<int> */
    public array $selectedReviewCommentIds = [];

    /** @var array<int, string> */
    public array $sourceProfileForms = [];

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
        $sources = app(ThreadsSourcesQueryService::class)->listForHub();

        foreach ($sources as $source) {
            $sourceId = (int) $source->id;
            if (! array_key_exists($sourceId, $this->sourceProfileForms)) {
                $this->sourceProfileForms[$sourceId] = $source->analysis_profile_id !== null
                    ? (string) $source->analysis_profile_id
                    : '';
            }
        }

        $threadsProfiles = AnalysisProfile::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('channel', 'threads')
                    ->orWhereNull('channel');
            })
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);
        $defaultThreadsProfileId = AnalysisProfile::defaultThreadsProfileId();

        $reviewComments = $this->reviewCommentsQuery()->paginate($this->normalizedReviewPerPage());
        $reviewCommentIdsOnScreen = $this->reviewCommentIdsOnCurrentPage($reviewComments);
        $this->selectedReviewCommentIds = array_values(array_map(
            'intval',
            array_intersect($this->selectedReviewCommentIds, $reviewCommentIdsOnScreen)
        ));

        $sortedVisible = $reviewCommentIdsOnScreen;
        sort($sortedVisible);
        $sortedSelected = $this->normalizedSelectedReviewCommentIds();
        sort($sortedSelected);
        $reviewAllVisibleSelected = count($sortedVisible) > 0 && $sortedVisible === $sortedSelected;

        $pendingClassificationCount = ThreadsComment::query()->whereNull('ai_summary')->count();
        $aiDispatchSpacingSeconds = (int) config('services.threads.ai_dispatch_spacing_seconds', 30);

        $reviewSources = ThreadsSource::query()
            ->orderBy('label')
            ->get(['id', 'label']);

        $reviewCategories = ThreadsCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $publishedQuery = app(ThreadsPublishedQueryService::class)->build(
            publishedCategory: $this->publishedCategory,
            publishedSource: $this->publishedSource,
            publishedSort: $this->publishedSort,
        );

        $publishedComments = $publishedQuery->paginate(
            $this->normalizedPublishedPerPage(),
            ['*'],
            'publishedPage'
        );

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
            'reviewAllVisibleSelected' => $reviewAllVisibleSelected,
            'pendingClassificationCount' => $pendingClassificationCount,
            'nextClassificationBatchEstimate' => min(max(1, min(200, (int) $this->manualDispatchBatchSize)), max(0, $pendingClassificationCount)),
            'aiDispatchSpacingSeconds' => $aiDispatchSpacingSeconds,
            'tabLabels' => [
                'sources' => 'Sources',
                'review' => 'Review Analise',
                'published' => 'Publicados',
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
            'threadsProfiles' => $threadsProfiles,
            'defaultThreadsProfileId' => $defaultThreadsProfileId,
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

    public function updatedNewSourceProfileId(string $value): void
    {
        if ($value !== '' && ! ctype_digit($value)) {
            $this->newSourceProfileId = '';
        }
    }

    public function updatedReviewStatus(string $value): void
    {
        if (! in_array($value, ['all', 'pending_review', 'ignored'], true)) {
            $this->reviewStatus = 'all';
        }

        $this->resetPage();
    }

    public function updatedReviewCategory(string $value): void
    {
        if ($value === 'all') {
            $this->resetPage();
            return;
        }

        if (! ctype_digit($value)) {
            $this->reviewCategory = 'all';
        }

        $this->resetPage();
    }

    public function updatedReviewSource(string $value): void
    {
        if ($value === 'all') {
            $this->resetPage();
            return;
        }

        if (! ctype_digit($value)) {
            $this->reviewSource = 'all';
        }

        $this->resetPage();
    }

    public function updatedReviewSort(string $value): void
    {
        if (! in_array($value, ['relevance', 'newest', 'score'], true)) {
            $this->reviewSort = 'relevance';
        }

        $this->resetPage();
    }

    public function updatedReviewWithoutSummary(): void
    {
        $this->resetPage();
    }

    public function updatedReviewPerPage($value): void
    {
        $this->reviewPerPage = max(25, min(200, (int) $value));
        $this->resetPage();
    }

    public function updatedPublishedCategory(string $value): void
    {
        if ($value === 'all') {
            $this->resetPage('publishedPage');
            return;
        }

        if (! ctype_digit($value)) {
            $this->publishedCategory = 'all';
        }

        $this->resetPage('publishedPage');
    }

    public function updatedPublishedSource(string $value): void
    {
        if ($value === 'all') {
            $this->resetPage('publishedPage');
            return;
        }

        if (! ctype_digit($value)) {
            $this->publishedSource = 'all';
        }

        $this->resetPage('publishedPage');
    }

    public function updatedPublishedSort(string $value): void
    {
        if (! in_array($value, ['score', 'newest', 'relevance'], true)) {
            $this->publishedSort = 'score';
        }

        $this->resetPage('publishedPage');
    }

    public function updatedPublishedPerPage($value): void
    {
        $this->publishedPerPage = max(10, min(100, (int) $value));
        $this->resetPage('publishedPage');
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
            'newSourceProfileId' => ['nullable', 'integer', Rule::exists('analysis_profiles', 'id')],
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
            'analysis_profile_id' => $this->resolvedProfileId(
                $data['newSourceProfileId'] ?? null,
                true
            ),
        ]);

        $this->reset(['newSourceLabel', 'newSourceKeyword', 'newSourceTargetUrl', 'newSourceProfileId']);
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

    public function saveSourceProfile(int $sourceId): void
    {
        $source = ThreadsSource::query()->findOrFail($sourceId);
        $rawProfileId = $this->sourceProfileForms[$sourceId] ?? '';
        $profileId = $this->resolvedProfileId($rawProfileId, false);

        $source->forceFill([
            'analysis_profile_id' => $profileId,
        ])->save();

        $this->sourceProfileForms[$sourceId] = $profileId !== null ? (string) $profileId : '';

        session()->flash(
            'threads_hub_notice',
            $profileId === null
                ? 'Source atualizada para usar fallback do profile padrão quando disponível.'
                : 'Profile da source atualizado.'
        );
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
        $spacing = (int) config('services.threads.ai_dispatch_spacing_seconds', 30);
        $pending = ThreadsComment::query()->whereNull('ai_summary')->count();
        $queued = min($batchSize, $pending);

        DispatchPendingThreadsClassificationJob::dispatch(
            batchSize: $batchSize,
            spacingSeconds: $spacing,
            force: false,
        );

        $remaining = max(0, $pending - $queued);

        session()->flash(
            'threads_hub_notice',
            $pending < 1
                ? 'Nenhum comentario pendente de classificacao (sem resumo IA).'
                : "Fila IA: {$queued} classificacao(oes) enfileirada(s) neste disparo (batch {$batchSize}, espaco {$spacing}s). Pendente na base: {$pending}. Estimativa apos este disparo: {$remaining} restante(s)."
        );
    }

    public function toggleSelectAllReviewOnPage(): void
    {
        $visibleIds = $this->reviewCommentIdsOnCurrentPage(
            $this->reviewCommentsQuery()->paginate($this->normalizedReviewPerPage())
        );
        sort($visibleIds);

        $current = $this->normalizedSelectedReviewCommentIds();
        sort($current);

        if ($visibleIds === $current && count($visibleIds) > 0) {
            $this->selectedReviewCommentIds = [];
        } else {
            $this->selectedReviewCommentIds = $visibleIds;
        }
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

    private function normalizedReviewPerPage(): int
    {
        return max(25, min(200, (int) $this->reviewPerPage));
    }

    private function normalizedPublishedPerPage(): int
    {
        return max(10, min(100, (int) $this->publishedPerPage));
    }

    private function batchNotice(int $affected, string $suffix): string
    {
        if ($affected < 1) {
            return 'Nenhum comentario selecionado.';
        }

        return "{$affected} {$suffix}";
    }

    private function resolvedProfileId(mixed $profileValue, bool $fallbackToDefault): ?int
    {
        $profileId = null;

        if (is_string($profileValue) && ctype_digit($profileValue)) {
            $profileId = (int) $profileValue;
        } elseif (is_int($profileValue) && $profileValue > 0) {
            $profileId = $profileValue;
        }

        if ($profileId !== null) {
            $exists = AnalysisProfile::query()
                ->whereKey($profileId)
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->where('channel', 'threads')
                        ->orWhereNull('channel');
                })
                ->exists();

            if ($exists) {
                return $profileId;
            }
        }

        return $fallbackToDefault ? AnalysisProfile::defaultThreadsProfileId() : null;
    }

    /**
     * Lista de review com os mesmos filtros/ordenacao da tabela (sem limite).
     */
    private function reviewCommentsQuery()
    {
        $query = app(ThreadsReviewQueryService::class)->build(
            reviewStatus: $this->reviewStatus,
            reviewCategory: $this->reviewCategory,
            reviewSource: $this->reviewSource,
            reviewWithoutSummary: $this->reviewWithoutSummary,
            reviewSort: $this->reviewSort,
        );

        if (! $query instanceof Builder) {
            return ThreadsComment::query();
        }

        return $query;
    }

    /**
     * @param  LengthAwarePaginator<int, ThreadsComment>  $comments
     * @return array<int>
     */
    private function reviewCommentIdsOnCurrentPage(LengthAwarePaginator $comments): array
    {
        return $comments->getCollection()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function render()
    {
        return view('livewire.threads.hub-page', $this->viewData())
            ->layout('layouts.app');
    }
}
