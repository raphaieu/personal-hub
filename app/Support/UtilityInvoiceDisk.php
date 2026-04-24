<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Acesso ao disco de PDFs de fatura (local ou s3/MinIO).
 * Falhas de rede/credencial no driver S3 costumam lançar em {@see \Illuminate\Filesystem\FilesystemAdapter::exists()}
 * ({@see \League\Flysystem\UnableToCheckFileExistence}): tratamos como "indisponível" para não quebrar UI HTTP/Livewire.
 */
final class UtilityInvoiceDisk
{
    public static function diskName(): string
    {
        return (string) config('services.utilities.pdf_storage_disk', 'local');
    }

    public static function exists(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        try {
            return Storage::disk(self::diskName())->exists($path);
        } catch (Throwable $e) {
            Log::warning('utilities.invoice_disk.exists_failed', [
                'disk' => self::diskName(),
                'path' => $path,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function get(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        try {
            return Storage::disk(self::diskName())->get($path);
        } catch (Throwable $e) {
            Log::warning('utilities.invoice_disk.get_failed', [
                'disk' => self::diskName(),
                'path' => $path,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
