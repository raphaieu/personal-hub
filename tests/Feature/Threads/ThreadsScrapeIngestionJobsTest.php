<?php

namespace Tests\Feature\Threads;

use App\Contracts\ThreadsScraperClientInterface;
use App\Jobs\ScrapeThreadsKeywordJob;
use App\Jobs\ScrapeThreadsUrlJob;
use App\Models\ThreadsComment;
use App\Models\ThreadsPost;
use App\Models\ThreadsSource;
use App\Services\Threads\FakeThreadsScraperClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ThreadsScrapeIngestionJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scrape_url_job_upserts_post_and_comments_by_external_id(): void
    {
        $source = ThreadsSource::query()->create([
            'type' => 'url',
            'label' => 'URL de teste',
            'target_url' => 'https://www.threads.com/@foo/post/abc123',
            'is_active' => true,
        ]);

        $fake = new FakeThreadsScraperClient(
            urlResponse: [
                'success' => true,
                'mode' => 'url',
                'source_value' => $source->target_url,
                'scraped_at' => '2026-04-23T20:30:00.000Z',
                'data' => [
                    'post' => [
                        'external_id' => 'abc123',
                        'post_url' => $source->target_url,
                        'author_handle' => '@foo',
                        'author_name' => '@foo',
                        'content' => 'Post inicial',
                        'posted_at' => '2026-04-23T10:00:00.000Z',
                    ],
                    'comments' => [
                        [
                            'external_id' => 'cmt-1',
                            'author_handle' => '@bar',
                            'author_name' => '@bar',
                            'content' => 'Comentario inicial',
                            'posted_at' => '2026-04-23T10:10:00.000Z',
                        ],
                    ],
                ],
            ],
        );

        $this->app->instance(ThreadsScraperClientInterface::class, $fake);

        (new ScrapeThreadsUrlJob($source->target_url, $source->id))->handle(
            app(ThreadsScraperClientInterface::class),
            app(\App\Services\Threads\ThreadsScrapeIngestionService::class)
        );

        $fake->setUrlResponse([
            'success' => true,
            'mode' => 'url',
            'source_value' => $source->target_url,
            'scraped_at' => '2026-04-23T20:35:00.000Z',
            'data' => [
                'post' => [
                    'external_id' => 'abc123',
                    'post_url' => $source->target_url,
                    'author_handle' => '@foo',
                    'author_name' => '@foo',
                    'content' => 'Post atualizado',
                    'posted_at' => '2026-04-23T10:00:00.000Z',
                ],
                'comments' => [
                    [
                        'external_id' => 'cmt-1',
                        'author_handle' => '@bar',
                        'author_name' => '@bar',
                        'content' => 'Comentario atualizado',
                        'posted_at' => '2026-04-23T10:10:00.000Z',
                    ],
                ],
            ],
        ]);

        (new ScrapeThreadsUrlJob($source->target_url, $source->id))->handle(
            app(ThreadsScraperClientInterface::class),
            app(\App\Services\Threads\ThreadsScrapeIngestionService::class)
        );

        $this->assertSame(1, ThreadsPost::query()->count());
        $this->assertSame(1, ThreadsComment::query()->count());
        $this->assertSame('Post atualizado', (string) ThreadsPost::query()->first()?->content);
        $this->assertSame('Comentario atualizado', (string) ThreadsComment::query()->first()?->content);
    }

    public function test_scrape_keyword_job_runs_with_fake_client_and_upserts_posts(): void
    {
        $source = ThreadsSource::query()->create([
            'type' => 'keyword',
            'label' => 'Keyword de teste',
            'keyword' => 'freelance php',
            'is_active' => true,
        ]);

        $fake = new FakeThreadsScraperClient(
            keywordResponse: [
                'success' => true,
                'mode' => 'keyword',
                'source_value' => 'freelance php',
                'scraped_at' => '2026-04-23T21:00:00.000Z',
                'include_comments' => false,
                'only_new' => true,
                'data' => [
                    'posts' => [
                        [
                            'source_tag' => 'keyword',
                            'post' => [
                                'external_id' => 'post-k-1',
                                'post_url' => 'https://www.threads.com/@jobs/post/post-k-1',
                                'author_handle' => '@jobs',
                                'author_name' => '@jobs',
                                'content' => 'Oportunidade backend Laravel',
                                'posted_at' => '2026-04-23T11:00:00.000Z',
                            ],
                            'is_known' => false,
                        ],
                    ],
                    'stats' => [
                        'posts_detected' => 1,
                        'posts_selected' => 1,
                        'posts_processed' => 1,
                        'known_detected' => 0,
                        'new_detected' => 1,
                        'skipped_known' => 0,
                        'early_stop_triggered' => false,
                        'known_streak_stop' => 20,
                        'comments_total' => 0,
                    ],
                ],
            ],
        );

        $this->app->instance(ThreadsScraperClientInterface::class, $fake);

        (new ScrapeThreadsKeywordJob(
            keyword: 'freelance php',
            maxPosts: 10,
            includeComments: false,
            knownPostIds: [],
            onlyNew: true,
            knownStreakStop: 20,
            threadsSourceId: $source->id,
        ))->handle(
            app(ThreadsScraperClientInterface::class),
            app(\App\Services\Threads\ThreadsScrapeIngestionService::class)
        );

        $this->assertDatabaseHas('threads_posts', [
            'external_id' => 'post-k-1',
            'threads_source_id' => $source->id,
        ]);
    }
}
