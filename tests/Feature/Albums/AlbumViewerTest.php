<?php

namespace Tests\Feature\Albums;

use App\Models\Album;
use App\Models\AlbumMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class AlbumViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_album_viewer_is_accessible(): void
    {
        $album = Album::query()->create([
            'slug' => 'album-publico',
            'title' => 'Album Publico',
            'access_type' => 'public',
        ]);

        $this->get(route('albums.viewer', ['slug' => $album->slug]))
            ->assertOk()
            ->assertSee('Album Publico');
    }

    public function test_password_album_requires_auth_screen(): void
    {
        $album = Album::query()->create([
            'slug' => 'album-senha',
            'title' => 'Album Senha',
            'access_type' => 'password',
            'password_hash' => bcrypt('1234'),
        ]);

        $this->get(route('albums.viewer', ['slug' => $album->slug]))
            ->assertOk()
            ->assertSee('protegido por senha');
    }

    public function test_password_album_authenticates_and_allows_viewer(): void
    {
        $album = Album::query()->create([
            'slug' => 'album-auth',
            'title' => 'Album Auth',
            'access_type' => 'password',
            'password_hash' => bcrypt('1234'),
        ]);

        $this->post(route('albums.viewer.auth', ['slug' => $album->slug]), [
            'password' => '1234',
        ])->assertRedirect(route('albums.viewer', ['slug' => $album->slug]));

        $this->get(route('albums.viewer', ['slug' => $album->slug]))
            ->assertOk()
            ->assertSee('Album Auth');
    }

    public function test_locked_album_returns_404(): void
    {
        $album = Album::query()->create([
            'slug' => 'album-lock',
            'title' => 'Album Lock',
            'access_type' => 'public',
            'is_locked' => true,
        ]);

        $this->get(route('albums.viewer', ['slug' => $album->slug]))
            ->assertNotFound();
    }

    public function test_media_view_requires_signed_url_and_streams_file(): void
    {
        Storage::fake('s3');

        $album = Album::query()->create([
            'slug' => 'album-media',
            'title' => 'Album Media',
            'access_type' => 'public',
        ]);

        $media = AlbumMedia::query()->create([
            'album_id' => $album->id,
            'type' => 'photo',
            'original_path' => 'albums/'.$album->id.'/original/media.jpg',
            'filename_original' => 'media.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1234,
            'processing_status' => 'done',
        ]);

        Storage::disk('s3')->put($media->original_path, 'fake-image-content');

        $this->get(route('albums.media.view', ['slug' => $album->slug, 'media' => $media->id]))
            ->assertForbidden();

        $signed = URL::temporarySignedRoute(
            'albums.media.view',
            now()->addMinutes(15),
            ['slug' => $album->slug, 'media' => $media->id]
        );

        $this->get($signed)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_media_download_is_blocked_when_album_download_disabled(): void
    {
        Storage::fake('s3');

        $album = Album::query()->create([
            'slug' => 'album-no-download',
            'title' => 'Album No Download',
            'access_type' => 'public',
            'download_enabled' => false,
        ]);

        $media = AlbumMedia::query()->create([
            'album_id' => $album->id,
            'type' => 'photo',
            'original_path' => 'albums/'.$album->id.'/original/media.jpg',
            'filename_original' => 'media.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1234,
            'processing_status' => 'done',
        ]);

        Storage::disk('s3')->put($media->original_path, 'fake-image-content');

        $signed = URL::temporarySignedRoute(
            'albums.media.download',
            now()->addMinutes(15),
            ['slug' => $album->slug, 'media' => $media->id]
        );

        $this->get($signed)->assertForbidden();
    }
}
