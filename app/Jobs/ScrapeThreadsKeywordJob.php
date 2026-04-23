<?php

namespace App\Jobs;

use App\Contracts\ThreadsScraperClientInterface;
use App\Models\ThreadsSource;
use App\Services\Threads\ThreadsScrapeIngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ScrapeThreadsKeywordJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<string>  $knownPostIds
     */
    public function __construct(
        public readonly string $keyword,
        public readonly int $maxPosts = 30,
        public readonly bool $includeComments = false,
        public readonly array $knownPostIds = [],
        public readonly bool $onlyNew = true,
        public readonly ?int $knownStreakStop = null,
        public readonly ?int $threadsSourceId = null,
    ) {
        $this->onQueue('scraping');
    }

    public function handle(
        ThreadsScraperClientInterface $client,
        ThreadsScrapeIngestionService $ingestionService,
    ): void {
        $source = $this->resolveSource();
        $payload = $client->scrapeByKeyword(
            keyword: $this->keyword,
            maxPosts: $this->maxPosts,
            includeComments: $this->includeComments,
            knownPostIds: $this->knownPostIds,
            onlyNew: $this->onlyNew,
            knownStreakStop: $this->knownStreakStop,
        );

        if (! ($payload['success'] ?? false)) {
            throw new RuntimeException((string) ($payload['error'] ?? 'Falha no scraping por keyword.'));
        }

        $result = $ingestionService->ingestKeywordPayload($payload, $source);

        if (($result['comment_ids'] ?? []) !== []) {
            $spacingSeconds = max(0, (int) env('THREADS_AI_DISPATCH_SPACING_SECONDS', 2));

            foreach ($result['comment_ids'] as $index => $commentId) {
                ClassifyCommentsJob::dispatch((int) $commentId)
                    ->delay(now()->addSeconds($index * $spacingSeconds));
            }
        }

        if ($source) {
            $source->forceFill(['last_scraped_at' => now()])->save();
        }

        Log::info('threads.scrape_keyword.ingested', [
            'source_id' => $source?->id,
            'keyword' => $this->keyword,
            'posts_upserted' => $result['posts_upserted'],
            'comments_upserted' => $result['comments_upserted'],
        ]);
    }

    private function resolveSource(): ?ThreadsSource
    {
        if (! $this->threadsSourceId) {
            return null;
        }

        return ThreadsSource::query()->find($this->threadsSourceId);
    }
}
