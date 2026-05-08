<?php

namespace App\Services\Threads\Hub;

use App\Models\ThreadsSource;
use Illuminate\Support\Collection;

final class ThreadsSourcesQueryService
{
    /**
     * @return Collection<int, ThreadsSource>
     */
    public function listForHub(): Collection
    {
        return ThreadsSource::query()
            ->with('analysisProfile:id,slug,name')
            ->latest('updated_at')
            ->get([
                'id',
                'type',
                'label',
                'keyword',
                'target_url',
                'is_active',
                'last_scraped_at',
                'analysis_profile_id',
            ]);
    }
}
