<?php

namespace App\Http\Controllers\Utilities;

use App\Models\Invoice;
use App\Support\UtilityInvoiceDisk;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UtilityInvoicePdfController
{
    public function show(Invoice $invoice): StreamedResponse
    {
        $path = $invoice->pdf_path;
        if ($path === null || $path === '') {
            abort(404);
        }

        if (! UtilityInvoiceDisk::exists($path)) {
            abort(404);
        }

        $body = UtilityInvoiceDisk::get($path);
        if ($body === null) {
            abort(404);
        }

        $safeRef = str_replace(['/', '\\'], '-', (string) $invoice->billing_reference);
        $filename = $safeRef !== '' ? "{$safeRef}.pdf" : "fatura-{$invoice->id}.pdf";

        return response()->streamDownload(
            static function () use ($body): void {
                echo $body;
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
            'attachment'
        );
    }
}
