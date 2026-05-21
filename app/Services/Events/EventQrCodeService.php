<?php


namespace App\Services\Events;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class EventQrCodeService
{
    /**
     * Data URI (PNG) for {@code <img src="...">} and DomPDF.
     *
     * Gera PNG via GD (sem Imagick) para compatibilidade com leitores de e-mail.
     */
    public function qrImageDataUri(string $payload): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
            'scale' => 10,
            'imageBase64' => true,
        ]);

        $qrcode = new QRCode($options);

        return $qrcode->render($payload);
    }
}
