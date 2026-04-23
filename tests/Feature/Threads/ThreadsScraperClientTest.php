<?php

namespace Tests\Feature\Threads;

use App\Contracts\ThreadsScraperClientInterface;
use App\Services\Threads\FakeThreadsScraperClient;
use App\Services\Threads\ThreadsPlaywrightService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class ThreadsScraperClientTest extends TestCase
{
    public function test_container_resolves_threads_scraper_interface_with_http_service(): void
    {
        $client = app(ThreadsScraperClientInterface::class);

        $this->assertInstanceOf(ThreadsPlaywrightService::class, $client);
    }

    public function test_scrape_by_keyword_normalizes_payload_and_response(): void
    {
        Config::set('services.playwright.url', 'http://127.0.0.1:3001');
        Config::set('services.playwright.timeout', 120);

        Http::fake([
            'http://127.0.0.1:3001/threads/scrape-keyword' => Http::response([
                'success' => true,
                'mode' => 'keyword',
                'source_value' => 'freelance php remoto',
                'include_comments' => false,
                'only_new' => true,
                'scraped_at' => '2026-04-23T17:22:42.001Z',
                'data' => [
                    'posts' => [],
                    'stats' => [
                        'posts_detected' => 10,
                        'posts_selected' => 3,
                        'posts_processed' => 2,
                        'known_detected' => 1,
                        'new_detected' => 2,
                        'skipped_known' => 1,
                        'early_stop_triggered' => false,
                        'known_streak_stop' => 20,
                        'comments_total' => 0,
                    ],
                ],
            ], 200),
        ]);

        $client = app(ThreadsScraperClientInterface::class);
        $result = $client->scrapeByKeyword(
            keyword: 'freelance php remoto',
            maxPosts: 3,
            includeComments: false,
            knownPostIds: ['DXaaS6-igb9'],
            onlyNew: true,
            knownStreakStop: 20,
        );

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'http://127.0.0.1:3001/threads/scrape-keyword'
                && $data['keyword'] === 'freelance php remoto'
                && $data['max_posts'] === 3
                && $data['include_comments'] === false
                && $data['known_post_ids'] === ['DXaaS6-igb9']
                && $data['only_new'] === true
                && $data['known_streak_stop'] === 20;
        });

        $this->assertTrue($result['success']);
        $this->assertSame('keyword', $result['mode']);
        $this->assertSame('freelance php remoto', $result['source_value']);
        $this->assertSame(2, $result['data']['stats']['posts_processed']);
        $this->assertFalse($result['include_comments']);
        $this->assertTrue($result['only_new']);
    }

    public function test_scrape_by_url_throws_runtime_exception_on_http_error(): void
    {
        Config::set('services.playwright.url', 'http://127.0.0.1:3001');
        Config::set('services.playwright.timeout', 120);

        Http::fake([
            'http://127.0.0.1:3001/threads/scrape-url' => Http::response([
                'success' => false,
                'mode' => 'url',
                'source_value' => 'https://www.threads.net/@handle/post/abc',
                'error' => 'upstream failure',
            ], 502),
        ]);

        $client = app(ThreadsScraperClientInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Serviço Playwright respondeu HTTP 502 para modo url.');

        $client->scrapeByUrl('https://www.threads.net/@handle/post/abc');
    }

    public function test_fake_client_supports_override_and_default_payloads(): void
    {
        $fake = new FakeThreadsScraperClient;

        $defaultKeyword = $fake->scrapeByKeyword(keyword: 'laravel');
        $this->assertTrue($defaultKeyword['success']);
        $this->assertSame('keyword', $defaultKeyword['mode']);

        $fake->setUrlResponse([
            'success' => true,
            'mode' => 'url',
            'source_value' => 'https://www.threads.net/@foo/post/123',
            'scraped_at' => '2026-04-23T17:22:42.001Z',
            'data' => ['post' => ['external_id' => '123'], 'comments' => []],
        ]);

        $overriddenUrl = $fake->scrapeByUrl('https://www.threads.net/@foo/post/123');
        $this->assertSame('123', $overriddenUrl['data']['post']['external_id']);
    }
}
