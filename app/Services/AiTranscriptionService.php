<?php

namespace App\Services;

use App\Support\ServiceApiKeys;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AiTranscriptionService
{
    /**
     * @return array{ok: bool, text?: string, engine?: string, error?: string}
     */
    public function transcribe(UploadedFile $file, string $engine): array
    {
        $engine = strtolower($engine);

        return match ($engine) {
            'openai' => $this->openai($file),
            'groq' => $this->groq($file),
            default => ['ok' => false, 'error' => 'Engine inválido.'],
        };
    }

    /**
     * @return array{ok: bool, text?: string, engine?: string, error?: string}
     */
    private function openai(UploadedFile $file): array
    {
        $key = ServiceApiKeys::openAi();
        if (! filled($key)) {
            return ['ok' => false, 'error' => 'OpenAI não configurado.'];
        }

        $path = $file->getRealPath();
        if ($path === false) {
            return ['ok' => false, 'error' => 'Arquivo temporário inválido.'];
        }

        $timeout = (int) config('ai.transcription_timeout', 120);
        $model = (string) config('ai_chat.transcription.openai_model');

        try {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return ['ok' => false, 'error' => 'Não foi possível ler o arquivo de áudio.', 'engine' => 'openai'];
            }

            $response = Http::timeout($timeout)
                ->withToken($key)
                ->attach(
                    'file',
                    $handle,
                    $file->getClientOriginalName(),
                    ['Content-Type' => $file->getMimeType() ?? 'application/octet-stream']
                )
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $model,
                ]);

            if (! $response->successful()) {
                Log::warning('ai.transcription_failure', [
                    'engine' => 'openai',
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['ok' => false, 'error' => 'Falha na transcrição OpenAI.', 'engine' => 'openai'];
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();
            $text = isset($json['text']) && is_string($json['text']) ? $json['text'] : '';

            Log::info('ai.transcription', ['engine' => 'openai', 'model' => $model]);

            return ['ok' => true, 'text' => trim($text), 'engine' => 'openai'];
        } catch (\Throwable $e) {
            Log::warning('ai.transcription_exception', ['engine' => 'openai', 'message' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage(), 'engine' => 'openai'];
        }
    }

    /**
     * @return array{ok: bool, text?: string, engine?: string, error?: string}
     */
    private function groq(UploadedFile $file): array
    {
        $key = ServiceApiKeys::groq();
        if (! filled($key)) {
            return ['ok' => false, 'error' => 'Groq não configurado.'];
        }

        $path = $file->getRealPath();
        if ($path === false) {
            return ['ok' => false, 'error' => 'Arquivo temporário inválido.'];
        }

        $timeout = (int) config('ai.transcription_timeout', 120);
        $model = (string) config('ai_chat.transcription.groq_model');
        $base = rtrim((string) config('services.groq.url'), '/');

        try {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return ['ok' => false, 'error' => 'Não foi possível ler o arquivo de áudio.', 'engine' => 'groq'];
            }

            $response = Http::timeout($timeout)
                ->withToken($key)
                ->attach(
                    'file',
                    $handle,
                    $file->getClientOriginalName(),
                    ['Content-Type' => $file->getMimeType() ?? 'application/octet-stream']
                )
                ->post($base.'/audio/transcriptions', [
                    'model' => $model,
                ]);

            if (! $response->successful()) {
                Log::warning('ai.transcription_failure', [
                    'engine' => 'groq',
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['ok' => false, 'error' => 'Falha na transcrição Groq.', 'engine' => 'groq'];
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();
            $text = isset($json['text']) && is_string($json['text']) ? $json['text'] : '';

            Log::info('ai.transcription', ['engine' => 'groq', 'model' => $model]);

            return ['ok' => true, 'text' => trim($text), 'engine' => 'groq'];
        } catch (\Throwable $e) {
            Log::warning('ai.transcription_exception', ['engine' => 'groq', 'message' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage(), 'engine' => 'groq'];
        }
    }
}
