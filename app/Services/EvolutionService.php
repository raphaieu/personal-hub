<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Cliente HTTP mínimo para Evolution API (envio de texto).
 *
 * @see https://doc.evolution-api.com/v2/api-reference/message-controller/send-text
 */
final class EvolutionService
{
    public function isConfigured(): bool
    {
        $url = $this->baseUrl();
        $key = $this->apiKey();
        $instance = $this->instance();

        return $url !== '' && $key !== '' && $instance !== '';
    }

    public function sendText(string $number, string $text): void
    {
        $number = trim($number);
        $text = trim($text);

        if ($number === '' || $text === '') {
            throw new RuntimeException('Evolution sendText: número ou texto vazio.');
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('Evolution API não configurada (EVOLUTION_URL / EVOLUTION_API_KEY / EVOLUTION_INSTANCE).');
        }

        $url = sprintf('%s/message/sendText/%s', $this->baseUrl(), rawurlencode($this->instance()));

        try {
            $response = Http::acceptJson()
                ->withHeaders(['apikey' => $this->apiKey()])
                ->timeout((int) config('services.evolution.timeout', 45))
                ->post($url, [
                    'number' => $number,
                    'text' => $text,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('evolution.send_text.connection_failure', [
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Falha de conexão com a Evolution API.', 0, $e);
        } catch (Throwable $e) {
            Log::warning('evolution.send_text.unexpected_failure', [
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Falha inesperada ao chamar a Evolution API.', 0, $e);
        }

        if (! $response->successful()) {
            Log::warning('evolution.send_text.http_failure', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException(
                sprintf('Evolution API respondeu HTTP %d ao enviar texto.', $response->status())
            );
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.evolution.url', ''), '/');
    }

    private function apiKey(): string
    {
        return (string) config('services.evolution.api_key', '');
    }

    private function instance(): string
    {
        return (string) config('services.evolution.instance', '');
    }
}
