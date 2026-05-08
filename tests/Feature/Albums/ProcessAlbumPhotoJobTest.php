<?php

namespace Tests\Feature\Albums;

use App\Jobs\Albums\ProcessAlbumPhotoJob;
use App\Models\Album;
use App\Models\AlbumMedia;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;
use Mockery;
use Tests\TestCase;

final class ProcessAlbumPhotoJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_generates_derivatives_and_sets_done(): void
    {
        Storage::fake('s3');

        $album = Album::query()->create([
            'slug' => 'job-album',
            'title' => 'Job Album',
            'access_type' => 'public',
            'thumb_width' => 200,
            'thumb_quality' => 85,
        ]);

        $jpg = $this->makeJpegBinary(120, 80);

        /** @var AlbumMedia $created */
        $created = AlbumMedia::query()->create([
            'album_id' => $album->id,
            'type' => 'photo',
            'original_path' => 'pending',
            'filename_original' => 'x.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($jpg),
            'processing_status' => 'pending',
            'uploaded_by' => 'admin',
        ]);

        $mediaId = $created->id;
        $originalPath = 'albums/'.$album->id.'/original/'.$mediaId.'.jpg';
        Storage::disk('s3')->put($originalPath, $jpg);
        $created->forceFill(['original_path' => $originalPath])->save();

        $job = new ProcessAlbumPhotoJob($mediaId);
        $job->handle();

        $media = AlbumMedia::query()->findOrFail($mediaId);
        $this->assertSame('done', $media->processing_status);
        $this->assertNotNull($media->thumb_path);
        $this->assertNotNull($media->medium_path);
        $this->assertTrue(Storage::disk('s3')->exists((string) $media->thumb_path));
        $this->assertTrue(Storage::disk('s3')->exists((string) $media->medium_path));
        $this->assertSame(120, $media->width);
        $this->assertSame(80, $media->height);
    }

    public function test_job_marks_failed_when_original_not_image(): void
    {
        Storage::fake('s3');

        $album = Album::query()->create([
            'slug' => 'job-fail',
            'title' => 'Job Fail',
            'access_type' => 'public',
        ]);

        /** @var AlbumMedia $created */
        $created = AlbumMedia::query()->create([
            'album_id' => $album->id,
            'type' => 'photo',
            'original_path' => 'pending',
            'filename_original' => 'bad.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
            'processing_status' => 'pending',
            'uploaded_by' => 'admin',
        ]);

        $mediaId = $created->id;
        $originalPath = 'albums/'.$album->id.'/original/'.$mediaId.'.jpg';
        Storage::disk('s3')->put($originalPath, 'not-an-image');
        $created->forceFill(['original_path' => $originalPath])->save();

        $job = new ProcessAlbumPhotoJob($mediaId);
        $job->handle();

        $media = AlbumMedia::query()->findOrFail($mediaId);
        $this->assertSame('failed', $media->processing_status);
    }

    public function test_job_marks_failed_with_storage_io_error_when_get_throws(): void
    {
        Storage::fake('s3');

        $album = Album::query()->create([
            'slug' => 'job-io',
            'title' => 'Job IO',
            'access_type' => 'public',
        ]);

        /** @var AlbumMedia $created */
        $created = AlbumMedia::query()->create([
            'album_id' => $album->id,
            'type' => 'photo',
            'original_path' => 'albums/'.$album->id.'/original/io.jpg',
            'filename_original' => 'io.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1,
            'processing_status' => 'pending',
            'uploaded_by' => 'admin',
        ]);

        $mock = Mockery::mock(Filesystem::class);
        $mock->shouldReceive('get')
            ->andThrow(UnableToReadFile::fromLocation('x', 'forbidden'));
        Storage::set('s3', $mock);

        $job = new ProcessAlbumPhotoJob($created->id);
        $job->handle();

        $media = AlbumMedia::query()->findOrFail($created->id);
        $this->assertSame('failed', $media->processing_status);
        $this->assertSame('storage_io_error', $media->metadata['processing_error'] ?? null);
        $this->assertNotEmpty($media->metadata['processing_error_detail'] ?? null);
    }

    private function makeJpegBinary(int $width, int $height): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 100, 50));
        ob_start();
        imagejpeg($img, null, 90);
        imagedestroy($img);

        return ob_get_clean() ?: '';
    }
}
