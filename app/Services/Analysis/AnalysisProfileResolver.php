<?php

namespace App\Services\Analysis;

use App\Models\AnalysisProfile;
use App\Models\MonitoredSource;
use App\Models\ThreadsSource;

final class AnalysisProfileResolver
{
    public function resolveForThreadsSource(?ThreadsSource $source): ?AnalysisProfile
    {
        $profile = $this->resolveSourceProfile($source?->analysisProfile);
        if ($profile !== null) {
            return $profile;
        }

        return AnalysisProfile::query()
            ->where('slug', AnalysisProfile::THREADS_OPPORTUNITIES_SLUG)
            ->where('is_active', true)
            ->first();
    }

    public function resolveForMonitoredSource(?MonitoredSource $source): ?AnalysisProfile
    {
        return $this->resolveSourceProfile($source?->analysisProfile);
    }

    public function resolveById(?int $profileId): ?AnalysisProfile
    {
        if ($profileId === null || $profileId < 1) {
            return null;
        }

        return AnalysisProfile::query()
            ->whereKey($profileId)
            ->where('is_active', true)
            ->first();
    }

    private function resolveSourceProfile(?AnalysisProfile $profile): ?AnalysisProfile
    {
        if ($profile === null || ! $profile->is_active) {
            return null;
        }

        return $profile;
    }
}
