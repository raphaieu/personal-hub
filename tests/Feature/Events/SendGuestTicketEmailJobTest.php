<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestStatus;
use App\Jobs\Events\SendGuestTicketEmailJob;
use App\Mail\Events\GuestTicketMail;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SendGuestTicketEmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_ticket_mail_with_pdf(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'status' => EventStatus::Published,
        ]);

        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::Confirmed,
        ]);

        (new SendGuestTicketEmailJob($guest->id))->handle();

        Mail::assertSent(GuestTicketMail::class, fn (GuestTicketMail $mail): bool => $mail->guest->is($guest));
    }
}
