<?php

declare(strict_types=1);

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('EVENTS_CORS_ORIGINS', 'https://events.raphael-martins.com'))
)));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => $origins !== [] ? $origins : ['https://events.raphael-martins.com'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Ref-Token', 'X-Turnstile-Token', 'Authorization'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
