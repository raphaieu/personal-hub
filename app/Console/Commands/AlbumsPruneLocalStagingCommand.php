<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class AlbumsPruneLocalStagingCommand extends Command
{
    protected $signature = 'albums:prune-local-staging
                            {--hours=48 : Apagar arquivos mais antigos que este número de horas}
                            {--dry-run : Apenas listar o que seria removido (contagem)}';

    protected $description = 'Remove arquivos antigos em album-ingest e livewire-tmp no disco local (storage/app/private)';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours)->getTimestamp();

        $disk = Storage::disk('local');
        $wouldRemove = 0;
        $removed = 0;

        foreach (['album-ingest', 'livewire-tmp'] as $prefix) {
            if (! $disk->exists($prefix)) {
                continue;
            }

            foreach ($disk->allFiles($prefix) as $path) {
                try {
                    if ($disk->lastModified($path) >= $cutoff) {
                        continue;
                    }
                    $wouldRemove++;
                    if ($dryRun) {
                        continue;
                    }
                    $disk->delete($path);
                    $removed++;
                } catch (Throwable $e) {
                    $this->warn("Ignorado {$path}: {$e->getMessage()}");
                }
            }
        }

        if ($dryRun) {
            $this->info("Dry-run: {$wouldRemove} arquivo(s) com mais de {$hours} h seriam removidos.");

            return 0;
        }

        $this->info("Removidos {$removed} arquivo(s) com mais de {$hours} h em album-ingest/ e livewire-tmp/.");

        return 0;
    }
}
