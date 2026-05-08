<?php

namespace Database\Seeders;

use App\Enums\AiTask;
use App\Models\AnalysisProfile;
use Illuminate\Database\Seeder;

class AnalysisProfileSeeder extends Seeder
{
    public function run(): void
    {
        AnalysisProfile::query()->updateOrCreate(
            ['slug' => AnalysisProfile::THREADS_OPPORTUNITIES_SLUG],
            [
                'name' => 'Threads Opportunities',
                'description' => 'Perfil padrão para classificar oportunidades de trabalho/renda extra em comentários do Threads.',
                'channel' => 'threads',
                'analysis_type' => 'classification',
                'system_prompt' => (string) AiTask::ThreadsOpportunityClassification->systemPrompt(),
                'output_schema' => [
                    'type' => 'object',
                    'required' => ['category_slug', 'summary', 'relevance_score'],
                    'properties' => [
                        'category_slug' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                        'relevance_score' => ['type' => 'number'],
                    ],
                ],
                'allowed_categories' => AnalysisProfile::THREADS_ALLOWED_CATEGORIES,
                'score_threshold' => 65.00,
                'settings' => [
                    'ai_task' => AiTask::ThreadsOpportunityClassification->value,
                    'result_mapping' => [
                        'category' => 'category_slug',
                        'summary' => 'summary',
                        'relevance_score' => 'relevance_score',
                    ],
                ],
                'is_active' => true,
            ]
        );
    }
}
