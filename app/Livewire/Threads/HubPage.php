<?php

namespace App\Livewire\Threads;

use App\Jobs\ScrapeThreadsKeywordJob;
use App\Jobs\ScrapeThreadsUrlJob;
use App\Models\ThreadsSource;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

final class HubPage extends Component
{
    #[Url(as: 'tab')]
    public string $currentTab = 'sources';

    public string $newSourceType = 'keyword';

    public string $newSourceLabel = '';

    public string $newSourceKeyword = '';

    public string $newSourceTargetUrl = '';

    public bool $newSourceIsActive = true;

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

        return [
            'sources' => $sources,
            'tabLabels' => [
                'sources' => 'Sources',
                'review' => 'Review',
                'published' => 'Published',
            ],
            'createTypes' => [
                'keyword' => 'Keyword',
                'url' => 'URL',
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

    public function render()
    {
        return view('livewire.threads.hub-page', $this->viewData())
            ->layout('layouts.app');
    }
}
