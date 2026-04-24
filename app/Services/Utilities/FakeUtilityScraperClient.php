<?php

namespace App\Services\Utilities;

use App\Contracts\UtilityScraperClientInterface;

final class FakeUtilityScraperClient implements UtilityScraperClientInterface
{
    /**
     * @param  array<string, mixed>|null  $embasaResponse
     * @param  array<string, mixed>|null  $coelbaResponse
     */
    public function __construct(
        private ?array $embasaResponse = null,
        private ?array $coelbaResponse = null,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public function setEmbasaResponse(array $response): void
    {
        $this->embasaResponse = $response;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function setCoelbaResponse(array $response): void
    {
        $this->coelbaResponse = $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function scrapeEmbasa(): array
    {
        return $this->embasaResponse ?? [
            'success' => true,
            'mode' => 'embasa',
            'concessionaria' => 'embasa',
            'scraped_at' => now()->toIso8601String(),
            'data' => [
                'concessionaria' => 'embasa',
                'faturas' => [],
                'pdf_path' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scrapeCoelba(): array
    {
        return $this->coelbaResponse ?? [
            'success' => true,
            'mode' => 'coelba',
            'concessionaria' => 'coelba',
            'scraped_at' => now()->toIso8601String(),
            'data' => [
                'concessionaria' => 'coelba',
                'faturas' => [],
                'pdf_path' => null,
                'pix_code' => null,
            ],
        ];
    }
}
