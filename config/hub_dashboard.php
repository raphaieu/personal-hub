<?php

declare(strict_types=1);

/**
 * Cards do dashboard principal: mantenha em sincronia com os links em
 * resources/views/layouts/navigation.blade.php ao adicionar novas áreas do hub.
 *
 * @var array<int, array{route: string, title: string, description: string, icon: string}>
 */
return [
    'cards' => [
        [
            'route' => 'dashboard',
            'title' => 'Dashboard',
            'description' => 'Visão geral e atalhos para todas as áreas do hub.',
            'icon' => 'home',
        ],
        [
            'route' => 'chat',
            'title' => 'Chat IA',
            'description' => 'Conversa com os modelos configurados (Ollama, Groq, fallback na nuvem).',
            'icon' => 'chat',
        ],
        [
            'route' => 'threads.hub',
            'title' => 'Threads Hub',
            'description' => 'Fontes, scrape e curadoria de oportunidades no Threads.',
            'icon' => 'threads',
        ],
        [
            'route' => 'analysis-profiles.hub',
            'title' => 'Profiles IA',
            'description' => 'Perfis de análise e prompts reutilizáveis por canal.',
            'icon' => 'profiles',
        ],
        [
            'route' => 'monitored-sources.hub',
            'title' => 'Fontes monitoradas',
            'description' => 'WhatsApp e outras fontes com pipeline de classificação.',
            'icon' => 'sources',
        ],
        [
            'route' => 'utilities.hub',
            'title' => 'Utilidades',
            'description' => 'Embasa, Coelba, faturas e scraping das concessionárias.',
            'icon' => 'utilities',
        ],
        [
            'route' => 'albums.hub',
            'title' => 'Álbuns',
            'description' => 'Galerias, upload, contribuição externa e viewer público.',
            'icon' => 'albums',
        ],
        [
            'route' => 'events.hub',
            'title' => 'Eventos',
            'description' => 'Eventos privados, convidados, links de lista e API pública para o front.',
            'icon' => 'events',
        ],
    ],
];
