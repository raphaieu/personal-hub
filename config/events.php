<?php

return [

    'frontend_url' => rtrim((string) env('EVENTS_FRONTEND_URL', 'https://events.raphael-martins.com'), '/'),

    'default_terms_url' => env('EVENTS_DEFAULT_TERMS_URL', 'https://events.raphael-martins.com/termos'),

    'default_privacy_url' => env('EVENTS_DEFAULT_PRIVACY_URL', 'https://events.raphael-martins.com/privacidade'),

    'turnstile' => [
        'enabled' => filter_var(env('EVENTS_TURNSTILE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'secret_key' => env('EVENTS_TURNSTILE_SECRET_KEY'),
    ],

    'hub_public_url' => rtrim((string) env('EVENTS_HUB_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/'),

    'payment_reservation_minutes' => (int) env('EVENTS_PAYMENT_RESERVATION_MINUTES', 15),

    'payment_poll_interval_seconds' => (int) env('EVENTS_PAYMENT_POLL_INTERVAL_SECONDS', 2),

    /*
    |--------------------------------------------------------------------------
    | Plataforma self-service (landing pages públicas)
    |--------------------------------------------------------------------------
    */

    // Tema aplicado quando o evento não tem theme_json próprio.
    'default_theme' => [
        'colors' => [
            'primary' => '#6c5ce7',
            'secondary' => '#a29bfe',
            'background' => '#0a0a0f',
            'surface' => '#16161f',
            'text' => '#e8e6f0',
            'textMuted' => 'rgba(232, 230, 240, 0.6)',
        ],
        'fonts' => [
            'heading' => 'Outfit',
            'body' => 'Inter',
        ],
        'mode' => 'dark',
        'borderRadius' => '16px',
        'customCss' => null,
    ],

    // Allowlist de famílias do Google Fonts carregáveis pelas landings.
    'allowed_fonts' => [
        'Outfit', 'Inter', 'Playfair Display', 'Bebas Neue', 'Montserrat', 'Poppins',
        'Roboto', 'Lora', 'Oswald', 'DM Sans', 'Space Grotesk', 'Sora',
    ],

    // Ícones disponíveis para itens de regra da landing (SVGs internos do front).
    'allowed_rule_icons' => [
        'beer', 'cup', 'shirt', '18', 'user-plus', 'music', 'star', 'camera', 'ticket', 'alert',
    ],

    'reserved_slugs' => [
        'app', 'api', 'e', 'termos', 'privacidade', 'admin', 'auth', 'assets',
        'login', 'register', 'events', 'hub', 'up',
    ],

    // Limites anti-abuso da conta gratuita (ajustáveis via env, sem deploy).
    'limits' => [
        'max_published_events' => (int) env('EVENTS_MAX_PUBLISHED_EVENTS', 3),
        'max_guests_per_event' => (int) env('EVENTS_MAX_GUESTS_PER_EVENT', 500),
        'max_referral_links_per_event' => (int) env('EVENTS_MAX_REFERRAL_LINKS', 10),
        'max_flyer_jobs_per_day' => (int) env('EVENTS_MAX_FLYER_JOBS_PER_DAY', 5),
    ],

    // Disco das fotos de convidado (MinIO em produção; fake/local em testes).
    'guest_photos_disk' => env('EVENTS_GUEST_PHOTOS_DISK', 's3'),

    'guest_photo_url_minutes' => (int) env('EVENTS_GUEST_PHOTO_URL_MINUTES', 15),
];
