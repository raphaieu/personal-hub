<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\UtilityAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Decide se vale chamar o Playwright para uma conta, com base na última fatura do ciclo (referência mês atual ou mais recente por vencimento).
 *
 * Regra (sem --force): não chama Playwright se a fatura está paga; nem se status é {@see a_vencer} ou pendente e hoje é **antes** do vencimento.
 * Chama se não há fatura, se vence hoje ou já passou (ainda não pago), ou se status é vencida/processando/outros.
 */
final class UtilityAccountScrapeGate
{
    /**
     * @param  Collection<int, UtilityAccount>  $accounts
     */
    public static function anyRequiresPlaywright(Collection $accounts, bool $force): bool
    {
        if ($force) {
            return true;
        }

        foreach ($accounts as $account) {
            if (self::accountRequiresPlaywright($account)) {
                return true;
            }
        }

        return false;
    }

    public static function accountRequiresPlaywright(UtilityAccount $account): bool
    {
        $invoice = self::resolveReferenceInvoice($account);
        if ($invoice === null) {
            return true;
        }

        if ($invoice->status === 'pago') {
            return false;
        }

        $today = now()->startOfDay();
        $due = Carbon::parse($invoice->due_date)->startOfDay();

        if (in_array($invoice->status, ['a_vencer', 'pendente'], true)) {
            return ! $today->lt($due);
        }

        return true;
    }

    private static function resolveReferenceInvoice(UtilityAccount $account): ?Invoice
    {
        $ref = now()->format('m/Y');
        $match = Invoice::query()
            ->where('utility_account_id', $account->id)
            ->where('billing_reference', $ref)
            ->first();

        if ($match instanceof Invoice) {
            return $match;
        }

        return Invoice::query()
            ->where('utility_account_id', $account->id)
            ->orderByDesc('due_date')
            ->first();
    }
}
