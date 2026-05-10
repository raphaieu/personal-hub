<?php

namespace App\Http\Controllers\Albums;

use App\Models\Album;
use App\Models\AlbumMedia;
use App\Support\AlbumMediaStreaming;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AlbumHubMediaController
{
    /**
     * Pré-visualização autenticada para o hub (miniatura ou variante).
     */
    public function preview(Request $request, Album $album, AlbumMedia $media): StreamedResponse
    {
        abort_unless((string) $media->album_id === (string) $album->id, 404);

        $disk = Storage::disk('s3');
        $variant = (string) $request->query('variant', 'thumb');
        $candidates = AlbumMediaStreaming::candidatesForVariant($media, $variant);

        [$path, $body] = AlbumMediaStreaming::fetchFirstReadable($disk, $candidates);

        if ($path === null || $body === null) {
            abort(404);
        }

        $mime = AlbumMediaStreaming::mimeTypeForStoredPath($path, (string) $media->mime_type);

        return AlbumMediaStreaming::streamBody(
            $body,
            'inline',
            (string) $media->filename_original,
            $mime
        );
    }
}
