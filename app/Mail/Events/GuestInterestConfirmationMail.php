<?php

declare(strict_types=1);

namespace App\Mail\Events;

use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class GuestInterestConfirmationMail extends Mailable implements ShouldQueue
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
            subject: 'Confirme seu interesse — '.($this->guest->event->title ?? 'evento'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.events.guest-interest-confirmation',
            with: ['guest' => $this->guest],
        );
    }
}
