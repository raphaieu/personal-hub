<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\UtilityAccount;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class InvoiceService
{
    public function __construct(
        private readonly EvolutionService $evolution,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Resposta normalizada do cliente de utilidades (contrato `UtilityScraperClientInterface`).
     * @return array{invoices_upserted: int, billing_references: list<string>}
     */
    public function processScrapeResult(array $payload, UtilityAccount $account): array
    {
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $faturas = isset($data['faturas']) && is_array($data['faturas']) ? $data['faturas'] : [];
        $scrapedAt = $this->parseScrapedAt($payload, $data);

        $pdfHostPath = isset($data['pdf_path']) && is_string($data['pdf_path']) && $data['pdf_path'] !== ''
            ? $data['pdf_path']
            : null;
        $billingRefForPdf = $this->pickBillingReferenceForPdf($faturas, (string) $account->kind);

        $upserted = 0;
        $refs = [];

        foreach ($faturas as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = $this->mapFaturaRow($row, (string) $account->kind);
            if ($mapped === null) {
                continue;
            }

            $billingRef = $mapped['billing_reference'];
            $refs[] = $billingRef;

            $storedPdfPath = null;
            if ($pdfHostPath !== null && $billingRefForPdf !== null && $billingRefForPdf === $billingRef) {
                $storedPdfPath = $this->uploadPdf($pdfHostPath, $account, $billingRef);
            }

            $raw = $row;
            if ($pdfHostPath !== null && $storedPdfPath === null) {
                $raw['playwright_pdf_path'] = $pdfHostPath;
            }

            Invoice::query()->updateOrCreate(
                [
                    'utility_account_id' => $account->id,
                    'billing_reference' => $billingRef,
                ],
                [
                    'due_date' => $mapped['due_date'],
                    'amount_total' => $mapped['amount_total'],
                    'amount_water' => $mapped['amount_water'],
                    'amount_sewage' => $mapped['amount_sewage'],
                    'amount_service' => $mapped['amount_service'],
                    'water_consumption_m3' => $mapped['water_consumption_m3'],
                    'status' => $mapped['status'],
                    'payment_date' => $mapped['payment_date'],
                    'pdf_path' => $storedPdfPath,
                    'raw_payload' => $raw,
                    'scraped_at' => $scrapedAt,
                ]
            );

            $upserted++;
        }

        return [
            'invoices_upserted' => $upserted,
            'billing_references' => $refs,
        ];
    }

    /**
     * Copia PDF acessível no filesystem do PHP para o disco configurado (`services.utilities.pdf_storage_disk`: `local` ou `s3`).
     *
     * @return non-empty-string|null Caminho relativo ao disco escolhido (chave no bucket S3/MinIO) ou null se arquivo inexistente / falha.
     */
    public function uploadPdf(string $localPath, UtilityAccount $account, string $billingReference): ?string
    {
        $billingReference = $this->sanitizePathSegment($billingReference);

        if ($billingReference === '' || ! is_file($localPath) || ! is_readable($localPath)) {
            return null;
        }

        $relative = sprintf('utilities/invoices/%d/%s.pdf', $account->id, $billingReference);
        $disk = (string) config('services.utilities.pdf_storage_disk', 'local');

        try {
            $binary = @file_get_contents($localPath);
            if ($binary === false) {
                return null;
            }

            Storage::disk($disk)->put($relative, $binary);
        } catch (Throwable $e) {
            Log::warning('invoice.pdf_upload_failed', [
                'utility_account_id' => $account->id,
                'billing_reference' => $billingReference,
                'disk' => $disk,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($this->shouldDeleteSourcePdfAfterUpload($disk) && is_file($localPath)) {
            if (! @unlink($localPath)) {
                Log::warning('invoice.pdf_source_delete_failed', [
                    'path' => $localPath,
                    'utility_account_id' => $account->id,
                    'hint' => 'Path precisa existir no mesmo host/volume que o PHP (ex.: bind mount com o container Playwright).',
                ]);
            }
        }

        return $relative;
    }

    private function shouldDeleteSourcePdfAfterUpload(string $disk): bool
    {
        $raw = config('services.utilities.delete_source_pdf_after_upload');

        if ($raw === null || $raw === '') {
            return $disk === 's3';
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Envia lembrete de fatura no grupo configurado (Evolution `sendText`).
     *
     * @return bool true se a mensagem foi enviada; false se ignorado (sem JID ou Evolution incompleta).
     */
    public function notifyHomeGroup(Invoice $invoice): bool
    {
        $jid = config('services.whatsapp.utilities_home_group_jid');

        if (! is_string($jid) || $jid === '') {
            Log::debug('invoice.notify_skipped_no_jid', ['invoice_id' => $invoice->id]);

            return false;
        }

        if (! $this->evolution->isConfigured()) {
            Log::warning('invoice.notify_skipped_evolution_not_configured', ['invoice_id' => $invoice->id]);

            return false;
        }

        $invoice->loadMissing('utilityAccount');
        $account = $invoice->utilityAccount;
        if ($account === null) {
            Log::warning('invoice.notify_skipped_no_account', ['invoice_id' => $invoice->id]);

            return false;
        }

        $this->evolution->sendText($jid, $this->formatInvoiceReminderMessage($invoice, $account));

        return true;
    }

    private function formatInvoiceReminderMessage(Invoice $invoice, UtilityAccount $account): string
    {
        $label = $account->label !== null && $account->label !== ''
            ? $account->label
            : strtoupper((string) $account->kind);

        $valor = $invoice->amount_total !== null
            ? 'R$ '.number_format((float) $invoice->amount_total, 2, ',', '.')
            : 'valor não informado';

        return sprintf(
            "Lembrete de conta (%s)\nRef.: %s\nVencimento: %s\nStatus: %s\n%s",
            $label,
            $invoice->billing_reference,
            Carbon::parse($invoice->due_date)->format('d/m/Y'),
            $invoice->status,
            $valor
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     billing_reference: string,
     *     due_date: CarbonInterface,
     *     amount_total: ?string,
     *     amount_water: ?string,
     *     amount_sewage: ?string,
     *     amount_service: ?string,
     *     water_consumption_m3: ?int,
     *     status: string,
     *     payment_date: ?CarbonInterface
     * }|null
     */
    private function mapFaturaRow(array $row, string $kind): ?array
    {
        $ref = $row['referencia'] ?? null;
        if (! is_string($ref) || $ref === '') {
            return null;
        }

        $vencimento = $row['vencimento'] ?? null;
        if (! is_string($vencimento) || $vencimento === '') {
            return null;
        }

        try {
            $dueDate = Carbon::parse($vencimento)->startOfDay();
        } catch (Throwable) {
            Log::warning('invoice.skip_invalid_due_date', ['referencia' => $ref, 'vencimento' => $vencimento]);

            return null;
        }

        if ($kind === 'coelba') {
            $amountTotal = $this->decimalString($row['valor_fatura'] ?? null);
        } else {
            $amountTotal = $this->decimalString($row['valor_total'] ?? null);
        }

        $statusRaw = $row['status'] ?? $row['situacao'] ?? 'pendente';
        $status = is_string($statusRaw) ? $this->normalizeStatus($statusRaw) : 'pendente';

        $paymentDate = null;
        if (isset($row['data_pagamento']) && is_string($row['data_pagamento']) && $row['data_pagamento'] !== '') {
            try {
                $paymentDate = Carbon::parse($row['data_pagamento'])->startOfDay();
            } catch (Throwable) {
                $paymentDate = null;
            }
        }

        $water = $kind === 'embasa' ? $this->decimalString($row['valor_agua'] ?? null) : null;
        $sewage = $kind === 'embasa' ? $this->decimalString($row['valor_esgoto'] ?? null) : null;
        $service = $kind === 'embasa' ? $this->decimalString($row['valor_servico'] ?? null) : null;
        $consumption = null;
        if ($kind === 'embasa' && isset($row['consumo_m3'])) {
            $c = $row['consumo_m3'];
            $consumption = is_int($c) ? $c : (is_numeric($c) ? (int) $c : null);
        }

        return [
            'billing_reference' => $ref,
            'due_date' => $dueDate,
            'amount_total' => $amountTotal,
            'amount_water' => $water,
            'amount_sewage' => $sewage,
            'amount_service' => $service,
            'water_consumption_m3' => $consumption,
            'status' => $status,
            'payment_date' => $paymentDate,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $faturas
     */
    private function pickBillingReferenceForPdf(array $faturas, string $kind): ?string
    {
        $preferred = [];

        foreach ($faturas as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = $this->mapFaturaRow($row, $kind);
            if ($mapped === null) {
                continue;
            }

            $status = $mapped['status'];
            if (in_array($status, ['pendente', 'vencida', 'a_vencer', 'processando'], true)) {
                $preferred[] = $mapped;
            }
        }

        $pool = $preferred !== [] ? $preferred : array_values(array_filter(array_map(function ($row) use ($kind) {
            return is_array($row) ? $this->mapFaturaRow($row, $kind) : null;
        }, $faturas)));

        if ($pool === []) {
            return null;
        }

        usort($pool, function (array $a, array $b): int {
            if ($a['due_date']->eq($b['due_date'])) {
                return 0;
            }

            return $a['due_date']->lt($b['due_date']) ? -1 : 1;
        });

        return $pool[0]['billing_reference'];
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = ['pago', 'pendente', 'processando', 'a_vencer', 'vencida'];

        if (in_array($status, $allowed, true)) {
            return $status;
        }

        return 'pendente';
    }

    private function decimalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        if (is_string($value) && is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    private function parseScrapedAt(array $payload, array $data): CarbonInterface
    {
        $raw = $payload['scraped_at'] ?? $data['scraped_at'] ?? null;

        if (is_string($raw) && $raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (Throwable) {
                // fallthrough
            }
        }

        return now();
    }

    private function sanitizePathSegment(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $value) ?? '';
    }
}
