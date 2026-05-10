<?php

namespace App\Support;

/**
 * Limites reais de upload multipart: o PHP impõe um teto por requisição (`max_file_uploads`,
 * em muitos ambientes o padrão é 20). O aplicativo também pode limitar via config.
 */
final class AlbumUploadLimits
{
    /**
     * Quantidade máxima de arquivos aceitos num único envio HTTP (menor entre config e PHP).
     */
    public static function maxFilesPerHttpRequest(): int
    {
        $configured = max(1, (int) config('services.albums.max_files_per_batch'));

        return min($configured, self::phpMaxFileUploads());
    }

    /**
     * Valor atual de `max_file_uploads` no PHP (mínimo 1 para validação).
     */
    public static function phpMaxFileUploads(): int
    {
        $raw = ini_get('max_file_uploads');

        return is_numeric($raw) ? max(1, (int) $raw) : 20;
    }
}
