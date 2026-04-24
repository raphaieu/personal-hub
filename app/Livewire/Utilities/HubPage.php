<?php

namespace App\Livewire\Utilities;

use App\Jobs\ScrapeConta;
use App\Models\Invoice;
use App\Models\UtilityAccount;
use App\Support\UtilityInvoiceDisk;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class HubPage extends Component
{
    use WithPagination;

    #[Url(as: 'conta')]
    public ?int $selectedAccountId = null;

    public ?int $editingId = null;

    public string $formKind = 'embasa';

    public string $formAccountRef = '';

    public string $formLabel = '';

    public int $formDueDay = 10;

    public int $formReminderLeadDays = 5;

    public bool $formIsActive = true;

    public function mount(): void
    {
        if ($this->selectedAccountId !== null && ! UtilityAccount::query()->whereKey($this->selectedAccountId)->exists()) {
            $this->selectedAccountId = null;
        }
    }

    public function updatedSelectedAccountId(mixed $value): void
    {
        $this->resetPage('invoicesPage');
        if ($value !== null && $value !== '' && ! UtilityAccount::query()->whereKey((int) $value)->exists()) {
            $this->selectedAccountId = null;
        }
    }

    public function selectAccount(?int $id): void
    {
        $this->selectedAccountId = $id;
        $this->resetPage('invoicesPage');
    }

    public function startCreate(): void
    {
        $this->editingId = null;
        $this->resetFormDefaults();
        $this->resetValidation();
    }

    public function startEdit(int $id): void
    {
        $account = UtilityAccount::query()->findOrFail($id);
        $this->editingId = $account->id;
        $this->formKind = $account->kind;
        $this->formAccountRef = $account->account_ref;
        $this->formLabel = (string) ($account->label ?? '');
        $this->formDueDay = (int) $account->due_day;
        $this->formReminderLeadDays = (int) $account->reminder_lead_days;
        $this->formIsActive = (bool) $account->is_active;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetFormDefaults();
        $this->resetValidation();
    }

    public function saveAccount(): void
    {
        $this->validate([
            'formKind' => ['required', Rule::in(['embasa', 'coelba'])],
            'formAccountRef' => ['required', 'string', 'max:255'],
            'formLabel' => ['nullable', 'string', 'max:255'],
            'formDueDay' => ['required', 'integer', 'min:1', 'max:31'],
            'formReminderLeadDays' => ['required', 'integer', 'min:0', 'max:90'],
            'formIsActive' => ['boolean'],
        ]);

        $payload = [
            'kind' => $this->formKind,
            'account_ref' => $this->formAccountRef,
            'label' => $this->formLabel !== '' ? $this->formLabel : null,
            'due_day' => $this->formDueDay,
            'reminder_lead_days' => $this->formReminderLeadDays,
            'is_active' => $this->formIsActive,
        ];

        if ($this->editingId !== null) {
            $account = UtilityAccount::query()->findOrFail($this->editingId);
            $payload['kind'] = $account->kind;
            $account->forceFill($payload)->save();
            session()->flash('utilities_hub_notice', 'Conta atualizada.');
        } else {
            UtilityAccount::query()->create($payload);
            session()->flash('utilities_hub_notice', 'Conta cadastrada.');
        }

        $this->editingId = null;
        $this->resetFormDefaults();
    }

    public function toggleActive(int $id): void
    {
        $account = UtilityAccount::query()->findOrFail($id);
        $account->forceFill(['is_active' => ! $account->is_active])->save();
        session()->flash('utilities_hub_notice', 'Status da conta atualizado.');
    }

    public function scrapeNow(int $accountId): void
    {
        $account = UtilityAccount::query()->findOrFail($accountId);
        ScrapeConta::dispatch($account->kind, true, true);
        session()->flash(
            'utilities_hub_notice',
            "Scrape {$account->kind} enfileirado (fila scraping, ignora janela e força Playwright)."
        );
    }

    /**
     * @return array<int, bool>
     */
    private function invoicePdfAvailability(iterable $invoices): array
    {
        $map = [];
        foreach ($invoices as $invoice) {
            $path = $invoice->pdf_path;
            $map[(int) $invoice->id] = is_string($path) && $path !== '' && UtilityInvoiceDisk::exists($path);
        }

        return $map;
    }

    private function resetFormDefaults(): void
    {
        $this->formKind = 'embasa';
        $this->formAccountRef = '';
        $this->formLabel = '';
        $this->formDueDay = 10;
        $this->formReminderLeadDays = 5;
        $this->formIsActive = true;
    }

    public function render()
    {
        $accounts = UtilityAccount::query()
            ->orderBy('kind')
            ->orderBy('label')
            ->orderBy('account_ref')
            ->orderBy('id')
            ->get();

        $selectedAccount = null;
        $invoices = null;
        $invoicePdfOk = [];

        if ($this->selectedAccountId !== null) {
            $selectedAccount = UtilityAccount::query()->find($this->selectedAccountId);
            if ($selectedAccount !== null) {
                $invoices = Invoice::query()
                    ->where('utility_account_id', $selectedAccount->id)
                    ->orderByDesc('due_date')
                    ->orderByDesc('id')
                    ->paginate(20, ['*'], 'invoicesPage');
                $invoicePdfOk = $this->invoicePdfAvailability($invoices);
            }
        }

        return view('livewire.utilities.hub-page', [
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'invoices' => $invoices,
            'invoicePdfOk' => $invoicePdfOk,
        ])->layout('layouts.app');
    }
}
