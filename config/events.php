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
];
