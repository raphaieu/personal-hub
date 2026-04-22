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

    'ollama_timeout_simple' => (int) env('AI_OLLAMA_TIMEOUT_SIMPLE', 10),

    'groq_timeout' => (int) env('AI_GROQ_TIMEOUT', 30),

    'anthropic_timeout' => (int) env('AI_ANTHROPIC_TIMEOUT', 60),

    'openai_timeout' => (int) env('AI_OPENAI_TIMEOUT', 60),

];
