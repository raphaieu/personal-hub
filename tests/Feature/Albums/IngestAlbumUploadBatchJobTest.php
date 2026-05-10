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
}
