<?php


namespace App\Services\Events;

use App\Models\Guest;
use Illuminate\Mail\Mailables\Content;

final class EventGuestInvitePresentation
{
    public function __construct(
        private readonly EventCheckInUrlGenerator $checkInUrls,
        private readonly EventQrCodeService $qrCodes,
    ) {}

    /**
     * @return array{guest: Guest, checkInUrl: string, qrDataUri: string, qrUrl: string}
     */
    public function viewData(Guest $guest): array
    {
        $guest->loadMissing('event');

        $checkInUrl = $this->checkInUrls->url($guest);

        return [
            'guest' => $guest,
            'checkInUrl' => $checkInUrl,
            'qrDataUri' => $this->qrCodes->qrImageDataUri($checkInUrl),
            'qrUrl' => route('events.guest.qr', $guest),
        ];
    }

    public function mailContent(Guest $guest): Content
    {
        $data = $this->viewData($guest);
        $key = $guest->event->invite_template_key;

        if ($key !== null && $key !== '' && view()->exists('mail.events.custom.'.$key)) {
            return new Content(
                htmlString: view('mail.events.custom.'.$key, $data)->render(),
            );
        }

        return new Content(
            markdown: 'mail.events.invite-default',
            with: ['guest' => $guest],
        );
    }
}
