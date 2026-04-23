<?php

namespace App\Livewire\Threads;

use App\Models\ThreadsSource;
use Livewire\Attributes\Url;
use Livewire\Component;

final class HubPage extends Component
{
    #[Url(as: 'tab')]
    public string $currentTab = 'sources';

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
        ];
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['sources', 'review', 'published'], true)) {
            return;
        }

        $this->currentTab = $tab;
    }

    public function render()
    {
        return view('livewire.threads.hub-page', $this->viewData())
            ->layout('layouts.app');
    }
}
