<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'evolution' => [
        'url' => env('EVOLUTION_URL'),
        'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'instance' => env('EVOLUTION_INSTANCE', 'raphael'),
        'timeout' => env('EVOLUTION_HTTP_TIMEOUT', 45),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp (hub)
    |--------------------------------------------------------------------------
    |
    | notes_solo_group_jid — mesmo JID que WHATSAPP_NOTAS_GRUPO_JID no seeder; mensagens
    | desse grupo disparam ProcessPersonalWhatsAppMessage (workaround mídia no 1:1).
    |
    */

    'whatsapp' => [
        'notes_solo_group_jid' => env('WHATSAPP_NOTAS_GRUPO_JID'),
        /**
         * JID do grupo da casa para lembretes de fatura (`InvoiceService::notifyHomeGroup` + `EvolutionService`).
         * Preferência: WHATSAPP_UTILITIES_HOME_GROUP_JID; fallback legado: WHATSAPP_GRUPO_CASA_JID (SPEC / .env.example).
         */
        'utilities_home_group_jid' => env('WHATSAPP_UTILITIES_HOME_GROUP_JID') ?: env('WHATSAPP_GRUPO_CASA_JID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ollama (host / dev local)
    |--------------------------------------------------------------------------
    */

    'ollama' => [
        'enabled' => env('OLLAMA_ENABLED', false),
        'base_url' => env('OLLAMA_BASE_URL', 'http://172.23.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen3.5:4b'),
        'think' => env('OLLAMA_THINK', false),
        'timeout' => env('OLLAMA_TIMEOUT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway /iara — remote dev / admin (see .env.example profiles)
    |--------------------------------------------------------------------------
    */

    'iara' => [
        'gateway_url' => env('IARA_GATEWAY_URL'),
        'internal_key' => env('IARA_INTERNAL_KEY'),
        'allowed_ips' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('IARA_ALLOWED_IPS', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Groq — OpenAI-compatible endpoint
    |--------------------------------------------------------------------------
    */

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic / OpenAI (fallback chain after Groq)
    |--------------------------------------------------------------------------
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Playwright Threads scraper
    |--------------------------------------------------------------------------
    */

    'playwright' => [
        'url' => env('PLAYWRIGHT_SERVICE_URL', 'http://127.0.0.1:3001'),
        'timeout' => env('PLAYWRIGHT_HTTP_TIMEOUT', 120),
    ],

    'threads' => [
        'relevance_threshold' => (float) env('THREADS_RELEVANCE_THRESHOLD', 0.65),
        'ai_dispatch_spacing_seconds' => (int) env('THREADS_AI_DISPATCH_SPACING_SECONDS', 30),
        'vote_fingerprint_salt' => env('THREADS_VOTE_FINGERPRINT_SALT', ''),
    ],

    'analytics' => [
        'ga4_measurement_id' => env('GA4_MEASUREMENT_ID'),
    ],

    'utilities' => [
        /** Inclui faturas com vencimento entre hoje e hoje+N (e vencidas em atraso). */
        'notify_days_ahead' => (int) env('UTILITIES_NOTIFY_DAYS_AHEAD', 7),
        /**
         * Disco Laravel (`config/filesystems.php`) onde gravar PDFs de fatura após leitura do path do Playwright.
         * Use `s3` com AWS_* / endpoint MinIO para objeto no bucket; `local` = storage/app/private (default).
         */
        'pdf_storage_disk' => env('UTILITIES_INVOICE_PDF_DISK', 'local'),
        /**
         * Apagar o arquivo fonte após `put` bem-sucedido (ex.: PDF em pasta do Playwright).
         * null = automático: true quando pdf_storage_disk é `s3`, false quando `local`.
         * Só funciona se o path existir no mesmo filesystem que o PHP (volume compartilhado app ↔ Playwright).
         */
        'delete_source_pdf_after_upload' => env('UTILITIES_DELETE_SOURCE_PDF_AFTER_UPLOAD'),
    ],

];
