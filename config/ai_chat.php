<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard chat — payloads e timeouts auxiliares
    |--------------------------------------------------------------------------
    */

    'max_image_payload_kb' => (int) env('AI_CHAT_MAX_IMAGE_KB', 2048),

    'max_images_per_message' => (int) env('AI_CHAT_MAX_IMAGES', 4),

    'ollama_tags_timeout_seconds' => (int) env('AI_CHAT_OLLAMA_TAGS_TIMEOUT', 5),

    'ollama_tags_cache_ttl_seconds' => (int) env('AI_CHAT_OLLAMA_TAGS_CACHE_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Modelos remotos (GET /v1/models nos provedores — mesmas chaves do .env)
    |--------------------------------------------------------------------------
    */

    // true = GET /v1/models nos provedores (usa GROQ_/OPENAI_/ANTHROPIC_API_KEY)
    'fetch_remote_models' => match (strtolower((string) env('AI_CHAT_FETCH_REMOTE_MODELS', 'true'))) {
        'false', '0', 'no', 'off' => false,
        default => true,
    },

    'remote_models_timeout_seconds' => (int) env('AI_CHAT_REMOTE_MODELS_TIMEOUT', 15),

    'remote_models_cache_ttl_seconds' => (int) env('AI_CHAT_REMOTE_MODELS_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Transcrição (Whisper / compatível) — modelos por engine
    |--------------------------------------------------------------------------
    */

    'transcription' => [
        'openai_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'whisper-1'),
        'groq_model' => env('GROQ_TRANSCRIPTION_MODEL', 'whisper-large-v3-turbo'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Geração de imagem (Images API OpenAI no MVP)
    |--------------------------------------------------------------------------
    */

    'image_generation' => [
        'openai_model' => env('OPENAI_IMAGE_MODEL', 'dall-e-3'),
        'size' => env('OPENAI_IMAGE_SIZE', '1024x1024'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo curado: id exatamente como na API do provedor
    | Flags guiam a UI; ajuste se um modelo real não suportar.
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'groq' => [
            // Referência depreciações: https://console.groq.com/docs/deprecations
            ['id' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B', 'vision' => false, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
            ['id' => 'llama-3.1-8b-instant', 'label' => 'Llama 3.1 8B Instant', 'vision' => false, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
            ['id' => 'meta-llama/llama-4-scout-17b-16e-instruct', 'label' => 'Llama 4 Scout 17B (multimodal)', 'vision' => true, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
            ['id' => 'openai/gpt-oss-120b', 'label' => 'GPT-OSS 120B', 'vision' => false, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
            ['id' => 'qwen/qwen3-32b', 'label' => 'Qwen 3 32B', 'vision' => false, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
        ],

        'anthropic' => [
            ['id' => 'claude-sonnet-4-20250514', 'label' => 'Claude Sonnet 4', 'vision' => true, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
            ['id' => 'claude-3-5-sonnet-20241022', 'label' => 'Claude 3.5 Sonnet', 'vision' => true, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
            ['id' => 'claude-3-5-haiku-20241022', 'label' => 'Claude 3.5 Haiku', 'vision' => true, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
            ['id' => 'claude-3-opus-20240229', 'label' => 'Claude 3 Opus', 'vision' => true, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
        ],

        'openai' => [
            ['id' => 'gpt-4o-mini', 'label' => 'GPT-4o mini', 'vision' => true, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
            ['id' => 'gpt-4o', 'label' => 'GPT-4o', 'vision' => true, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
            ['id' => 'gpt-4-turbo', 'label' => 'GPT-4 Turbo', 'vision' => true, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
            ['id' => 'o1-mini', 'label' => 'o1-mini', 'vision' => true, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
        ],
    ],

];
