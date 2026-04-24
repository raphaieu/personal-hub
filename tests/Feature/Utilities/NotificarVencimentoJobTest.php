<?php

namespace Tests\Feature\Utilities;

use App\Jobs\NotificarVencimento;
use App\Models\Invoice;
use App\Models\UtilityAccount;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NotificarVencimentoJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sends_whatsapp_and_sets_last_notified_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20')->startOfDay());

        Config::set('services.utilities.notify_days_ahead', 7);
        Config::set('services.whatsapp.utilities_home_group_jid', '120363000000@g.us');
        Config::set('services.evolution.url', 'http://evolution.test');
        Config::set('services.evolution.api_key', 'key');
        Config::set('services.evolution.instance', 'hub');

        Http::fake([
            'http://evolution.test/message/sendText/hub' => Http::response(['status' => 'PENDING'], 201),
        ]);

        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => 'X',
            'label' => 'Casa',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        $invoice = Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '04/2026',
            'due_date' => '2026-04-22',
            'amount_total' => '55.10',
            'status' => 'pendente',
            'last_notified_at' => null,
        ]);

        (new NotificarVencimento)->handle(app(InvoiceService::class));

        $invoice->refresh();
        $this->assertNotNull($invoice->last_notified_at);

        Http::assertSentCount(1);
    }

    public function test_skips_when_already_notified_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20')->startOfDay());

        Config::set('services.utilities.notify_days_ahead', 7);
        Config::set('services.whatsapp.utilities_home_group_jid', '120363000000@g.us');
        Config::set('services.evolution.url', 'http://evolution.test');
        Config::set('services.evolution.api_key', 'key');
        Config::set('services.evolution.instance', 'hub');

        Http::fake();

        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => 'X',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '04/2026',
            'due_date' => '2026-04-22',
            'amount_total' => '55.10',
            'status' => 'pendente',
            'last_notified_at' => now(),
        ]);

        (new NotificarVencimento)->handle(app(InvoiceService::class));

        Http::assertNothingSent();
    }
}
