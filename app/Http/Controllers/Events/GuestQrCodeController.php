<?php


namespace App\Http\Controllers\Events;

use App\Models\Guest;
use App\Services\Events\EventCheckInUrlGenerator;
use App\Services\Events\EventQrCodeService;

final class GuestQrCodeController
{
    public function __invoke(Guest $guest): \Illuminate\Http\Response
    {
        $checkInUrl = app(EventCheckInUrlGenerator::class)->url($guest);
        $png = app(EventQrCodeService::class)->qrPngBytes($checkInUrl);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
