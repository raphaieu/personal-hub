<?php

namespace Tests\Feature\Utilities;

use App\Jobs\ScrapeConta;
use App\Jobs\VerificarStatusFaturas;
use App\Models\Invoice;
use App\Models\UtilityAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class VerificarStatusFaturasJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_scrape_conta_with_ignore_window_when_unpaid_exists(): void
    {
        Bus::fake();

        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => 'A',
            'due_day' => 1,
            'reminder_lead_days' => 1,
            'is_active' => true,
        ]);

        Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '01/2026',
            'due_date' => '2026-04-10',
            'amount_total' => '10.00',
            'status' => 'pendente',
        ]);

        (new VerificarStatusFaturas)->handle();

        Bus::assertDispatched(ScrapeConta::class, function (ScrapeConta $job): bool {
            return $job->kind === 'embasa'
                && $job->ignoreScrapeWindow === true
                && $job->force === false;
        });
    }

    public function test_does_not_dispatch_when_no_unpaid_invoices(): void
    {
        Bus::fake();

        UtilityAccount::query()->create([
            'kind' => 'coelba',
            'account_ref' => 'B',
            'due_day' => 5,
            'reminder_lead_days' => 2,
            'is_active' => true,
        ]);

        (new VerificarStatusFaturas)->handle();

        Bus::assertNothingDispatched();
    }
}
