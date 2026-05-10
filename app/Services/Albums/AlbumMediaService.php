<?php

namespace App\Services\Albums;

use App\Jobs\Albums\ProcessAlbumPhotoJob;
use App\Models\AlbumMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class AlbumMediaService
{
    /**
     * Apaga a mídia do bucket (todas as variantes) e do banco em força.
     * Limpa também referências como `cover_media_id` no álbum.
     */
    public function delete(AlbumMedia $media): void
    {
        $disk = Storage::disk('s3');
        $album = $media->album;

        $paths = array_values(array_filter([
            $media->original_path,
            $media->thumb_path,
            $media->medium_path,
            $media->video_thumb_path,
        ], static fn ($p) => is_string($p) && $p !== ''));

        foreach ($paths as $path) {
            try {
                $disk->delete($path);
            } catch (Throwable $e) {
                Log::warning('albums.media.delete_object_failed', [
                    'album_media_id' => $media->id,
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        DB::transaction(function () use ($media, $album): void {
            if ($album !== null && (string) $album->cover_media_id === (string) $media->id) {
                $album->forceFill(['cover_media_id' => null])->save();
            }

            $media->forceDelete();
        });

        Log::info('albums.media.deleted', [
            'album_media_id' => $media->id,
            'album_id' => $album?->id,
            'paths' => $paths,
        ]);
    }

    /**
     * Reenfileira o processamento de uma foto que falhou (ou que ficou suja).
     */
    public function reprocess(AlbumMedia $media): void
    {
        if ($media->type !== 'photo') {
            return;
        }

        $media->forceFill([
            'processing_status' => 'pending',
            'thumb_path' => null,
            'medium_path' => null,
            'metadata' => array_merge($media->metadata ?? [], [
                'reprocessed_at' => now()->toIso8601String(),
            ]),
        ])->save();

        ProcessAlbumPhotoJob::dispatch($media->id);
    }
}
