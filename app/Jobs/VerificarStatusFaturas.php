<?php

namespace App\Jobs;

use App\Models\UtilityAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Reenfileira scrape por concessionária quando há fatura não paga (fora da janela do agendamento principal).
 */
class VerificarStatusFaturas implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $kinds = UtilityAccount::query()
            ->where('is_active', true)
            ->whereHas('invoices', function ($q): void {
                $q->where('status', '!=', 'pago');
            })
            ->pluck('kind')
            ->unique()
            ->filter(fn (mixed $kind): bool => is_string($kind) && in_array($kind, ['embasa', 'coelba'], true))
            ->values();

        if ($kinds->isEmpty()) {
            Log::info('utilities.verificar_status_faturas.no_pending_invoices');

            return;
        }

        foreach ($kinds as $kind) {
            Bus::dispatch(new ScrapeConta((string) $kind, ignoreScrapeWindow: true, force: false));
        }

        Log::info('utilities.verificar_status_faturas.dispatched', [
            'kinds' => $kinds->all(),
        ]);
    }
}
