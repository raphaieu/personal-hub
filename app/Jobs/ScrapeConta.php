<?php

namespace App\Jobs;

use App\Contracts\UtilityScraperClientInterface;
use App\Models\UtilityAccount;
use App\Services\InvoiceService;
use App\Support\UtilityScrapeWindow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ScrapeConta implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $kind,
    ) {
        $this->onQueue('scraping');
    }

    public function handle(
        UtilityScraperClientInterface $utilityClient,
        InvoiceService $invoiceService,
    ): void {
        $kind = $this->validatedKind();

        $accounts = UtilityAccount::query()
            ->where('kind', $kind)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (UtilityAccount $account) => UtilityScrapeWindow::isWithinWindow($account));

        if ($accounts->isEmpty()) {
            Log::info('utilities.scrape_conta.no_accounts_in_window', ['kind' => $kind]);

            return;
        }

        $payload = $kind === 'embasa'
            ? $utilityClient->scrapeEmbasa()
            : $utilityClient->scrapeCoelba();

        if (! ($payload['success'] ?? false)) {
            throw new RuntimeException((string) ($payload['error'] ?? 'Falha no scraping de utilidades.'));
        }

        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $runtimeRef = $this->extractRuntimeAccountRef($kind, $data);

        $targets = $this->resolveTargetAccounts($accounts, $runtimeRef);

        if ($targets->isEmpty()) {
            Log::warning('utilities.scrape_conta.no_matching_account', [
                'kind' => $kind,
                'runtime_ref' => $runtimeRef,
                'candidates' => $accounts->pluck('account_ref')->all(),
            ]);

            return;
        }

        foreach ($targets as $account) {
            $result = $invoiceService->processScrapeResult($payload, $account);
            $account->forceFill(['last_scraped_at' => now()])->save();

            Log::info('utilities.scrape_conta.ingested', [
                'utility_account_id' => $account->id,
                'kind' => $kind,
                'invoices_upserted' => $result['invoices_upserted'],
            ]);
        }
    }

    private function validatedKind(): string
    {
        $kind = strtolower($this->kind);

        if (! in_array($kind, ['embasa', 'coelba'], true)) {
            throw new InvalidArgumentException('ScrapeConta: kind deve ser embasa ou coelba.');
        }

        return $kind;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractRuntimeAccountRef(string $kind, array $data): ?string
    {
        if ($kind === 'embasa') {
            $m = $data['matricula'] ?? null;

            return is_string($m) && $m !== '' ? $m : null;
        }

        $c = $data['codigo_cliente'] ?? null;

        return is_string($c) && $c !== '' ? $c : null;
    }

    /**
     * @param  Collection<int, UtilityAccount>  $accounts
     * @return Collection<int, UtilityAccount>
     */
    private function resolveTargetAccounts(Collection $accounts, ?string $runtimeRef): Collection
    {
        if ($runtimeRef !== null) {
            $matched = $accounts->filter(fn (UtilityAccount $a) => $a->account_ref === $runtimeRef);
            if ($matched->isNotEmpty()) {
                return $matched->values();
            }
        }

        if ($accounts->count() === 1) {
            return $accounts->values();
        }

        return collect();
    }
}
