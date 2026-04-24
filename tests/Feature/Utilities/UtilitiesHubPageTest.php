<?php

namespace Tests\Feature\Utilities;

use App\Http\Controllers\Utilities\UtilityInvoicePdfController;
use App\Jobs\ScrapeConta;
use App\Livewire\Utilities\HubPage;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UtilityAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class UtilitiesHubPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_utilities_hub(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('utilities.hub'));

        $response
            ->assertOk()
            ->assertSee('Hub Utilidades')
            ->assertSee('Nova conta');
    }

    public function test_guest_is_redirected_from_utilities_hub(): void
    {
        $this->get(route('utilities.hub'))->assertRedirect();
    }

    public function test_livewire_can_create_utility_account(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('formKind', 'coelba')
            ->set('formAccountRef', '123456')
            ->set('formLabel', 'Apartamento')
            ->set('formDueDay', 15)
            ->set('formReminderLeadDays', 7)
            ->set('formIsActive', true)
            ->call('saveAccount')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('utility_accounts', [
            'kind' => 'coelba',
            'account_ref' => '123456',
            'label' => 'Apartamento',
            'due_day' => 15,
            'reminder_lead_days' => 7,
            'is_active' => true,
        ]);
    }

    public function test_livewire_can_edit_utility_account_without_changing_kind(): void
    {
        $user = User::factory()->create();
        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '999',
            'label' => 'Antigo',
            'due_day' => 5,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('startEdit', $account->id)
            ->set('formKind', 'coelba')
            ->set('formLabel', 'Novo label')
            ->set('formDueDay', 20)
            ->call('saveAccount')
            ->assertHasNoErrors();

        $account->refresh();
        $this->assertSame('embasa', $account->kind);
        $this->assertSame('Novo label', $account->label);
        $this->assertSame(20, $account->due_day);
    }

    public function test_livewire_toggle_active_flips_flag(): void
    {
        $user = User::factory()->create();
        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '111',
            'label' => null,
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('toggleActive', $account->id);

        $this->assertFalse((bool) $account->fresh()?->is_active);
    }

    public function test_livewire_scrape_now_dispatches_scrape_conta_with_ignore_window_and_force(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $account = UtilityAccount::query()->create([
            'kind' => 'coelba',
            'account_ref' => '777',
            'label' => null,
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('scrapeNow', $account->id);

        Bus::assertDispatched(ScrapeConta::class, function (ScrapeConta $job): bool {
            return $job->kind === 'coelba'
                && $job->ignoreScrapeWindow === true
                && $job->force === true;
        });
    }

    public function test_invoice_pdf_returns_404_when_path_missing(): void
    {
        $user = User::factory()->create();
        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '1',
            'label' => null,
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);
        $invoice = Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '04/2026',
            'due_date' => now()->addDays(5)->toDateString(),
            'amount_total' => 100.50,
            'amount_water' => null,
            'amount_sewage' => null,
            'amount_service' => null,
            'water_consumption_m3' => null,
            'status' => 'pendente',
            'payment_date' => null,
            'pdf_path' => 'utilities/invoices/1/missing.pdf',
            'raw_payload' => [],
            'scraped_at' => now(),
            'last_notified_at' => null,
        ]);

        config(['services.utilities.pdf_storage_disk' => 'local']);
        Storage::fake('local');

        $this->actingAs($user)
            ->get(action([UtilityInvoicePdfController::class, 'show'], $invoice))
            ->assertNotFound();
    }

    public function test_invoice_pdf_downloads_when_file_exists_on_configured_disk(): void
    {
        $user = User::factory()->create();
        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '1',
            'label' => null,
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);
        $path = 'utilities/invoices/'.$account->id.'/04-2026.pdf';
        $invoice = Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '04/2026',
            'due_date' => now()->addDays(5)->toDateString(),
            'amount_total' => 10,
            'amount_water' => null,
            'amount_sewage' => null,
            'amount_service' => null,
            'water_consumption_m3' => null,
            'status' => 'pendente',
            'payment_date' => null,
            'pdf_path' => $path,
            'raw_payload' => [],
            'scraped_at' => now(),
            'last_notified_at' => null,
        ]);

        config(['services.utilities.pdf_storage_disk' => 'local']);
        Storage::fake('local');
        Storage::disk('local')->put($path, '%PDF-1.4 fake');

        $this->actingAs($user)
            ->get(action([UtilityInvoicePdfController::class, 'show'], $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_hub_lists_invoices_for_selected_account(): void
    {
        $user = User::factory()->create();
        $account = UtilityAccount::query()->create([
            'kind' => 'embasa',
            'account_ref' => '42',
            'label' => 'Casa',
            'due_day' => 10,
            'reminder_lead_days' => 5,
            'is_active' => true,
        ]);
        Invoice::query()->create([
            'utility_account_id' => $account->id,
            'billing_reference' => '03/2026',
            'due_date' => '2026-03-10',
            'amount_total' => 200,
            'amount_water' => null,
            'amount_sewage' => null,
            'amount_service' => null,
            'water_consumption_m3' => null,
            'status' => 'pendente',
            'payment_date' => null,
            'pdf_path' => null,
            'raw_payload' => [],
            'scraped_at' => now(),
            'last_notified_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('selectAccount', $account->id)
            ->assertSee('03/2026')
            ->assertSee('pendente');
    }
}
