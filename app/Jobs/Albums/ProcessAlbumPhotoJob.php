<?php

namespace App\Jobs\Albums;

use App\Models\Album;
use App\Models\AlbumMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProcessAlbumPhotoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $albumMediaId,
    ) {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $media = AlbumMedia::query()->with('album')->find($this->albumMediaId);
        if ($media === null || $media->type !== 'photo') {
            return;
        }

        $media->forceFill(['processing_status' => 'processing'])->save();

        $album = $media->album;
        if ($album === null) {
            $this->markFailed($media, 'album_missing');

            return;
        }

        $disk = Storage::disk('s3');

        // Evita HEAD: alguns proxies/permissões bloqueiam HeadObject mas permitem GetObject.
        try {
            $binary = $disk->get($media->original_path);
        } catch (Throwable $e) {
            $this->markFailed($media, 'storage_io_error', $e);
            Log::error('albums.media.storage_read_failed', [
                'album_media_id' => $media->id,
                'path' => $media->original_path,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        if ($binary === null || $binary === '') {
            $this->markFailed($media, 'original_missing');

            return;
        }

        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            $this->markFailed($media, 'decode_failed');

            return;
        }

        if (function_exists('imagepalettetotruecolor') && ! imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }

        $width = imagesx($src);
        $height = imagesy($src);

        try {
            $this->generateDerivatives($disk, $album, $media, $src, $width, $height);
        } catch (Throwable $e) {
            $this->markFailed($media, 'processing_exception', $e);
            Log::error('albums.media.photo_process_exception', [
                'album_media_id' => $media->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function generateDerivatives(
        Filesystem $disk,
        Album $album,
        AlbumMedia $media,
        \GdImage $src,
        int $width,
        int $height,
    ): void {
        $thumbMax = max(80, min(2400, (int) $album->thumb_width));
        $quality = max(20, min(100, (int) $album->thumb_quality));
        $mediumMax = (int) config('services.albums.medium_max_width', 1200);

        $thumbPath = 'albums/'.$album->id.'/thumbs/'.$media->id.'.webp';
        $mediumPath = 'albums/'.$album->id.'/medium/'.$media->id.'.webp';

        $thumb = null;
        $medium = null;

        try {
            $thumb = $this->scaleToMaxWidth($src, $thumbMax);
            $medium = $this->scaleToMaxWidth($src, $mediumMax);

            $thumbBytes = $this->encodeWebp($thumb, $quality);
            $mediumBytes = $this->encodeWebp($medium, min(90, $quality + 5));

            if ($thumbBytes === '' || $mediumBytes === '') {
                $this->markFailed($media, 'encode_webp_failed');

                return;
            }

            $disk->put($thumbPath, $thumbBytes);
            $disk->put($mediumPath, $mediumBytes);
        } finally {
            imagedestroy($src);
            if ($thumb instanceof \GdImage) {
                imagedestroy($thumb);
            }
            if ($medium instanceof \GdImage) {
                imagedestroy($medium);
            }
        }

        $media->forceFill([
            'thumb_path' => $thumbPath,
            'medium_path' => $mediumPath,
            'width' => $width,
            'height' => $height,
            'processing_status' => 'done',
            'metadata' => array_merge($media->metadata ?? [], [
                'processed_at' => now()->toIso8601String(),
            ]),
        ])->save();

        Log::info('albums.media.photo_processed', [
            'album_media_id' => $media->id,
            'album_id' => $album->id,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('albums.media.photo_process_failed', [
            'album_media_id' => $this->albumMediaId,
            'message' => $exception?->getMessage(),
        ]);

        AlbumMedia::query()->whereKey($this->albumMediaId)->update([
            'processing_status' => 'failed',
        ]);
    }

    /**
     * @param  \GdImage|resource  $src
     */
    private function scaleToMaxWidth($src, int $maxWidth): \GdImage
    {
        $w = imagesx($src);
        $target = min($w, $maxWidth);
        $scaled = imagescale($src, $target);
        if ($scaled === false) {
            throw new \RuntimeException('imagescale_failed');
        }

        return $scaled;
    }

    /**
     * @param  \GdImage|resource  $img
     */
    private function encodeWebp($img, int $quality): string
    {
        ob_start();
        imagewebp($img, null, $quality);
        $data = ob_get_clean();

        return $data !== false ? $data : '';
    }

    private function markFailed(AlbumMedia $media, string $reason, ?Throwable $previous = null): void
    {
        $metadata = array_merge($media->metadata ?? [], [
            'processing_error' => $reason,
        ]);

        if ($previous !== null) {
            $metadata['processing_error_detail'] = mb_substr($previous->getMessage(), 0, 500);
        }

        $media->forceFill([
            'processing_status' => 'failed',
            'metadata' => $metadata,
        ])->save();

        Log::warning('albums.media.photo_process_aborted', [
            'album_media_id' => $media->id,
            'reason' => $reason,
        ]);
    }
}
