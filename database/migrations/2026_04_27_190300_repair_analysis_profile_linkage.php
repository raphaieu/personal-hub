<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $slug = 'threads-opportunities';
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
                'system_prompt' => 'Responda em JSON válido para classificar oportunidades no Threads. Campos obrigatórios: category_slug (emprego-fixo|temporario|freela|renda-extra|outros), summary (string curta em pt-BR), relevance_score (número de 0 a 1). Sem markdown.',
                'output_schema' => json_encode([
                    'type' => 'object',
                    'required' => ['category_slug', 'summary', 'relevance_score'],
                    'properties' => [
                        'category_slug' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                        'relevance_score' => ['type' => 'number'],
                    ],
                ], JSON_THROW_ON_ERROR),
                'allowed_categories' => json_encode(['emprego-fixo', 'temporario', 'freela', 'renda-extra', 'outros'], JSON_THROW_ON_ERROR),
                'score_threshold' => 65.00,
                'settings' => json_encode([
                    'ai_task' => 'threads_opportunity_classification',
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
            return;
        }

        $profileId = (int) $profileId;

        DB::table('threads_sources')
            ->whereNull('analysis_profile_id')
            ->update([
                'analysis_profile_id' => $profileId,
                'updated_at' => $now,
            ]);

        DB::table('threads_categories')
            ->whereNull('analysis_profile_id')
            ->update([
                'analysis_profile_id' => $profileId,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Repair migration idempotente: rollback não desfaz backfill para evitar perda de vínculo.
    }
};
