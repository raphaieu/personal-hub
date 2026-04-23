<?php

namespace App\Services\Threads;

use App\Contracts\ThreadsScraperClientInterface;

final class FakeThreadsScraperClient implements ThreadsScraperClientInterface
{
    /**
     * @param  array<string, mixed>|null  $urlResponse
     * @param  array<string, mixed>|null  $keywordResponse
     */
    public function __construct(
        private ?array $urlResponse = null,
        private ?array $keywordResponse = null,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public function setUrlResponse(array $response): void
    {
        $this->urlResponse = $response;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function setKeywordResponse(array $response): void
    {
        $this->keywordResponse = $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function scrapeByUrl(string $url): array
    {
        return $this->urlResponse ?? [
            'success' => true,
            'mode' => 'url',
            'source_value' => $url,
            'scraped_at' => now()->toIso8601String(),
            'data' => [
                'source_tag' => 'url',
                'post' => [],
                'comments' => [],
            ],
        ];
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
        return $this->keywordResponse ?? [
            'success' => true,
            'mode' => 'keyword',
            'source_value' => $keyword,
            'scraped_at' => now()->toIso8601String(),
            'include_comments' => $includeComments,
            'only_new' => $onlyNew,
            'data' => [
                'posts' => [],
                'stats' => [
                    'posts_detected' => 0,
                    'posts_selected' => $maxPosts,
                    'posts_processed' => 0,
                    'known_detected' => count($knownPostIds),
                    'new_detected' => 0,
                    'skipped_known' => 0,
                    'early_stop_triggered' => false,
                    'known_streak_stop' => $knownStreakStop ?? 0,
                    'comments_total' => 0,
                ],
            ],
        ];
    }
}
