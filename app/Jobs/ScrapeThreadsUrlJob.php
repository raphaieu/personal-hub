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

class ScrapeThreadsUrlJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $url,
        public readonly ?int $threadsSourceId = null,
    ) {
        $this->onQueue('scraping');
    }

    public function handle(
        ThreadsScraperClientInterface $client,
        ThreadsScrapeIngestionService $ingestionService,
    ): void {
        $source = $this->resolveSource();
        $payload = $client->scrapeByUrl($this->url);

        if (! ($payload['success'] ?? false)) {
            throw new RuntimeException((string) ($payload['error'] ?? 'Falha no scraping por URL.'));
        }

        $result = $ingestionService->ingestUrlPayload($payload, $source);

        if ($source) {
            $source->forceFill(['last_scraped_at' => now()])->save();
        }

        Log::info('threads.scrape_url.ingested', [
            'source_id' => $source?->id,
            'url' => $this->url,
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
