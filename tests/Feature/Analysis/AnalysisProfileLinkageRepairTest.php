<?php

namespace Tests\Feature\Analysis;

use App\Models\AnalysisProfile;
use App\Models\ThreadsCategory;
use App\Models\ThreadsSource;
use Database\Seeders\ThreadsCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnalysisProfileLinkageRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_command_backfills_threads_sources_and_categories_with_default_profile(): void
    {
        $legacySource = ThreadsSource::query()->create([
            'type' => 'keyword',
            'label' => 'Legacy Source',
            'keyword' => 'legacy',
            'analysis_profile_id' => null,
            'is_active' => true,
        ]);
        $legacyCategory = ThreadsCategory::query()->create([
            'slug' => 'legacy-cat',
            'name' => 'Legacy Cat',
            'analysis_profile_id' => null,
            'is_active' => true,
        ]);

        AnalysisProfile::query()
            ->where('slug', AnalysisProfile::THREADS_OPPORTUNITIES_SLUG)
            ->delete();

        $this->artisan('analysis:repair-profile-linkage')
            ->assertSuccessful();

        $defaultProfileId = AnalysisProfile::defaultThreadsProfileId();

        $this->assertNotNull($defaultProfileId);
        $this->assertSame($defaultProfileId, $legacySource->fresh()?->analysis_profile_id);
        $this->assertSame($defaultProfileId, $legacyCategory->fresh()?->analysis_profile_id);
    }

    public function test_threads_category_seeder_updates_legacy_rows_without_creating_duplicate_slug(): void
    {
        $defaultProfile = AnalysisProfile::query()->firstOrCreate(
            ['slug' => AnalysisProfile::THREADS_OPPORTUNITIES_SLUG],
            [
                'name' => 'Threads Opportunities',
                'analysis_type' => 'classification',
                'system_prompt' => 'prompt',
                'is_active' => true,
            ]
        );

        ThreadsCategory::query()->create([
            'slug' => 'freela',
            'name' => 'Freela antigo',
            'analysis_profile_id' => null,
            'is_active' => true,
        ]);

        $this->seed(ThreadsCategorySeeder::class);

        $this->assertSame(1, ThreadsCategory::query()->where('slug', 'freela')->count());
        $this->assertSame(
            $defaultProfile->id,
            ThreadsCategory::query()->where('slug', 'freela')->value('analysis_profile_id')
        );
    }
}
