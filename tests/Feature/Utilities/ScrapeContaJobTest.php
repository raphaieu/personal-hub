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
}
