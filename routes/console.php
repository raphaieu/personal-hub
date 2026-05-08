<?php

use App\Jobs\ScrapeConta;
use App\Models\AnalysisProfile;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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

Artisan::command(
    'analysis:repair-profile-linkage',
    function (): int {
        $slug = AnalysisProfile::THREADS_OPPORTUNITIES_SLUG;
        $now = now();

        $profileId = DB::table('analysis_profiles')
            ->where('slug', $slug)
            ->value('id');

        if (! is_numeric($profileId)) {
            $profileId = DB::table('analysis_profiles')->insertGetId([
                'slug' => $slug,
                'name' => 'Threads Opportunities',
                'description' => 'Perfil padrão para classificar oportunidades de trabalho/renda extra em comentários do Threads.',
                'channel' => 'threads',
                'analysis_type' => 'classification',
                'system_prompt' => (string) \App\Enums\AiTask::ThreadsOpportunityClassification->systemPrompt(),
                'output_schema' => json_encode([
                    'type' => 'object',
                    'required' => ['category_slug', 'summary', 'relevance_score'],
                    'properties' => [
                        'category_slug' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                        'relevance_score' => ['type' => 'number'],
                    ],
                ], JSON_THROW_ON_ERROR),
                'allowed_categories' => json_encode(AnalysisProfile::THREADS_ALLOWED_CATEGORIES, JSON_THROW_ON_ERROR),
                'score_threshold' => 65.00,
                'settings' => json_encode([
                    'ai_task' => \App\Enums\AiTask::ThreadsOpportunityClassification->value,
                    'result_mapping' => [
                        'category' => 'category_slug',
                        'summary' => 'summary',
                        'relevance_score' => 'relevance_score',
                    ],
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! is_numeric($profileId)) {
            $this->error('Não foi possível resolver profile padrão para reparo.');

            return 1;
        }

        $profileId = (int) $profileId;

        $threadsSources = DB::table('threads_sources')
            ->whereNull('analysis_profile_id')
            ->update([
                'analysis_profile_id' => $profileId,
                'updated_at' => $now,
            ]);

        $threadsCategories = DB::table('threads_categories')
            ->whereNull('analysis_profile_id')
            ->update([
                'analysis_profile_id' => $profileId,
                'updated_at' => $now,
            ]);

        $this->info(sprintf(
            'Repair concluído. profile_id=%d | threads_sources=%d | threads_categories=%d',
            $profileId,
            $threadsSources,
            $threadsCategories
        ));

        return 0;
    }
)->purpose('Garante profile padrão de Threads e repara linkage em fontes/categorias legadas');
