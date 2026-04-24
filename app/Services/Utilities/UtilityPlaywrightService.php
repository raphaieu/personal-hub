<?php

namespace App\Services\Utilities;

use App\Contracts\UtilityScraperClientInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class UtilityPlaywrightService implements UtilityScraperClientInterface
{
    /**
     * @return array<string, mixed>
     */
    public function scrapeEmbasa(): array
    {
        return $this->request('/embasa/scrape', 'embasa', 'Embasa');
    }

    /**
     * @return array<string, mixed>
     */
    public function scrapeCoelba(): array
    {
        return $this->request('/coelba/scrape', 'coelba', 'Coelba');
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $path, string $fallbackMode, string $utilityLabel): array
    {
        $baseUrl = rtrim((string) config('services.playwright.url', 'http://127.0.0.1:3001'), '/');
        $timeout = (int) config('services.playwright.timeout', 120);
        $url = $baseUrl.$path;

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->post($url, []);
        } catch (ConnectionException $e) {
            Log::warning('utilities.playwright.connection_failure', [
                'url' => $url,
                'utility' => $utilityLabel,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                sprintf('Falha de conexão com o serviço Playwright (%s).', $utilityLabel),
                0,
                $e
            );
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Resposta inválida do serviço Playwright (JSON ausente ou malformado).');
        }

        if (! $response->successful()) {
            Log::warning('utilities.playwright.http_failure', [
                'url' => $url,
                'utility' => $utilityLabel,
                'status' => $response->status(),
                'response' => $json,
            ]);

            throw new RuntimeException(
                sprintf(
                    'Serviço Playwright respondeu HTTP %d para %s.',
                    $response->status(),
                    $utilityLabel
                )
            );
        }

        return $this->normalizeResponse($json, $fallbackMode);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function normalizeResponse(array $json, string $fallbackMode): array
    {
        $success = (bool) ($json['success'] ?? false);
        $mode = is_string($json['mode'] ?? null) ? $json['mode'] : $fallbackMode;
        $concessionaria = is_string($json['concessionaria'] ?? null) ? $json['concessionaria'] : $fallbackMode;

        return [
            'success' => $success,
            'mode' => $mode,
            'concessionaria' => $concessionaria,
            'scraped_at' => is_string($json['scraped_at'] ?? null) ? $json['scraped_at'] : now()->toIso8601String(),
            'error' => isset($json['error']) && is_string($json['error']) ? $json['error'] : null,
            'screenshot_path' => isset($json['screenshot_path']) && is_string($json['screenshot_path']) ? $json['screenshot_path'] : null,
            'data' => isset($json['data']) && is_array($json['data']) ? $json['data'] : [],
        ];
    }
}
