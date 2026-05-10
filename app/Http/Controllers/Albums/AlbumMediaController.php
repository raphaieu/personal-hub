<?php

namespace App\Http\Controllers\Albums;

use App\Models\Album;
use App\Models\AlbumMedia;
use App\Services\Albums\AlbumAccessService;
use App\Support\AlbumMediaStreaming;
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
        $candidates = AlbumMediaStreaming::candidatesForVariant($resource, $variant);

        [$path, $body] = AlbumMediaStreaming::fetchFirstReadable($disk, $candidates);

        if ($path === null || $body === null) {
            abort(404);
        }

        $mime = AlbumMediaStreaming::mimeTypeForStoredPath($path, (string) $resource->mime_type);

        return AlbumMediaStreaming::streamBody(
            $body,
            'inline',
            (string) $resource->filename_original,
            $mime
        );
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

        $downloadBase = ($resource->display_name !== null && trim($resource->display_name) !== '')
            ? trim($resource->display_name)
            : (string) $resource->filename_original;

        $filename = AlbumMediaStreaming::safeDownloadFilename($downloadBase);
        $ext = pathinfo((string) $resource->filename_original, PATHINFO_EXTENSION);
        if ($ext !== '' && ! str_contains(strtolower($filename), '.')) {
            $filename .= '.'.$ext;
        }

        return AlbumMediaStreaming::streamBody(
            $body,
            'attachment',
            $filename,
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
}
