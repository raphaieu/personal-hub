<?php

namespace App\Http\Controllers\Albums;

use App\Models\Album;
use App\Models\AlbumMedia;
use App\Services\Albums\AlbumAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AlbumMediaController
{
    public function view(Request $request, string $slug, string $media, AlbumAccessService $accessService): StreamedResponse
    {
        $resource = $this->resolveAndAuthorize($request, $slug, $media, $accessService);
        $path = $resource->thumb_path ?: $resource->medium_path ?: $resource->original_path;

        return $this->stream($path, 'inline', $resource->filename_original, (string) $resource->mime_type);
    }

    public function download(Request $request, string $slug, string $media, AlbumAccessService $accessService): StreamedResponse
    {
        $resource = $this->resolveAndAuthorize($request, $slug, $media, $accessService);
        if (! $resource->album->download_enabled) {
            abort(403);
        }

        return $this->stream(
            (string) $resource->original_path,
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

    private function stream(string $path, string $disposition, string $filename, string $mimeType): StreamedResponse
    {
        if ($path === '' || ! Storage::disk('s3')->exists($path)) {
            abort(404);
        }

        $body = Storage::disk('s3')->get($path);

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
