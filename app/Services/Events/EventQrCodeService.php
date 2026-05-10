<?php

declare(strict_types=1);

namespace App\Services\Events;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class EventQrCodeService
{
    /**
     * Data URI (SVG) for {@code <img src="...">} and DomPDF.
     *
     * Nota: {@code simple-qrcode} usa Imagick para PNG; SVG evita exigir a extensão imagick no PHP.
     */
    public function qrImageDataUri(string $payload): string
    {
        $svg = QrCode::format('svg')->size(280)->margin(1)->generate($payload);

        return 'data:image/svg+xml;base64,'.base64_encode((string) $svg);
    }
}
