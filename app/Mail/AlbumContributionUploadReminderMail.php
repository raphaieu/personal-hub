<?php

namespace App\Mail;

use App\Models\Album;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlbumContributionUploadReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Album $album,
        public string $uploadUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Seu link para enviar mídias — '.$this->album->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.albums.contribution-upload-reminder',
        );
    }
}
