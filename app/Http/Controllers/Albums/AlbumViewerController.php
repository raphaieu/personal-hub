<?php

namespace App\Http\Controllers\Albums;

use App\Models\Album;
use App\Services\Albums\AlbumAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

final class AlbumViewerController
{
    public function show(Request $request, string $slug, AlbumAccessService $accessService): View
    {
        $album = Album::query()
            ->with([
                'parent',
                'media' => fn ($query) => $query->orderBy('sort_position')->orderBy('created_at'),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        if ($album->is_locked) {
            abort(404);
        }

        $canAccess = $accessService->canAccess($request, $album);
        if (! $canAccess && (string) $album->access_type === 'password') {
            return view('albums.password', ['album' => $album]);
        }
        if (! $canAccess) {
            abort(404);
        }

        $mediaItems = $album->media->map(function ($media) use ($album): array {
            $viewUrl = URL::temporarySignedRoute(
                'albums.media.view',
                now()->addMinutes(15),
                ['slug' => $album->slug, 'media' => $media->id]
            );

            $downloadUrl = URL::temporarySignedRoute(
                'albums.media.download',
                now()->addMinutes(15),
                ['slug' => $album->slug, 'media' => $media->id]
            );

            return [
                'id' => $media->id,
                'type' => $media->type,
                'filename_original' => $media->filename_original,
                'processing_status' => $media->processing_status,
                'view_url' => $viewUrl,
                'download_url' => $downloadUrl,
            ];
        });

        return view('albums.viewer', [
            'album' => $album,
            'mediaItems' => $mediaItems,
        ]);
    }

    public function auth(Request $request, string $slug, AlbumAccessService $accessService): RedirectResponse
    {
        $album = Album::query()->where('slug', $slug)->firstOrFail();
        if ($album->is_locked) {
            abort(404);
        }

        $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        $ok = $accessService->authenticateWithPassword($request, $album, (string) $request->input('password'));
        if (! $ok) {
            return back()->withErrors(['password' => 'Senha inválida.'])->withInput();
        }

        return redirect()->route('albums.viewer', ['slug' => $album->slug]);
    }
}
