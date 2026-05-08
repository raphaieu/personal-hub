<?php

namespace App\Services\Albums;

use App\Jobs\Albums\SendAlbumContributionDigestJob;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Models\Contributor;
use Illuminate\Support\Facades\Cache;

/**
 * Acumula linhas de resumo por álbum e agenda um único job (ShouldBeUnique) após a janela de debounce.
 * O buffer usa {@see Cache} — em produção, configure CACHE_STORE=redis para persistência alinhada ao Redis do Hub.
 */
final class AlbumContributionDigestService
{
    private function bufferKey(string $albumId): string
    {
        return 'albums:contrib:digest:buffer:'.$albumId;
    }

    public function recordUploadedMedia(Album $album, Contributor $contributor, AlbumMedia $media): void
    {
        $line = [
            'contributor_email' => $contributor->email,
            'media_id' => $media->id,
            'filename' => $media->filename_original,
            'type' => $media->type,
            'recorded_at' => now()->toIso8601String(),
        ];

        $key = $this->bufferKey($album->id);
        $ttlSeconds = max(300, (int) config('services.albums.contribution_notify_debounce_seconds', 600) * 2);

        Cache::lock('albums:contrib:digest:lock:'.$album->id, 10)->block(5, function () use ($key, $line, $ttlSeconds): void {
            /** @var list<array<string, mixed>> $buf */
            $buf = Cache::get($key, []);
            $buf[] = $line;
            Cache::put($key, $buf, now()->addSeconds($ttlSeconds));
        });

        $debounce = max(60, (int) config('services.albums.contribution_notify_debounce_seconds', 600));
        $schedKey = 'albums:contrib:digest:sched:'.$album->id;

        if (! Cache::add($schedKey, 1, now()->addSeconds($debounce + 120))) {
            return;
        }

        SendAlbumContributionDigestJob::dispatch($album->id)
            ->onQueue('notifications')
            ->delay(now()->addSeconds($debounce));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pullBuffer(string $albumId): array
    {
        $key = $this->bufferKey($albumId);

        $pulled = Cache::lock('albums:contrib:digest:lock:'.$albumId, 10)->block(5, function () use ($key): array {
            /** @var list<array<string, mixed>> $buf */
            $buf = Cache::get($key, []);
            if ($buf !== []) {
                Cache::forget($key);
            }

            return $buf;
        });

        return is_array($pulled) ? $pulled : [];
    }
}
