<?php


namespace App\Mail\Events;

use App\Models\Guest;
use App\Services\Events\EventGuestInvitePresentation;
use App\Services\Events\EventTicketPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class GuestInviteMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Guest $guest)
    {
        $this->guest->loadMissing('event');
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Convite — '.($this->guest->event->title ?? 'evento'),
        );
    }

    public function content(): Content
    {
        $this->guest->loadMissing('event');

        return app(EventGuestInvitePresentation::class)->mailContent($this->guest);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => app(EventTicketPdfService::class)->renderPdf($this->guest),
                $this->pdfFileName(),
                ['mime' => 'application/pdf'],
            ),
        ];
    }

    private function pdfFileName(): string
    {
        $this->guest->loadMissing('event');
        $slug = $this->guest->event->slug ?? 'evento';

        return 'ingresso-'.$slug.'.pdf';
    }
}
