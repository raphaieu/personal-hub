<?php


namespace App\Services\Events;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class EventQrCodeService
{
    public function qrImageDataUri(string $payload): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
            'scale' => 10,
            'imageBase64' => true,
        ]);

        return (new QRCode($options))->render($payload);
    }

    public function qrPngBytes(string $payload): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
            'scale' => 10,
            'imageBase64' => false,
        ]);

        return (new QRCode($options))->render($payload);
    }
}
