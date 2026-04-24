<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\UtilityAccount;
use App\Support\UtilityAccountScrapeGate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UtilityAccountScrapeGateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_skips_when_pendente_and_today_before_due(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-24')->startOfDay());

        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '1',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '05/2026',
            'due_date' => '2026-05-08',
            'amount_total' => '10.00',
            'status' => 'pendente',
        ]);

        $this->assertFalse(UtilityAccountScrapeGate::accountRequiresPlaywright($account));
        $this->assertFalse(UtilityAccountScrapeGate::anyRequiresPlaywright(collect([$account]), false));
        $this->assertTrue(UtilityAccountScrapeGate::anyRequiresPlaywright(collect([$account]), true));
    }

    #[Test]
    public function it_runs_when_pendente_and_today_is_due_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-08')->startOfDay());

        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '1',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '05/2026',
            'due_date' => '2026-05-08',
            'amount_total' => '10.00',
            'status' => 'pendente',
        ]);

        $this->assertTrue(UtilityAccountScrapeGate::accountRequiresPlaywright($account));
    }

    #[Test]
    public function it_runs_when_pendente_and_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10')->startOfDay());

        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '1',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '05/2026',
            'due_date' => '2026-05-08',
            'amount_total' => '10.00',
            'status' => 'pendente',
        ]);

        $this->assertTrue(UtilityAccountScrapeGate::accountRequiresPlaywright($account));
    }

    #[Test]
    public function it_skips_when_pago(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20')->startOfDay());

        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '1',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '05/2026',
            'due_date' => '2026-05-08',
            'amount_total' => '10.00',
            'status' => 'pago',
        ]);

        $this->assertFalse(UtilityAccountScrapeGate::accountRequiresPlaywright($account));
    }

    #[Test]
    public function it_runs_when_no_invoice_yet(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-24')->startOfDay());

        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '1',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        $this->assertTrue(UtilityAccountScrapeGate::accountRequiresPlaywright($account));
    }
}
