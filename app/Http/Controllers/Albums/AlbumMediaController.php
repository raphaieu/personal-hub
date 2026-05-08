<?php

namespace App\Http\Controllers\Albums;

use App\Models\Album;
use App\Models\AlbumMedia;
use App\Services\Albums\AlbumAccessService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AlbumMediaController
{
    public function view(Request $request, string $slug, string $media, AlbumAccessService $accessService): StreamedResponse
    {
        $resource = $this->resolveAndAuthorize($request, $slug, $media, $accessService);
        $disk = Storage::disk('s3');

        $variant = (string) $request->query('variant', '');
        $candidates = $this->candidatesForVariant($resource, $variant);

        [$path, $body] = $this->fetchFirstReadable($disk, $candidates);

        if ($path === null || $body === null) {
            abort(404);
        }

        $mime = $this->mimeTypeForStoredPath($path, (string) $resource->mime_type);

        return $this->streamBody($body, 'inline', $resource->filename_original, $mime);
    }

    public function download(Request $request, string $slug, string $media, AlbumAccessService $accessService): StreamedResponse
    {
        $resource = $this->resolveAndAuthorize($request, $slug, $media, $accessService);
        if (! $resource->album->download_enabled) {
            abort(403);
        }

        $disk = Storage::disk('s3');

        try {
            $body = $disk->get((string) $resource->original_path);
        } catch (Throwable $e) {
            Log::warning('albums.media.download_read_failed', [
                'media_id' => $resource->id,
                'path' => $resource->original_path,
                'exception' => $e->getMessage(),
            ]);
            abort(404);
        }

        if ($body === null || $body === '') {
            abort(404);
        }

        return $this->streamBody(
            $body,
            'attachment',
            (string) $resource->filename_original,
            (string) $resource->mime_type
        );
    }

    private function resolveAndAuthorize(Request $request, string $slug, string $mediaId, AlbumAccessService $accessService): AlbumMedia
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $album = Album::query()->where('slug', $slug)->firstOrFail();
        if (! $accessService->canAccess($request, $album)) {
            abort(403);
        }

        $resource = AlbumMedia::query()
            ->where('id', $mediaId)
            ->where('album_id', $album->id)
            ->firstOrFail();

        return $resource->loadMissing('album');
    }

    /**
     * Decide a ordem dos paths a tentar por variante pedida. Sempre cai no original
     * como último recurso para que a UI nunca fique sem imagem enquanto thumb/medium
     * ainda não foram gerados.
     *
     * @return array<int, string>
     */
    private function candidatesForVariant(AlbumMedia $resource, string $variant): array
    {
        $thumb = (string) ($resource->thumb_path ?? '');
        $medium = (string) ($resource->medium_path ?? '');
        $original = (string) ($resource->original_path ?? '');

        $ordered = match ($variant) {
            'thumb' => [$thumb, $medium, $original],
            'medium' => [$medium, $thumb, $original],
            'original' => [$original],
            default => [$medium, $thumb, $original],
        };

        return array_values(array_filter($ordered, static fn ($p) => $p !== ''));
    }

    /**
     * Tenta ler o primeiro candidato que retornar conteúdo. Evita HEAD (alguns proxies
     * bloqueiam). Falhas por path inexistente / IO viram skip e tentam o próximo.
     *
     * @param  array<int, string>  $candidates
     * @return array{0: ?string, 1: ?string}
     */
    private function fetchFirstReadable(Filesystem $disk, array $candidates): array
    {
        foreach ($candidates as $candidate) {
            try {
                $body = $disk->get($candidate);
            } catch (Throwable $e) {
                Log::warning('albums.media.view_read_failed', [
                    'path' => $candidate,
                    'exception' => $e->getMessage(),
                ]);

                continue;
            }

            if (is_string($body) && $body !== '') {
                return [$candidate, $body];
            }
        }

        return [null, null];
    }

    private function mimeTypeForStoredPath(string $path, string $fallbackMime): string
    {
        $lower = strtolower($path);

        return match (true) {
            str_ends_with($lower, '.webp') => 'image/webp',
            str_ends_with($lower, '.png') => 'image/png',
            str_ends_with($lower, '.gif') => 'image/gif',
            str_ends_with($lower, '.jpg') || str_ends_with($lower, '.jpeg') => 'image/jpeg',
            str_ends_with($lower, '.mp4') => 'video/mp4',
            str_ends_with($lower, '.mov') => 'video/quicktime',
            str_ends_with($lower, '.webm') => 'video/webm',
            default => $fallbackMime !== '' ? $fallbackMime : 'application/octet-stream',
        };
    }

    private function streamBody(string $body, string $disposition, string $filename, string $mimeType): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($body): void {
                echo $body;
            },
            $filename !== '' ? $filename : 'media-file',
            [
                'Content-Type' => $mimeType !== '' ? $mimeType : 'application/octet-stream',
                'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            ],
            $disposition
        );
    }
}
