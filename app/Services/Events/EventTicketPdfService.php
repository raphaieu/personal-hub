<?php


namespace App\Services\Events;

use App\Models\Guest;
use Barryvdh\DomPDF\Facade\Pdf;

final class EventTicketPdfService
{
    public function __construct(
        private readonly EventGuestInvitePresentation $presentation,
    ) {}

    public function renderPdf(Guest $guest): string
    {
        $guest->loadMissing('event');
        $data = $this->presentation->viewData($guest);
        $view = $this->resolvePdfView($guest);

        return Pdf::loadView($view, $data)->output();
    }

    private function resolvePdfView(Guest $guest): string
    {
        $key = $guest->event->invite_template_key;

        if ($key !== null && $key !== '' && view()->exists('pdf.events.custom.'.$key)) {
            return 'pdf.events.custom.'.$key;
        }

        return 'pdf.events.invite-default';
    }
}
