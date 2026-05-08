<?php

namespace Tests\Feature\Albums;

use App\Jobs\Albums\ProcessAlbumPhotoJob;
use App\Livewire\Albums\AlbumDetailPage;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class AlbumHubMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_photo_persists_on_s3_and_dispatches_processing_job(): void
    {
        Storage::fake('s3');
        Queue::fake();

        $user = User::factory()->create();
        $album = Album::query()->create([
            'slug' => 'hub-upload',
            'title' => 'Hub Upload',
            'access_type' => 'public',
            'thumb_width' => 400,
            'thumb_quality' => 80,
        ]);

        $file = UploadedFile::fake()->image('foto.jpg', 640, 480);

        Livewire::actingAs($user)
            ->test(AlbumDetailPage::class, ['album' => $album])
            ->set('uploadFiles', [$file])
            ->call('uploadMedia')
            ->assertHasNoErrors();

        $media = AlbumMedia::query()->where('album_id', $album->id)->first();
        $this->assertNotNull($media);
        $this->assertSame('photo', $media->type);
        $this->assertSame('pending', $media->processing_status);
        $this->assertTrue(Storage::disk('s3')->exists($media->original_path));

        Queue::assertPushed(ProcessAlbumPhotoJob::class, function (ProcessAlbumPhotoJob $job) use ($media): bool {
            return $job->albumMediaId === $media->id
                && $job->queue === 'media';
        });
    }

    public function test_upload_video_is_marked_done_without_photo_job(): void
    {
        Storage::fake('s3');
        Queue::fake();

        $user = User::factory()->create();
        $album = Album::query()->create([
            'slug' => 'hub-video',
            'title' => 'Hub Video',
            'access_type' => 'public',
        ]);

        $file = UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4');

        Livewire::actingAs($user)
            ->test(AlbumDetailPage::class, ['album' => $album])
            ->set('uploadFiles', [$file])
            ->call('uploadMedia')
            ->assertHasNoErrors();

        $media = AlbumMedia::query()->where('album_id', $album->id)->first();
        $this->assertNotNull($media);
        $this->assertSame('video', $media->type);
        $this->assertSame('done', $media->processing_status);

        Queue::assertNotPushed(ProcessAlbumPhotoJob::class);
    }

    public function test_guest_cannot_open_album_detail_hub(): void
    {
        $album = Album::query()->create([
            'slug' => 'x',
            'title' => 'X',
            'access_type' => 'public',
        ]);

        $this->get(route('albums.hub.show', $album))->assertRedirect();
    }

    public function test_reprocess_failed_photo_resets_status_and_dispatches_job(): void
    {
        Storage::fake('s3');
        Queue::fake();

        $user = User::factory()->create();
        $album = Album::query()->create([
            'slug' => 'reprocess',
            'title' => 'Reprocess',
            'access_type' => 'public',
        ]);

        /** @var AlbumMedia $media */
        $media = AlbumMedia::query()->create([
            'album_id' => $album->id,
            'type' => 'photo',
            'original_path' => 'albums/'.$album->id.'/original/x.jpg',
            'filename_original' => 'x.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'processing_status' => 'failed',
            'thumb_path' => 'albums/'.$album->id.'/thumbs/x.webp',
            'medium_path' => 'albums/'.$album->id.'/medium/x.webp',
        ]);

        Livewire::actingAs($user)
            ->test(AlbumDetailPage::class, ['album' => $album])
            ->call('reprocessMedia', $media->id)
            ->assertHasNoErrors();

        $fresh = $media->fresh();
        $this->assertSame('pending', $fresh->processing_status);
        $this->assertNull($fresh->thumb_path);
        $this->assertNull($fresh->medium_path);

        Queue::assertPushed(
            ProcessAlbumPhotoJob::class,
            fn (ProcessAlbumPhotoJob $job) => $job->albumMediaId === $media->id
        );
    }

    public function test_reprocess_does_not_run_for_video(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $album = Album::query()->create([
            'slug' => 'video-no-rep',
            'title' => 'Video No Rep',
            'access_type' => 'public',
        ]);

        /** @var AlbumMedia $media */
        $media = AlbumMedia::query()->create([
            'album_id' => $album->id,
            'type' => 'video',
            'original_path' => 'albums/'.$album->id.'/original/x.mp4',
            'filename_original' => 'x.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 100,
            'processing_status' => 'failed',
        ]);

        Livewire::actingAs($user)
            ->test(AlbumDetailPage::class, ['album' => $album])
            ->call('reprocessMedia', $media->id)
            ->assertHasNoErrors();

        Queue::assertNotPushed(ProcessAlbumPhotoJob::class);
        $this->assertSame('failed', $media->fresh()->processing_status);
    }

    public function test_delete_media_removes_objects_from_s3_and_database(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $album = Album::query()->create([
            'slug' => 'delete-me',
            'title' => 'Delete Me',
            'access_type' => 'public',
        ]);

        /** @var AlbumMedia $media */
        $media = AlbumMedia::query()->create([
            'album_id' => $album->id,
            'type' => 'photo',
            'original_path' => 'albums/'.$album->id.'/original/o.jpg',
            'thumb_path' => 'albums/'.$album->id.'/thumbs/t.webp',
            'medium_path' => 'albums/'.$album->id.'/medium/m.webp',
            'filename_original' => 'o.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1,
            'processing_status' => 'done',
        ]);

        Storage::disk('s3')->put($media->original_path, 'a');
        Storage::disk('s3')->put($media->thumb_path, 'b');
        Storage::disk('s3')->put($media->medium_path, 'c');

        $album->forceFill(['cover_media_id' => $media->id])->save();

        Livewire::actingAs($user)
            ->test(AlbumDetailPage::class, ['album' => $album])
            ->call('deleteMedia', $media->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('album_media', ['id' => $media->id]);
        $this->assertFalse(Storage::disk('s3')->exists($media->original_path));
        $this->assertFalse(Storage::disk('s3')->exists($media->thumb_path));
        $this->assertFalse(Storage::disk('s3')->exists($media->medium_path));
        $this->assertNull($album->fresh()->cover_media_id);
    }

    public function test_delete_only_removes_media_owned_by_album(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $albumA = Album::query()->create(['slug' => 'a', 'title' => 'A', 'access_type' => 'public']);
        $albumB = Album::query()->create(['slug' => 'b', 'title' => 'B', 'access_type' => 'public']);

        $mediaB = AlbumMedia::query()->create([
            'album_id' => $albumB->id,
            'type' => 'photo',
            'original_path' => 'albums/'.$albumB->id.'/original/o.jpg',
            'filename_original' => 'o.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1,
            'processing_status' => 'done',
        ]);

        Livewire::actingAs($user)
            ->test(AlbumDetailPage::class, ['album' => $albumA])
            ->call('deleteMedia', $mediaB->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('album_media', ['id' => $mediaB->id]);
    }
}
