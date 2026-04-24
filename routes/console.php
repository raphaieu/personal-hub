<?php

use App\Jobs\ScrapeConta;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'utilities:scrape {kind : embasa ou coelba} {--force : Ignora a heurística e chama o Playwright} {--ignore-window : Ignora a janela UtilityScrapeWindow}',
    function (): int {
        $kind = strtolower((string) $this->argument('kind'));
        if (! in_array($kind, ['embasa', 'coelba'], true)) {
            $this->error('Use embasa ou coelba.');

            return 1;
        }

        ScrapeConta::dispatch(
            $kind,
            (bool) $this->option('ignore-window'),
            (bool) $this->option('force'),
        );

        $this->info(sprintf(
            'Job ScrapeConta enfileirado (fila scraping): kind=%s ignore-window=%s force=%s',
            $kind,
            $this->option('ignore-window') ? 'sim' : 'não',
            $this->option('force') ? 'sim' : 'não',
        ));

        return 0;
    }
)->purpose('Enfileira scrape Embasa/Coelba (manual: use --force para sempre chamar o Playwright)');
