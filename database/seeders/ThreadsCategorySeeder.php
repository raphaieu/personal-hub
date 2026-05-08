<?php

namespace Database\Seeders;

use App\Models\AnalysisProfile;
use App\Models\ThreadsCategory;
use Illuminate\Database\Seeder;

class ThreadsCategorySeeder extends Seeder
{
    public function run(): void
    {
        $analysisProfileId = AnalysisProfile::query()
            ->where('slug', AnalysisProfile::THREADS_OPPORTUNITIES_SLUG)
            ->value('id');

        $categories = [
            AnalysisProfile::THREADS_ALLOWED_CATEGORIES[0] => ['Emprego Fixo', 'Vagas CLT ou posições permanentes.', 10],
            AnalysisProfile::THREADS_ALLOWED_CATEGORIES[1] => ['Temporario', 'Trabalhos com prazo determinado ou sazonal.', 20],
            AnalysisProfile::THREADS_ALLOWED_CATEGORIES[2] => ['Freela', 'Projetos freelancer e trabalhos por demanda.', 30],
            AnalysisProfile::THREADS_ALLOWED_CATEGORIES[3] => ['Renda Extra', 'Oportunidades complementares de renda.', 40],
            AnalysisProfile::THREADS_ALLOWED_CATEGORIES[4] => ['Outros', 'Itens relevantes que nao encaixam nas categorias principais.', 99],
        ];

        foreach ($categories as $slug => [$name, $description, $sortOrder]) {
            $categoryModel = ThreadsCategory::query()->firstOrNew([
                'slug' => $slug,
            ]);

            $categoryModel->fill([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'analysis_profile_id' => is_numeric($analysisProfileId) ? (int) $analysisProfileId : null,
            ]);
            $categoryModel->save();
        }
    }
}
