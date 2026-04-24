<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificarVencimento implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(InvoiceService $invoiceService): void
    {
        $daysAhead = max(0, (int) config('services.utilities.notify_days_ahead', 7));

        /** @var EloquentCollection<int, Invoice> $invoices */
        $invoices = Invoice::query()
            ->with('utilityAccount')
            ->where('status', '!=', 'pago')
            ->where(function (Builder $q) use ($daysAhead): void {
                $q->whereDate('due_date', '<', now()->toDateString())
                    ->orWhere(function (Builder $q2) use ($daysAhead): void {
                        $q2->whereDate('due_date', '>=', now()->toDateString())
                            ->whereDate('due_date', '<=', now()->addDays($daysAhead)->toDateString());
                    });
            })
            ->where(function (Builder $q): void {
                $q->whereNull('last_notified_at')
                    ->orWhereDate('last_notified_at', '!=', now()->toDateString());
            })
            ->orderBy('due_date')
            ->get();

        if ($invoices->isEmpty()) {
            Log::info('utilities.notificar_vencimento.nothing_to_notify');

            return;
        }

        $sent = 0;
        foreach ($invoices as $invoice) {
            try {
                if ($invoiceService->notifyHomeGroup($invoice)) {
                    $invoice->forceFill(['last_notified_at' => now()])->save();
                    $sent++;
                }
            } catch (Throwable $e) {
                Log::error('utilities.notificar_vencimento.invoice_failed', [
                    'invoice_id' => $invoice->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Log::info('utilities.notificar_vencimento.completed', [
            'candidates' => $invoices->count(),
            'sent' => $sent,
        ]);
    }
}
