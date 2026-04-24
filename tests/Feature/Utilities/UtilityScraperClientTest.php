<?php

namespace Tests\Feature\Utilities;

use App\Contracts\UtilityScraperClientInterface;
use App\Services\Utilities\FakeUtilityScraperClient;
use App\Services\Utilities\UtilityPlaywrightService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class UtilityScraperClientTest extends TestCase
{
    public function test_container_resolves_utility_scraper_interface_with_http_service(): void
    {
        $client = app(UtilityScraperClientInterface::class);

        $this->assertInstanceOf(UtilityPlaywrightService::class, $client);
    }

    public function test_scrape_embasa_normalizes_payload_and_response(): void
    {
        Config::set('services.playwright.url', 'http://127.0.0.1:3001');
        Config::set('services.playwright.timeout', 120);

        Http::fake([
            'http://127.0.0.1:3001/embasa/scrape' => Http::response([
                'success' => true,
                'mode' => 'embasa',
                'concessionaria' => 'embasa',
                'scraped_at' => '2026-04-24T12:00:00.000Z',
                'data' => [
                    'concessionaria' => 'embasa',
                    'matricula' => '12345',
                    'scraped_at' => '2026-04-24T12:00:00.000Z',
                    'faturas' => [
                        [
                            'referencia' => '04/2026',
                            'vencimento' => '10/04/2026',
                            'valor_total' => 'R$ 50,00',
                            'status' => 'pendente',
                        ],
                    ],
                    'pdf_path' => '/app/downloads/embasa_1.pdf',
                ],
            ], 200),
        ]);

        $client = app(UtilityScraperClientInterface::class);
        $result = $client->scrapeEmbasa();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://127.0.0.1:3001/embasa/scrape'
                && $request->method() === 'POST';
        });

        $this->assertTrue($result['success']);
        $this->assertSame('embasa', $result['mode']);
        $this->assertSame('embasa', $result['concessionaria']);
        $this->assertSame('12345', $result['data']['matricula']);
        $this->assertSame('pendente', $result['data']['faturas'][0]['status']);
        $this->assertNull($result['error']);
    }

    public function test_scrape_coelba_throws_runtime_exception_on_http_error(): void
    {
        Config::set('services.playwright.url', 'http://127.0.0.1:3001');
        Config::set('services.playwright.timeout', 120);

        Http::fake([
            'http://127.0.0.1:3001/coelba/scrape' => Http::response([
                'success' => false,
                'mode' => 'coelba',
                'concessionaria' => 'coelba',
                'error' => 'upstream failure',
            ], 502),
        ]);

        $client = app(UtilityScraperClientInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Serviço Playwright respondeu HTTP 502 para Coelba.');

        $client->scrapeCoelba();
    }

    public function test_fake_client_supports_override_and_default_payloads(): void
    {
        $fake = new FakeUtilityScraperClient;

        $defaultEmbasa = $fake->scrapeEmbasa();
        $this->assertTrue($defaultEmbasa['success']);
        $this->assertSame('embasa', $defaultEmbasa['mode']);

        $fake->setCoelbaResponse([
            'success' => true,
            'mode' => 'coelba',
            'concessionaria' => 'coelba',
            'scraped_at' => '2026-04-24T12:00:00.000Z',
            'data' => [
                'pix_code' => '00020126580014',
                'faturas' => [['referencia' => '05/2026']],
            ],
        ]);

        $overridden = $fake->scrapeCoelba();
        $this->assertSame('00020126580014', $overridden['data']['pix_code']);
        $this->assertSame('05/2026', $overridden['data']['faturas'][0]['referencia']);
    }
}
