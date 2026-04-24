<?php

namespace Tests\Feature\Utilities;

use App\Contracts\UtilityScraperClientInterface;
use App\Jobs\ScrapeConta;
use App\Models\Invoice;
use App\Models\UtilityAccount;
use App\Services\InvoiceService;
use App\Services\Utilities\FakeUtilityScraperClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class ScrapeContaJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_job_ingests_when_account_in_window_and_ref_matches(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-07')->startOfDay());

        UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => 'MAT-1',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        $fake = new FakeUtilityScraperClient(embasaResponse: [
            'success' => true,
            'mode' => 'embasa',
            'concessionaria' => 'embasa',
            'scraped_at' => '2026-03-07T08:00:00.000Z',
            'data' => [
                'matricula' => 'MAT-1',
                'faturas' => [
                    [
                        'referencia' => '03/2026',
                        'vencimento' => '2026-03-10',
                        'valor_total' => 10,
                        'status' => 'pendente',
                    ],
                ],
            ],
        ]);

        $this->app->instance(UtilityScraperClientInterface::class, $fake);

        (new ScrapeConta('embasa'))->handle(
            app(UtilityScraperClientInterface::class),
            app(InvoiceService::class),
        );

        $this->assertSame(1, Invoice::query()->count());
        $account = UtilityAccount::query()->first();
        $this->assertNotNull($account?->last_scraped_at);
    }

    public function test_job_throws_on_invalid_kind(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ScrapeConta('invalid'))->handle(
            app(UtilityScraperClientInterface::class),
            app(InvoiceService::class),
        );
    }

    public function test_job_ingests_outside_window_when_ignore_scrape_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01')->startOfDay());

        UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => 'MAT-OUT',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        $fake = new FakeUtilityScraperClient(embasaResponse: [
            'success' => true,
            'mode' => 'embasa',
            'concessionaria' => 'embasa',
            'scraped_at' => '2026-01-01T09:00:00.000Z',
            'data' => [
                'matricula' => 'MAT-OUT',
                'faturas' => [
                    [
                        'referencia' => '01/2026',
                        'vencimento' => '2026-01-15',
                        'valor_total' => 20,
                        'status' => 'pendente',
                    ],
                ],
            ],
        ]);

        $this->app->instance(UtilityScraperClientInterface::class, $fake);

        (new ScrapeConta('embasa', ignoreScrapeWindow: true))->handle(
            app(UtilityScraperClientInterface::class),
            app(InvoiceService::class),
        );

        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_skips_playwright_when_pending_and_due_still_in_future_without_force(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-24')->startOfDay());

        UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => 'MAT-SKIP',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        $account = UtilityAccount::query()->first();
        $this->assertNotNull($account);

        Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '05/2026',
            'due_date' => '2026-05-08',
            'amount_total' => '10.00',
            'status' => 'pendente',
        ]);

        $spy = new class implements UtilityScraperClientInterface
        {
            public int $embasaCalls = 0;

            public function scrapeEmbasa(): array
            {
                $this->embasaCalls++;

                return [
                    'success' => true,
                    'data' => [
                        'matricula' => 'MAT-SKIP',
                        'faturas' => [
                            [
                                'referencia' => '05/2026',
                                'vencimento' => '2026-05-08',
                                'valor_total' => 10,
                                'status' => 'pendente',
                            ],
                        ],
                    ],
                ];
            }

            public function scrapeCoelba(): array
            {
                throw new \RuntimeException('unexpected');
            }
        };

        $this->app->instance(UtilityScraperClientInterface::class, $spy);

        (new ScrapeConta('embasa', ignoreScrapeWindow: true))->handle(
            app(UtilityScraperClientInterface::class),
            app(InvoiceService::class),
        );

        $this->assertSame(0, $spy->embasaCalls);
    }

    public function test_force_runs_playwright_even_when_pending_before_due(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-24')->startOfDay());

        UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => 'MAT-FORCE',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        $account = UtilityAccount::query()->first();
        $this->assertNotNull($account);

        Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '05/2026',
            'due_date' => '2026-05-08',
            'amount_total' => '10.00',
            'status' => 'pendente',
        ]);

        $spy = new class implements UtilityScraperClientInterface
        {
            public int $embasaCalls = 0;

            public function scrapeEmbasa(): array
            {
                $this->embasaCalls++;

                return [
                    'success' => true,
                    'data' => [
                        'matricula' => 'MAT-FORCE',
                        'faturas' => [
                            [
                                'referencia' => '05/2026',
                                'vencimento' => '2026-05-08',
                                'valor_total' => 11,
                                'status' => 'pendente',
                            ],
                        ],
                    ],
                ];
            }

            public function scrapeCoelba(): array
            {
                throw new \RuntimeException('unexpected');
            }
        };

        $this->app->instance(UtilityScraperClientInterface::class, $spy);

        (new ScrapeConta('embasa', ignoreScrapeWindow: true, force: true))->handle(
            app(UtilityScraperClientInterface::class),
            app(InvoiceService::class),
        );

        $this->assertSame(1, $spy->embasaCalls);
        $this->assertSame('11.00', (string) Invoice::query()->first()?->amount_total);
    }
}
