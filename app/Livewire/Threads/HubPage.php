<?php

namespace App\Livewire\Threads;

use App\Jobs\ClassifyCommentsJob;
use App\Jobs\DispatchPendingThreadsClassificationJob;
use App\Jobs\ScrapeThreadsKeywordJob;
use App\Jobs\ScrapeThreadsUrlJob;
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

    public string $newSourceType = 'keyword';

    public string $newSourceLabel = '';

    public string $newSourceKeyword = '';

    public string $newSourceTargetUrl = '';

    public bool $newSourceIsActive = true;

    public int $manualDispatchBatchSize = 1;

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
            ->orderByDesc('ai_relevance_score')
            ->orderByDesc('id');

        if ($this->reviewStatus !== 'all') {
            $reviewCommentsQuery->where('status', $this->reviewStatus);
        }

        $reviewComments = $reviewCommentsQuery->limit(100)->get();

        return [
            'sources' => $sources,
            'reviewComments' => $reviewComments,
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

    public function render()
    {
        return view('livewire.threads.hub-page', $this->viewData())
            ->layout('layouts.app');
    }
}
