<?php

namespace Tests\Feature\Threads;

use App\Jobs\ClassifyCommentsJob;
use App\Jobs\DispatchPendingThreadsClassificationJob;
use App\Jobs\ScrapeThreadsKeywordJob;
use App\Jobs\ScrapeThreadsUrlJob;
use App\Livewire\Threads\HubPage;
use App\Models\ThreadsCategory;
use App\Models\ThreadsComment;
use App\Models\ThreadsPost;
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

    public function test_livewire_dispatches_pending_classification_job_manually(): void
    {
        Bus::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('manualDispatchBatchSize', 3)
            ->call('dispatchPendingClassification');

        Bus::assertDispatched(DispatchPendingThreadsClassificationJob::class);
    }

    public function test_livewire_can_move_ignored_comment_back_to_pending_review(): void
    {
        $user = User::factory()->create();
        $comment = $this->makeComment(status: 'ignored');

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('moveCommentToPendingReview', $comment->id);

        $this->assertSame('pending_review', (string) $comment->fresh()?->status);
    }

    public function test_livewire_can_toggle_comment_public_visibility(): void
    {
        $user = User::factory()->create();
        $comment = $this->makeComment(status: 'pending_review');

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('toggleCommentPublic', $comment->id);

        $this->assertTrue((bool) $comment->fresh()?->is_public);
    }

    public function test_livewire_batch_actions_update_selected_comments(): void
    {
        $user = User::factory()->create();
        $commentA = $this->makeComment(status: 'pending_review');
        $commentB = $this->makeComment(status: 'pending_review');

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('selectedReviewCommentIds', [$commentA->id, $commentB->id])
            ->call('batchIgnoreSelected');

        $this->assertSame('ignored', (string) $commentA->fresh()?->status);
        $this->assertSame('ignored', (string) $commentB->fresh()?->status);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('selectedReviewCommentIds', [$commentA->id, $commentB->id])
            ->call('batchPublishSelected');

        $this->assertTrue((bool) $commentA->fresh()?->is_public);
        $this->assertTrue((bool) $commentB->fresh()?->is_public);
    }

    public function test_livewire_batch_reclassify_dispatches_one_job_per_selected_comment(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $commentA = $this->makeComment(status: 'pending_review');
        $commentB = $this->makeComment(status: 'ignored');

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('selectedReviewCommentIds', [$commentA->id, $commentB->id])
            ->call('batchReclassifySelected');

        Bus::assertDispatchedTimes(ClassifyCommentsJob::class, 2);
        Bus::assertDispatched(ClassifyCommentsJob::class, function (ClassifyCommentsJob $job) use ($commentA): bool {
            return $job->commentId === $commentA->id && $job->force === true;
        });
        Bus::assertDispatched(ClassifyCommentsJob::class, function (ClassifyCommentsJob $job) use ($commentB): bool {
            return $job->commentId === $commentB->id && $job->force === true;
        });
    }

    public function test_livewire_review_filters_can_limit_without_summary_by_source_and_category(): void
    {
        $user = User::factory()->create();
        $sourceA = ThreadsSource::query()->create([
            'type' => 'keyword',
            'label' => 'Source A',
            'keyword' => 'a',
            'is_active' => true,
        ]);
        $sourceB = ThreadsSource::query()->create([
            'type' => 'keyword',
            'label' => 'Source B',
            'keyword' => 'b',
            'is_active' => true,
        ]);
        $categoryA = ThreadsCategory::query()->create([
            'slug' => 'freela',
            'name' => 'Freela',
            'is_active' => true,
        ]);
        $categoryB = ThreadsCategory::query()->create([
            'slug' => 'outros',
            'name' => 'Outros',
            'is_active' => true,
        ]);

        $target = $this->makeComment(status: 'pending_review', sourceId: $sourceA->id);
        $target->forceFill(['content' => 'match sem resumo', 'threads_category_id' => $categoryA->id, 'ai_summary' => null])->save();

        $excludedWithSummary = $this->makeComment(status: 'pending_review', sourceId: $sourceA->id);
        $excludedWithSummary->forceFill(['content' => 'tem resumo', 'threads_category_id' => $categoryA->id, 'ai_summary' => 'ok'])->save();

        $excludedOtherSource = $this->makeComment(status: 'pending_review', sourceId: $sourceB->id);
        $excludedOtherSource->forceFill(['content' => 'outra source', 'threads_category_id' => $categoryA->id, 'ai_summary' => null])->save();

        $excludedOtherCategory = $this->makeComment(status: 'pending_review', sourceId: $sourceA->id);
        $excludedOtherCategory->forceFill(['content' => 'outra categoria', 'threads_category_id' => $categoryB->id, 'ai_summary' => null])->save();

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('currentTab', 'review')
            ->set('reviewWithoutSummary', true)
            ->set('reviewSource', (string) $sourceA->id)
            ->set('reviewCategory', (string) $categoryA->id)
            ->assertSee('match sem resumo')
            ->assertDontSee('tem resumo')
            ->assertDontSee('outra source')
            ->assertDontSee('outra categoria');
    }

    private function makeComment(string $status = 'pending_review', ?int $sourceId = null): ThreadsComment
    {
        $post = ThreadsPost::query()->create([
            'external_id' => uniqid('post-', true),
            'threads_source_id' => $sourceId,
        ]);

        return ThreadsComment::query()->create([
            'threads_post_id' => $post->id,
            'external_id' => uniqid('comment-', true),
            'content' => 'Comentário para review',
            'status' => $status,
            'is_public' => false,
        ]);
    }
}
