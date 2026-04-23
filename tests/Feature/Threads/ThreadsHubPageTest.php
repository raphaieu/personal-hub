<?php

namespace Tests\Feature\Threads;

use App\Jobs\ScrapeThreadsKeywordJob;
use App\Jobs\ScrapeThreadsUrlJob;
use App\Livewire\Threads\HubPage;
use App\Models\ThreadsSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

final class ThreadsHubPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_threads_hub_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('threads.hub'));

        $response
            ->assertOk()
            ->assertSee('Hub Threads')
            ->assertSee('Sources');
    }

    public function test_threads_hub_page_renders_sources_table_data(): void
    {
        $user = User::factory()->create();

        ThreadsSource::query()->create([
            'type' => 'keyword',
            'label' => 'Freelas PHP',
            'keyword' => 'freelance php remoto',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('threads.hub'));

        $response
            ->assertOk()
            ->assertSee('Freelas PHP')
            ->assertSee('freelance php remoto')
            ->assertSee('Ativa');
    }

    public function test_livewire_can_create_keyword_source(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('newSourceType', 'keyword')
            ->set('newSourceLabel', 'Remoto JS')
            ->set('newSourceKeyword', 'vaga remoto javascript')
            ->set('newSourceIsActive', true)
            ->call('createSource')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('threads_sources', [
            'type' => 'keyword',
            'label' => 'Remoto JS',
            'keyword' => 'vaga remoto javascript',
            'is_active' => true,
        ]);
    }

    public function test_livewire_can_toggle_source_status(): void
    {
        $user = User::factory()->create();
        $source = ThreadsSource::query()->create([
            'type' => 'keyword',
            'label' => 'Toggle Test',
            'keyword' => 'toggle',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('toggleSource', $source->id);

        $this->assertFalse((bool) $source->fresh()?->is_active);
    }

    public function test_livewire_dispatches_keyword_scrape_job_from_source_action(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $source = ThreadsSource::query()->create([
            'type' => 'keyword',
            'label' => 'Freelas',
            'keyword' => 'freela laravel',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('scrapeNow', $source->id);

        Bus::assertDispatched(ScrapeThreadsKeywordJob::class);
    }

    public function test_livewire_can_create_url_source_on_first_submit_after_type_change(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('newSourceType', 'url')
            ->set('newSourceLabel', 'Minha URL')
            ->set('newSourceTargetUrl', 'https://www.threads.com/@myvidaemfotos/post/DXcRQA-kSAg')
            ->call('createSource')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('threads_sources', [
            'type' => 'url',
            'label' => 'Minha URL',
            'target_url' => 'https://www.threads.com/@myvidaemfotos/post/DXcRQA-kSAg',
        ]);
    }

    public function test_livewire_dispatches_url_scrape_job_from_source_action(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $source = ThreadsSource::query()->create([
            'type' => 'url',
            'label' => 'URL Thread',
            'target_url' => 'https://www.threads.com/@foo/post/abc',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('scrapeNow', $source->id);

        Bus::assertDispatched(ScrapeThreadsUrlJob::class);
    }
}
