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
        'mp4' => ['video/mp4', 'audio/mp4'],
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

        Storage::disk('s3')->put($path, (string) file_get_contents($file->getRealPath()));

        return $this->finalizeNewMedia(
            $album,
            $id,
            $path,
            $file->getClientOriginalName(),
            $mime,
            $file->getSize(),
            $contributor,
            $ext
        );
    }

    /**
     * Consome arquivo já salvo no disco `local` (ex.: após Livewire mover o upload temporário).
     * Remove o arquivo local após gravação bem-sucedida no S3 e criação do registro.
     *
     * @param  string  $relativeLocalPath  Caminho relativo ao root do disco `local`.
     */
    public function ingestFromStoredLocalPath(Album $album, string $relativeLocalPath, string $originalFilename): AlbumMedia
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($relativeLocalPath)) {
            throw ValidationException::withMessages([
                'uploadFiles' => 'Arquivo temporário não encontrado ou expirado. Tente enviar novamente.',
            ]);
        }

        try {
            $fullPath = $disk->path($relativeLocalPath);
            $size = (int) (@filesize($fullPath) ?: 0);

            $maxBytes = (int) config('services.albums.max_upload_bytes');
            if ($size > $maxBytes) {
                throw ValidationException::withMessages([
                    'uploadFiles' => 'Cada arquivo deve ter no máximo '.round($maxBytes / (1024 * 1024), 0).' MB.',
                ]);
            }

            $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            $mime = $this->detectMime($fullPath);
            if (
                ($mime === 'application/octet-stream'
                    || $mime === ''
                    || $mime === 'application/x-empty'
                    || $mime === 'inode/x-empty')
                && isset(self::ALLOWED[$ext][0])
            ) {
                $mime = self::ALLOWED[$ext][0];
            }

            $this->assertAllowed($ext, $mime, 'uploadFiles');

            $id = (string) Str::uuid();
            $path = 'albums/'.$album->id.'/original/'.$id.'.'.$ext;

            Storage::disk('s3')->put($path, (string) file_get_contents($fullPath));

            return $this->finalizeNewMedia(
                $album,
                $id,
                $path,
                $originalFilename,
                $mime,
                $size,
                null,
                $ext
            );
        } finally {
            if ($disk->exists($relativeLocalPath)) {
                $disk->delete($relativeLocalPath);
            }
        }
    }

    private function detectMime(string $fullPath): string
    {
        if (is_file($fullPath) && function_exists('mime_content_type')) {
            $detected = @mime_content_type($fullPath);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return 'application/octet-stream';
    }

    private function finalizeNewMedia(
        Album $album,
        string $id,
        string $originalPathOnS3,
        string $filenameOriginal,
        string $mime,
        int $sizeBytes,
        ?Contributor $contributor,
        string $extensionLower,
    ): AlbumMedia {
        $type = $this->resolveMediaType($extensionLower, $mime);

        $nextSort = (int) (AlbumMedia::query()
            ->where('album_id', $album->id)
            ->max('sort_position') ?? 0) + 1;

        $uploadedBy = $contributor !== null ? 'contributor' : 'admin';

        /** @var AlbumMedia $media */
        $media = AlbumMedia::query()->create([
            'id' => $id,
            'album_id' => $album->id,
            'type' => $type,
            'original_path' => $originalPathOnS3,
            'filename_original' => $filenameOriginal,
            'mime_type' => $mime,
            'size_bytes' => $sizeBytes,
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

    private function resolveMediaType(string $extensionLower, string $mime): string
    {
        if (in_array($extensionLower, ['mp4', 'mov', 'webm'], true)) {
            return 'video';
        }

        return str_starts_with($mime, 'video/') ? 'video' : 'photo';
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
