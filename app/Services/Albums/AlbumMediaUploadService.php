<?php

namespace App\Services\Albums;

use App\Jobs\Albums\ProcessAlbumPhotoJob;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Models\Contributor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AlbumMediaUploadService
{
    /**
     * Extensão (lowercase) => lista de MIME aceitos.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'mp4' => ['video/mp4'],
        'mov' => ['video/quicktime'],
        'webm' => ['video/webm'],
    ];

    public function store(Album $album, UploadedFile $file, ?Contributor $contributor = null, string $fileFieldKey = 'uploadFiles'): AlbumMedia
    {
        $maxBytes = (int) config('services.albums.max_upload_bytes');
        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                $fileFieldKey => 'Cada arquivo deve ter no máximo '.round($maxBytes / (1024 * 1024), 0).' MB.',
            ]);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType() ?: 'application/octet-stream';

        $this->assertAllowed($ext, $mime, $fileFieldKey);

        $id = (string) Str::uuid();
        $path = 'albums/'.$album->id.'/original/'.$id.'.'.$ext;

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        $type = str_starts_with($mime, 'video/') ? 'video' : 'photo';

        $nextSort = (int) (AlbumMedia::query()
            ->where('album_id', $album->id)
            ->max('sort_position') ?? 0) + 1;

        $uploadedBy = $contributor !== null ? 'contributor' : 'admin';

        /** @var AlbumMedia $media */
        $media = AlbumMedia::query()->create([
            'id' => $id,
            'album_id' => $album->id,
            'type' => $type,
            'original_path' => $path,
            'filename_original' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
            'processing_status' => $type === 'video' ? 'done' : 'pending',
            'uploaded_by' => $uploadedBy,
            'contributor_id' => $contributor?->id,
            'sort_position' => $nextSort,
        ]);

        if ($type === 'photo') {
            ProcessAlbumPhotoJob::dispatch($media->id);
        }

        $media = $media->fresh() ?? $media;

        if ($contributor !== null) {
            app(AlbumContributionDigestService::class)->recordUploadedMedia($album, $contributor, $media);
        }

        return $media;
    }

    private function assertAllowed(string $extension, string $mime, string $fileFieldKey = 'uploadFiles'): void
    {
        $allowedMimes = self::ALLOWED[$extension] ?? null;
        if ($allowedMimes === null || ! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                $fileFieldKey => 'Tipo de arquivo não permitido. Use imagens (JPEG, PNG, WebP, GIF) ou vídeo (MP4, MOV, WebM).',
            ]);
        }
    }
}
