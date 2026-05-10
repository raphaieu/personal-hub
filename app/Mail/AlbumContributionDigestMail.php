<?php

namespace App\Mail;

use App\Models\Album;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlbumContributionDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function __construct(
        public Album $album,
        public array $lines,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Novas mídias recebidas — '.$this->album->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.albums.contribution-digest',
        );
    }
}
