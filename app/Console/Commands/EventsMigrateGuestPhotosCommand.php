<?php

namespace App\Console\Commands;

use App\Models\Guest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class EventsMigrateGuestPhotosCommand extends Command
{
    protected $signature = 'events:migrate-guest-photos {--dry-run : Só reporta, sem copiar}';

    protected $description = 'Copia as fotos de convidados do disco local para o disco configurado (MinIO/S3)';

    /**
     * Migração one-off pós-M0: fotos antigas ficaram no disco local.
     * Mantém o mesmo path — nenhuma coluna precisa ser atualizada.
     */
    public function handle(): int
    {
        $targetDisk = (string) config('events.guest_photos_disk', 's3');
        $dryRun = (bool) $this->option('dry-run');

        if ($targetDisk === 'local') {
            $this->warn('EVENTS_GUEST_PHOTOS_DISK está como local — nada a migrar.');

            return self::SUCCESS;
        }

        $copied = 0;
        $skipped = 0;
        $missing = 0;

        Guest::query()
            ->whereNotNull('photo_path')
            ->chunkById(200, function ($guests) use ($targetDisk, $dryRun, &$copied, &$skipped, &$missing): void {
                foreach ($guests as $guest) {
                    $path = (string) $guest->photo_path;

                    if (Storage::disk($targetDisk)->exists($path)) {
                        $skipped++;

                        continue;
                    }

                    if (! Storage::disk('local')->exists($path)) {
                        $missing++;
                        $this->warn("Sem arquivo local nem remoto: {$path} (guest {$guest->id})");

                        continue;
                    }

                    if (! $dryRun) {
                        Storage::disk($targetDisk)->put($path, Storage::disk('local')->readStream($path));
                    }

                    $copied++;
                }
            });

        $this->info(sprintf(
            '%s: %d copiadas, %d já existentes, %d sem arquivo local.',
            $dryRun ? '[dry-run]' : 'Migração concluída',
            $copied,
            $skipped,
            $missing,
        ));

        return self::SUCCESS;
    }
}
