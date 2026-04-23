<?php

namespace App\Services\Threads;

use App\Models\ThreadsComment;
use App\Models\ThreadsPost;
use App\Models\ThreadsSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

final class ThreadsScrapeIngestionService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{posts_upserted: int, comments_upserted: int}
     */
    public function ingestUrlPayload(array $payload, ?ThreadsSource $source = null): array
    {
        $data = Arr::get($payload, 'data');
        if (! is_array($data)) {
            return ['posts_upserted' => 0, 'comments_upserted' => 0];
        }

        $scrapedAt = $this->parseDate(Arr::get($payload, 'scraped_at'));
        $postData = Arr::get($data, 'post');
        if (! is_array($postData)) {
            return ['posts_upserted' => 0, 'comments_upserted' => 0];
        }

        $post = $this->upsertPost($postData, $source, $scrapedAt);
        if (! $post) {
            return ['posts_upserted' => 0, 'comments_upserted' => 0];
        }

        $comments = Arr::get($data, 'comments');
        $commentsUpserted = $this->upsertComments($post, $comments, $scrapedAt);

        return [
            'posts_upserted' => 1,
            'comments_upserted' => $commentsUpserted,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{posts_upserted: int, comments_upserted: int}
     */
    public function ingestKeywordPayload(array $payload, ?ThreadsSource $source = null): array
    {
        $data = Arr::get($payload, 'data');
        if (! is_array($data)) {
            return ['posts_upserted' => 0, 'comments_upserted' => 0];
        }

        $items = Arr::get($data, 'posts');
        if (! is_array($items)) {
            return ['posts_upserted' => 0, 'comments_upserted' => 0];
        }

        $scrapedAt = $this->parseDate(Arr::get($payload, 'scraped_at'));
        $postsUpserted = 0;
        $commentsUpserted = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $postData = Arr::get($item, 'post');
            if (! is_array($postData)) {
                continue;
            }

            $post = $this->upsertPost($postData, $source, $scrapedAt);
            if (! $post) {
                continue;
            }

            $postsUpserted++;
            $commentsUpserted += $this->upsertComments($post, Arr::get($item, 'comments'), $scrapedAt);
        }

        return [
            'posts_upserted' => $postsUpserted,
            'comments_upserted' => $commentsUpserted,
        ];
    }

    /**
     * @param  array<string, mixed>  $postData
     */
    private function upsertPost(array $postData, ?ThreadsSource $source, ?CarbonImmutable $scrapedAt): ?ThreadsPost
    {
        $externalId = $this->stringOrNull(Arr::get($postData, 'external_id'));
        if (! $externalId) {
            return null;
        }

        return ThreadsPost::query()->updateOrCreate(
            ['external_id' => $externalId],
            [
                'threads_source_id' => $source?->id,
                'post_url' => $this->stringOrNull(Arr::get($postData, 'post_url')),
                'author_handle' => $this->stringOrNull(Arr::get($postData, 'author_handle')),
                'author_name' => $this->stringOrNull(Arr::get($postData, 'author_name')),
                'content' => $this->stringOrNull(Arr::get($postData, 'content')),
                'published_at' => $this->parseDate(Arr::get($postData, 'posted_at')),
                'scraped_at' => $scrapedAt,
                'raw_payload' => $postData,
            ]
        );
    }

    /**
     * @param  mixed  $comments
     */
    private function upsertComments(ThreadsPost $post, mixed $comments, ?CarbonImmutable $scrapedAt): int
    {
        if (! is_array($comments)) {
            return 0;
        }

        $upserted = 0;
        foreach ($comments as $commentData) {
            if (! is_array($commentData)) {
                continue;
            }

            $externalId = $this->stringOrNull(Arr::get($commentData, 'external_id'));
            if (! $externalId) {
                continue;
            }

            ThreadsComment::query()->updateOrCreate(
                ['external_id' => $externalId],
                [
                    'threads_post_id' => $post->id,
                    'parent_external_id' => $this->stringOrNull(Arr::get($commentData, 'parent_external_id')),
                    'author_handle' => $this->stringOrNull(Arr::get($commentData, 'author_handle')),
                    'author_name' => $this->stringOrNull(Arr::get($commentData, 'author_name')),
                    'content' => $this->stringOrNull(Arr::get($commentData, 'content')),
                    'commented_at' => $this->parseDate(Arr::get($commentData, 'posted_at')),
                    'scraped_at' => $scrapedAt,
                    'raw_payload' => $commentData,
                ]
            );

            $upserted++;
        }

        return $upserted;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
