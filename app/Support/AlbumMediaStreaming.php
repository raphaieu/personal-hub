<?php

namespace App\Support;

use App\Models\AlbumMedia;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AlbumMediaStreaming
{
    /**
     * @param  array<int, string>  $candidates
     * @return array{0: ?string, 1: ?string}
     */
    public static function fetchFirstReadable(Filesystem $disk, array $candidates): array
    {
        foreach ($candidates as $candidate) {
            try {
                $body = $disk->get($candidate);
            } catch (Throwable $e) {
                Log::warning('albums.media.view_read_failed', [
                    'path' => $candidate,
                    'exception' => $e->getMessage(),
                ]);

                continue;
            }

            if (is_string($body) && $body !== '') {
                return [$candidate, $body];
            }
        }

        return [null, null];
    }

    /**
     * @return array<int, string>
     */
    public static function candidatesForVariant(AlbumMedia $resource, string $variant): array
    {
        $thumb = (string) ($resource->thumb_path ?? '');
        $medium = (string) ($resource->medium_path ?? '');
        $original = (string) ($resource->original_path ?? '');

        $ordered = match ($variant) {
            'thumb' => [$thumb, $medium, $original],
            'medium' => [$medium, $thumb, $original],
            'original' => [$original],
            default => [$medium, $thumb, $original],
        };

        return array_values(array_filter($ordered, static fn ($p) => $p !== ''));
    }

    public static function mimeTypeForStoredPath(string $path, string $fallbackMime): string
    {
        $lower = strtolower($path);

        return match (true) {
            str_ends_with($lower, '.webp') => 'image/webp',
            str_ends_with($lower, '.png') => 'image/png',
            str_ends_with($lower, '.gif') => 'image/gif',
            str_ends_with($lower, '.jpg'), str_ends_with($lower, '.jpeg') => 'image/jpeg',
            str_ends_with($lower, '.mp4') => 'video/mp4',
            str_ends_with($lower, '.mov') => 'video/quicktime',
            str_ends_with($lower, '.webm') => 'video/webm',
            default => $fallbackMime !== '' ? $fallbackMime : 'application/octet-stream',
        };
    }

    public static function streamBody(
        string $body,
        string $disposition,
        string $filename,
        string $mimeType,
    ): StreamedResponse {
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

    public static function safeDownloadFilename(string $displayOrOriginal): string
    {
        $trimmed = trim($displayOrOriginal);
        if ($trimmed === '') {
            return 'media-file';
        }

        return preg_replace('/[^\p{L}\p{N}._\- ]/u', '_', $trimmed) ?: 'media-file';
    }
}
