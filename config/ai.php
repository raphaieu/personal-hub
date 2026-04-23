<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI routing (AiRouterService)
    |--------------------------------------------------------------------------
    |
    | Prompt length above threshold skips Ollama and starts with Groq (local-first
    | only when the workload fits the host model window).
    |
    */

    'prompt_long_threshold_chars' => (int) env('AI_PROMPT_LONG_THRESHOLD', 2000),

    // Segundos para POST /api/chat — tags (/api/tags) é rápido; inferência pode passar de 10s em CPU modesta ou cold start.
    'ollama_timeout_simple' => (int) env('AI_OLLAMA_TIMEOUT_SIMPLE', 45),

    'groq_timeout' => (int) env('AI_GROQ_TIMEOUT', 30),

    'anthropic_timeout' => (int) env('AI_ANTHROPIC_TIMEOUT', 60),

    'openai_timeout' => (int) env('AI_OPENAI_TIMEOUT', 60),

    // Dashboard: transcrição / imagens (HTTP direto à API do provedor)
    'transcription_timeout' => (int) env('AI_TRANSCRIPTION_TIMEOUT', 120),

    'image_generation_timeout' => (int) env('AI_IMAGE_GENERATION_TIMEOUT', 120),

];
