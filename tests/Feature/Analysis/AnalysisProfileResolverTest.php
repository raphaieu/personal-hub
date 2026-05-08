<?php

namespace Tests\Feature\Analysis;

use App\Models\AnalysisProfile;
use App\Models\MonitoredSource;
use App\Models\ThreadsSource;
use App\Services\Analysis\AnalysisProfileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnalysisProfileResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_for_threads_source_falls_back_to_default_profile(): void
    {
        $defaultProfile = AnalysisProfile::query()->firstOrCreate([
            'slug' => AnalysisProfile::THREADS_OPPORTUNITIES_SLUG,
        ], [
            'name' => 'Threads Opportunities',
            'analysis_type' => 'classification',
            'system_prompt' => 'prompt',
            'is_active' => true,
        ]);

        $source = ThreadsSource::query()->create([
            'type' => 'keyword',
            'label' => 'Source sem profile',
            'keyword' => 'laravel',
            'is_active' => true,
        ]);

        $resolved = app(AnalysisProfileResolver::class)->resolveForThreadsSource($source);

        $this->assertNotNull($resolved);
        $this->assertSame($defaultProfile->id, $resolved?->id);
    }

    public function test_resolve_for_monitored_source_uses_explicit_profile(): void
    {
        $profile = AnalysisProfile::query()->create([
            'slug' => 'whatsapp-triage',
            'name' => 'WhatsApp Triage',
            'channel' => 'whatsapp',
            'analysis_type' => 'classification',
            'system_prompt' => 'prompt whatsapp',
            'is_active' => true,
        ]);

        $source = MonitoredSource::query()->create([
            'kind' => 'contact',
            'identifier' => '5511999999999@s.whatsapp.net',
            'label' => 'Contato',
            'analysis_profile_id' => $profile->id,
            'is_active' => true,
        ]);

        $resolved = app(AnalysisProfileResolver::class)->resolveForMonitoredSource($source->fresh('analysisProfile'));

        $this->assertNotNull($resolved);
        $this->assertSame($profile->id, $resolved?->id);
    }
}
