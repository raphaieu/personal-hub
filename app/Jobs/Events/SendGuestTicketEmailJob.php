<?php

namespace App\Jobs\Events;

use App\Mail\Events\GuestTicketMail;
use App\Models\Guest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendGuestTicketEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $guestId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $guest = Guest::query()->with('event')->find($this->guestId);

        if ($guest === null) {
            return;
        }

        Mail::to($guest->email)->send(new GuestTicketMail($guest));
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('events.ticket_email_failed', [
            'guest_id' => $this->guestId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
