<?php

namespace App\Services;

use App\Support\ServiceApiKeys;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AiImageGenerationService
{
    /**
     * @return array{ok: bool, url?: string|null, b64_json?: string|null, error?: string}
     */
    public function generate(string $prompt, ?string $size = null, ?string $responseFormat = null): array
    {
        $key = ServiceApiKeys::openAi();
        if (! filled($key)) {
            return ['ok' => false, 'error' => 'OpenAI não configurado.'];
        }

        $timeout = (int) config('ai.image_generation_timeout', 120);
        $model = (string) config('ai_chat.image_generation.openai_model');
        $size ??= (string) config('ai_chat.image_generation.size');
        $responseFormat ??= 'url';

        try {
            $response = Http::timeout($timeout)
                ->withToken($key)
                ->acceptJson()
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => $model,
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => $size,
                    'response_format' => $responseFormat,
                ]);

            if (! $response->successful()) {
                Log::warning('ai.image_generation_failure', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['ok' => false, 'error' => 'Falha ao gerar imagem.'];
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();
            $data = $json['data'][0] ?? null;

            Log::info('ai.image_generation', ['model' => $model, 'size' => $size]);

            if (! is_array($data)) {
                return ['ok' => false, 'error' => 'Resposta inválida da API de imagens.'];
            }

            $url = isset($data['url']) && is_string($data['url']) ? $data['url'] : null;
            $b64 = isset($data['b64_json']) && is_string($data['b64_json']) ? $data['b64_json'] : null;

            return [
                'ok' => true,
                'url' => $url,
                'b64_json' => $b64,
            ];
        } catch (\Throwable $e) {
            Log::warning('ai.image_generation_exception', ['message' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
