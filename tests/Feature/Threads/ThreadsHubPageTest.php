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

    public function test_dispatch_pending_classification_passes_batch_size_to_job(): void
    {
        Bus::fake();
        $user = User::factory()->create();

        $this->makeComment();
        $this->makeComment();
        $this->makeComment();

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('manualDispatchBatchSize', 2)
            ->call('dispatchPendingClassification');

        Bus::assertDispatched(DispatchPendingThreadsClassificationJob::class, function (DispatchPendingThreadsClassificationJob $job): bool {
            return $job->batchSize === 2;
        });
    }

    public function test_review_select_all_selects_then_clears_visible_rows(): void
    {
        $user = User::factory()->create();
        $commentA = $this->makeComment();
        $commentB = $this->makeComment();

        $component = Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('currentTab', 'review');

        $component->call('toggleSelectAllReviewOnPage');
        $this->assertEqualsCanonicalizing(
            [$commentA->id, $commentB->id],
            $component->instance()->selectedReviewCommentIds
        );

        $component->call('toggleSelectAllReviewOnPage');
        $this->assertSame([], $component->instance()->selectedReviewCommentIds);
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

    public function test_review_tab_supports_pagination_beyond_first_hundred_items(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 120; $i++) {
            $comment = $this->makeComment(status: 'pending_review');
            $comment->forceFill([
                'content' => sprintf('comentario paginacao %03d', $i),
            ])->save();
        }

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('currentTab', 'review')
            ->assertSee('120 itens no filtro');
    }

    public function test_published_tab_lists_only_public_comments(): void
    {
        $user = User::factory()->create();
        $secret = $this->makeComment(isPublic: false);
        $secret->forceFill(['ai_summary' => 'SEC_UNIQ_NOT_SHOWN_IN_PUB'])->save();

        $pub = $this->makeComment(isPublic: true);
        $pub->forceFill(['ai_summary' => 'PUB_UNIQ_VISIBLE_IN_TAB'])->save();

        $component = Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('currentTab', 'published');

        $component->assertSet('publishedForms.'.$pub->id.'.ai_summary', 'PUB_UNIQ_VISIBLE_IN_TAB');

        $this->assertArrayNotHasKey($secret->id, $component->instance()->publishedForms);

        $component->assertDontSee('SEC_UNIQ_NOT_SHOWN_IN_PUB');
    }

    public function test_livewire_can_save_published_comment_quick_edit_fields(): void
    {
        $user = User::factory()->create();
        $category = ThreadsCategory::query()->create([
            'slug' => 'save-cat',
            'name' => 'Save Cat',
            'is_active' => true,
        ]);
        $comment = $this->makeComment(isPublic: true);
        $comment->forceFill([
            'ai_summary' => 'resumo antigo',
            'threads_category_id' => null,
            'is_featured' => false,
        ])->save();

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('currentTab', 'published')
            ->set('publishedForms.'.$comment->id.'.ai_summary', 'resumo editado para o feed')
            ->set('publishedForms.'.$comment->id.'.threads_category_id', (string) $category->id)
            ->set('publishedForms.'.$comment->id.'.is_featured', true)
            ->call('savePublishedComment', $comment->id)
            ->assertHasNoErrors();

        $fresh = $comment->fresh();
        $this->assertSame('resumo editado para o feed', (string) $fresh?->ai_summary);
        $this->assertSame($category->id, (int) $fresh?->threads_category_id);
        $this->assertTrue((bool) $fresh?->is_featured);
    }

    public function test_livewire_can_unpublish_from_published_tab(): void
    {
        $user = User::factory()->create();
        $comment = $this->makeComment(isPublic: true);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('currentTab', 'published')
            ->call('unpublishPublishedComment', $comment->id);

        $this->assertFalse((bool) $comment->fresh()?->is_public);
    }

    public function test_published_tab_supports_pagination_beyond_first_hundred_items(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 120; $i++) {
            $comment = $this->makeComment(isPublic: true);
            $comment->forceFill([
                'ai_summary' => sprintf('resumo publicado %03d', $i),
            ])->save();
        }

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('currentTab', 'published')
            ->assertSee('120 publicado(s) neste filtro');
    }

    private function makeComment(string $status = 'pending_review', ?int $sourceId = null, bool $isPublic = false): ThreadsComment
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
            'is_public' => $isPublic,
        ]);
    }
}
