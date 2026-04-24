<?php

namespace Tests\Feature\Utilities;

use App\Models\Invoice;
use App\Models\UtilityAccount;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_scrape_result_upserts_embasa_invoices(): void
    {
        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '999',
            'label' => 'Teste',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        $service = new InvoiceService;

        $payload = [
            'success' => true,
            'mode' => 'embasa',
            'concessionaria' => 'embasa',
            'scraped_at' => '2026-04-24T10:00:00.000Z',
            'data' => [
                'matricula' => '999',
                'faturas' => [
                    [
                        'referencia' => '04/2026',
                        'vencimento' => '2026-05-10',
                        'valor_total' => 88.5,
                        'valor_agua' => 40,
                        'valor_esgoto' => 30,
                        'valor_servico' => 18.5,
                        'consumo_m3' => 12,
                        'status' => 'pendente',
                    ],
                ],
            ],
        ];

        $first = $service->processScrapeResult($payload, $account);
        $this->assertSame(1, $first['invoices_upserted']);

        $payload['data']['faturas'][0]['valor_total'] = 90.0;
        $second = $service->processScrapeResult($payload, $account);
        $this->assertSame(1, $second['invoices_upserted']);
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame('90.00', (string) Invoice::query()->first()?->amount_total);
    }

    public function test_process_scrape_result_maps_coelba_valor_fatura(): void
    {
        $account = UtilityAccount::query()->create([
            'kind' => 'coelba',
            'account_ref' => '123456',
            'label' => 'Coelba',
            'due_day' => 15,
            'reminder_lead_days' => 3,
            'is_active' => true,
        ]);

        $service = new InvoiceService;

        $payload = [
            'success' => true,
            'data' => [
                'faturas' => [
                    [
                        'referencia' => '04/2026',
                        'vencimento' => '2026-04-18',
                        'valor_fatura' => 120.25,
                        'situacao' => 'a_vencer',
                    ],
                ],
            ],
        ];

        $service->processScrapeResult($payload, $account);

        $invoice = Invoice::query()->first();
        $this->assertNotNull($invoice);
        $this->assertSame('120.25', (string) $invoice->amount_total);
        $this->assertSame('a_vencer', $invoice->status);
    }

    public function test_upload_pdf_copies_readable_file_to_local_disk(): void
    {
        Storage::fake('local');

        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '1',
            'due_day' => 5,
            'reminder_lead_days' => 2,
            'is_active' => true,
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'hubpdf');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, '%PDF-1.4 minimal');

        $service = new InvoiceService;
        $stored = $service->uploadPdf($tmp, $account, '04/2026');

        $this->assertIsString($stored);
        Storage::disk('local')->assertExists($stored);

        @unlink($tmp);
    }
}
