<?php


namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestStatus;
use App\Jobs\Events\SendGuestTicketEmailJob;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class EventsGuestEmailConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_confirm_token_confirms_guest_and_queues_ticket_email_job(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'slug' => 'confirm-party',
            'status' => EventStatus::Published,
        ]);

        $guest = Guest::factory()->for($event)->create([
            'name' => 'João Teste',
            'email' => 'joao@example.com',
            'status' => GuestStatus::PendingEmail,
            'email_confirmation_token' => 'token-secreto-teste',
            'email_confirmed_at' => null,
        ]);

        $response = $this->get('/events/guest/confirm/'.$guest->email_confirmation_token);

        $response->assertOk();

        $guest->refresh();

        $this->assertSame(GuestStatus::Confirmed, $guest->status);
        $this->assertNotNull($guest->email_confirmed_at);
        $this->assertNull($guest->email_confirmation_token);

        Queue::assertPushed(SendGuestTicketEmailJob::class, fn (SendGuestTicketEmailJob $job): bool => $job->guestId === $guest->id);
    }

    public function test_confirm_when_already_confirmed_with_same_token_shows_message_without_extra_job(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'status' => EventStatus::Published,
        ]);

        $guest = Guest::factory()->for($event)->create([
            'status' => GuestStatus::Confirmed,
            'email_confirmation_token' => 'token-reuso-edge',
            'email_confirmed_at' => now(),
        ]);

        $response = $this->get('/events/guest/confirm/token-reuso-edge');

        $response->assertOk();

        Queue::assertNothingPushed();
    }
}
