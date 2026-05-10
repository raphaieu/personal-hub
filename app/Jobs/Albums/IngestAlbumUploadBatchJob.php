<?php

namespace App\Jobs\Albums;

use App\Models\Album;
use App\Services\Albums\AlbumMediaUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Processa em fila os arquivos já gravados no disco local pelo Livewire,
 * enviando ao S3 e criando registros de mídia (um ProcessAlbumPhotoJob por foto).
 *
 * @phpstan-type IngestFile array{path: string, original: string}
 */
final class IngestAlbumUploadBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<IngestFile>  $files  Paths relativos ao disco `local` (storage/app/private).
     */
    public function __construct(
        public readonly string $albumId,
        public readonly array $files,
    ) {}

    public function handle(AlbumMediaUploadService $uploadService): void
    {
        $album = Album::query()->find($this->albumId);
        if ($album === null) {
            Log::warning('albums.ingest_batch.album_missing', ['album_id' => $this->albumId]);

            return;
        }

        foreach ($this->files as $entry) {
            $path = (string) ($entry['path'] ?? '');
            $original = (string) ($entry['original'] ?? '');
            if ($path === '' || $original === '') {
                continue;
            }

            try {
                $uploadService->ingestFromStoredLocalPath($album, $path, $original);
            } catch (Throwable $e) {
                Log::error('albums.ingest_batch.file_failed', [
                    'album_id' => $album->id,
                    'path' => $path,
                    'original' => $original,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->cleanupIngestBatchDirectories();
    }

    /**
     * Remove pastas vazias ou sobras do lote em album-ingest/{albumId}/{batchUuid}/.
     */
    private function cleanupIngestBatchDirectories(): void
    {
        $dirs = [];
        foreach ($this->files as $entry) {
            $path = (string) ($entry['path'] ?? '');
            if ($path === '' || ! str_contains($path, '/')) {
                continue;
            }
            $parent = dirname($path);
            if (str_starts_with($parent, 'album-ingest/')) {
                $dirs[$parent] = true;
            }
        }

        $disk = Storage::disk('local');
        foreach (array_keys($dirs) as $dir) {
            try {
                if ($disk->exists($dir)) {
                    $disk->deleteDirectory($dir);
                }
            } catch (Throwable $e) {
                Log::warning('albums.ingest_batch.cleanup_dir_failed', [
                    'dir' => $dir,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('albums.ingest_batch.job_failed', [
            'album_id' => $this->albumId,
            'files_count' => count($this->files),
            'message' => $exception?->getMessage(),
        ]);
    }
}
