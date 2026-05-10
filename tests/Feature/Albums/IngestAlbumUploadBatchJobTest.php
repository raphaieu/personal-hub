<?php

namespace Tests\Feature\Albums;

use App\Jobs\Albums\IngestAlbumUploadBatchJob;
use App\Jobs\Albums\ProcessAlbumPhotoJob;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Services\Albums\AlbumMediaUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class IngestAlbumUploadBatchJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_ingests_local_files_and_dispatches_photo_processing(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        Queue::fake();

        $album = Album::query()->create([
            'slug' => 'batch-job',
            'title' => 'Batch Job',
            'access_type' => 'public',
            'thumb_width' => 400,
            'thumb_quality' => 80,
        ]);

        $fake = UploadedFile::fake()->image('test.jpg', 80, 60);
        $relative = $fake->storeAs('album-ingest/'.$album->id.'/'.Str::uuid(), 'test.jpg', 'local');

        $job = new IngestAlbumUploadBatchJob((string) $album->id, [
            ['path' => $relative, 'original' => 'test.jpg'],
        ]);

        $job->handle(app(AlbumMediaUploadService::class));

        $media = AlbumMedia::query()->where('album_id', $album->id)->first();
        $this->assertNotNull($media);
        $this->assertSame('photo', $media->type);
        $this->assertFalse(Storage::disk('local')->exists($relative));

        Queue::assertPushed(ProcessAlbumPhotoJob::class, fn (ProcessAlbumPhotoJob $j): bool => $j->albumMediaId === $media->id);
    }

    public function test_ingest_from_local_path_removes_file_when_type_invalid(): void
    {
        Storage::fake('local');
        Storage::fake('s3');

        $album = Album::query()->create([
            'slug' => 'reject-type',
            'title' => 'Reject',
            'access_type' => 'public',
        ]);

        $relative = 'album-ingest/'.$album->id.'/'.Str::uuid().'/bad.exe';
        Storage::disk('local')->put($relative, "MZ\x90\x00");

        try {
            app(AlbumMediaUploadService::class)->ingestFromStoredLocalPath($album, $relative, 'bad.exe');
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
            //
        }

        $this->assertFalse(Storage::disk('local')->exists($relative));
    }

    public function test_batch_job_removes_ingest_directory_after_processing(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        Queue::fake();

        $album = Album::query()->create([
            'slug' => 'batch-dir',
            'title' => 'Batch Dir',
            'access_type' => 'public',
            'thumb_width' => 400,
            'thumb_quality' => 80,
        ]);

        $fake = UploadedFile::fake()->image('x.jpg', 40, 40);
        $relative = $fake->storeAs('album-ingest/'.$album->id.'/'.Str::uuid(), 'x.jpg', 'local');
        $batchDir = dirname($relative);

        $job = new IngestAlbumUploadBatchJob((string) $album->id, [
            ['path' => $relative, 'original' => 'x.jpg'],
        ]);

        $job->handle(app(AlbumMediaUploadService::class));

        $this->assertFalse(Storage::disk('local')->exists($batchDir));
    }
}
