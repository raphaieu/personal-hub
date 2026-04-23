<?php

namespace App\Contracts;

interface ThreadsScraperClientInterface
{
    /**
     * @return array<string, mixed>
     */
    public function scrapeByUrl(string $url): array;

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
    ): array;
}
