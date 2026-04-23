<?php

namespace App\Services\Threads;

use App\Contracts\ThreadsScraperClientInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ThreadsPlaywrightService implements ThreadsScraperClientInterface
{
    /**
     * @return array<string, mixed>
     */
    public function scrapeByUrl(string $url): array
    {
        return $this->request('/threads/scrape-url', [
            'url' => $url,
        ], 'url', $url);
    }

    /**
     * @param  list<string>  $knownPostIds
     * @return array<string, mixed>
     */
    public function scrapeByKeyword(
        string $keyword,
        int $maxPosts = 30,
        bool $includeComments = false,
        array $knownPostIds = [],
        bool $onlyNew = true,
        ?int $knownStreakStop = null,
    ): array {
        $payload = [
            'keyword' => $keyword,
            'max_posts' => $maxPosts,
            'include_comments' => $includeComments,
            'known_post_ids' => $knownPostIds,
            'only_new' => $onlyNew,
        ];

        if ($knownStreakStop !== null) {
            $payload['known_streak_stop'] = $knownStreakStop;
        }

        return $this->request('/threads/scrape-keyword', $payload, 'keyword', $keyword);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $path, array $payload, string $mode, string $sourceValue): array
    {
        $baseUrl = rtrim((string) config('services.playwright.url', 'http://127.0.0.1:3001'), '/');
        $timeout = (int) config('services.playwright.timeout', 120);
        $url = $baseUrl.$path;

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::warning('threads.playwright.connection_failure', [
                'url' => $url,
                'mode' => $mode,
                'source_value' => $sourceValue,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Falha de conexão com o serviço Playwright de Threads.', 0, $e);
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Resposta inválida do serviço Playwright (JSON ausente ou malformado).');
        }

        if (! $response->successful()) {
            Log::warning('threads.playwright.http_failure', [
                'url' => $url,
                'mode' => $mode,
                'source_value' => $sourceValue,
                'status' => $response->status(),
                'response' => $json,
            ]);

            throw new RuntimeException(
                sprintf(
                    'Serviço Playwright respondeu HTTP %d para modo %s.',
                    $response->status(),
                    $mode
                )
            );
        }

        return $this->normalizeResponse($json, $mode, $sourceValue);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function normalizeResponse(array $json, string $mode, string $sourceValue): array
    {
        $success = (bool) ($json['success'] ?? false);

        $normalized = [
            'success' => $success,
            'mode' => is_string($json['mode'] ?? null) ? $json['mode'] : $mode,
            'source_value' => is_string($json['source_value'] ?? null) ? $json['source_value'] : $sourceValue,
            'scraped_at' => is_string($json['scraped_at'] ?? null) ? $json['scraped_at'] : now()->toIso8601String(),
            'error' => isset($json['error']) && is_string($json['error']) ? $json['error'] : null,
            'screenshot_path' => isset($json['screenshot_path']) && is_string($json['screenshot_path']) ? $json['screenshot_path'] : null,
            'data' => isset($json['data']) && is_array($json['data']) ? $json['data'] : [],
        ];

        if ($mode === 'keyword') {
            $normalized['include_comments'] = (bool) ($json['include_comments'] ?? false);
            $normalized['only_new'] = (bool) ($json['only_new'] ?? false);
        }

        return $normalized;
    }
}
