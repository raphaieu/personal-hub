<?php

namespace App\Http\Controllers\Utilities;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UtilityInvoicePdfController
{
    public function show(Invoice $invoice): StreamedResponse
    {
        $path = $invoice->pdf_path;
        if ($path === null || $path === '') {
            abort(404);
        }

        $disk = (string) config('services.utilities.pdf_storage_disk', 'local');
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            abort(404);
        }

        $safeRef = str_replace(['/', '\\'], '-', (string) $invoice->billing_reference);
        $filename = $safeRef !== '' ? "{$safeRef}.pdf" : "fatura-{$invoice->id}.pdf";

        return response()->streamDownload(
            static function () use ($storage, $path): void {
                $body = $storage->get($path);
                echo $body;
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
            'attachment'
        );
    }
}
