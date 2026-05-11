<?php


return [

    'frontend_url' => rtrim((string) env('EVENTS_FRONTEND_URL', 'https://events.raphael-martins.com'), '/'),

    'default_terms_url' => env('EVENTS_DEFAULT_TERMS_URL', 'https://events.raphael-martins.com/termos'),

    'default_privacy_url' => env('EVENTS_DEFAULT_PRIVACY_URL', 'https://events.raphael-martins.com/privacidade'),

    'turnstile' => [
        'enabled' => filter_var(env('EVENTS_TURNSTILE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'secret_key' => env('EVENTS_TURNSTILE_SECRET_KEY'),
    ],
];
