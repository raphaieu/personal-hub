<?php

namespace Database\Seeders;

use App\Models\ThreadsCategory;
use Illuminate\Database\Seeder;

class ThreadsCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'emprego-fixo',
                'name' => 'Emprego Fixo',
                'description' => 'Vagas CLT ou posições permanentes.',
                'sort_order' => 10,
            ],
            [
                'slug' => 'temporario',
                'name' => 'Temporario',
                'description' => 'Trabalhos com prazo determinado ou sazonal.',
                'sort_order' => 20,
            ],
            [
                'slug' => 'freela',
                'name' => 'Freela',
                'description' => 'Projetos freelancer e trabalhos por demanda.',
                'sort_order' => 30,
            ],
            [
                'slug' => 'renda-extra',
                'name' => 'Renda Extra',
                'description' => 'Oportunidades complementares de renda.',
                'sort_order' => 40,
            ],
            [
                'slug' => 'outros',
                'name' => 'Outros',
                'description' => 'Itens relevantes que nao encaixam nas categorias principais.',
                'sort_order' => 99,
            ],
        ];

        foreach ($categories as $category) {
            ThreadsCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['is_active' => true]
            );
        }
    }
}
