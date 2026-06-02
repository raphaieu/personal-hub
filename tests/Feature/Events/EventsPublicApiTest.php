<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestStatus;
use App\Jobs\Events\SendGuestTicketEmailJob;
use App\Mail\Events\GuestInterestConfirmationMail;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class EventsPublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    public function test_config_returns_404_for_draft_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'slug' => 'draft-party',
            'status' => EventStatus::Draft,
        ]);

        $response = $this->getJson('/api/v1/events/'.$event->slug.'/config');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_register_creates_guest_and_sends_mail(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'slug' => 'open-party',
            'status' => EventStatus::Published,
            'requires_turnstile' => false,
            'requires_ref' => false,
        ]);

        $photo = UploadedFile::fake()->image('face.jpg', 200, 200);

        $response = $this->post('/api/v1/events/'.$event->slug.'/register', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'consent_terms' => '1',
            'photo' => $photo,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('flow', 'email_confirmation');

        $this->assertDatabaseHas('guests', [
            'event_id' => $event->id,
            'email' => 'maria@example.com',
            'status' => GuestStatus::PendingEmail->value,
        ]);

        Mail::assertQueued(GuestInterestConfirmationMail::class);
        Queue::assertNothingPushed();
    }

    public function test_config_exposes_requires_email_confirmation_flag(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'slug' => 'skip-confirm-party',
            'status' => EventStatus::Published,
            'skip_email_confirmation' => true,
            'requires_turnstile' => false,
        ]);

        $response = $this->getJson('/api/v1/events/'.$event->slug.'/config');

        $response->assertOk()
            ->assertJsonPath('data.registration.requiresEmailConfirmation', false);
    }

    public function test_register_with_skip_email_confirmation_confirms_and_queues_ticket(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'slug' => 'instant-party',
            'status' => EventStatus::Published,
            'requires_turnstile' => false,
            'skip_email_confirmation' => true,
        ]);

        $photo = UploadedFile::fake()->image('face.jpg', 200, 200);

        $response = $this->post('/api/v1/events/'.$event->slug.'/register', [
            'name' => 'Pedro Silva',
            'email' => 'pedro@example.com',
            'consent_terms' => '1',
            'photo' => $photo,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('flow', 'ticket_sent');

        $guest = Guest::query()->where('event_id', $event->id)->where('email', 'pedro@example.com')->first();
        $this->assertNotNull($guest);
        $this->assertSame(GuestStatus::Confirmed, $guest->status);
        $this->assertNotNull($guest->email_confirmed_at);

        Mail::assertNothingQueued();
        Queue::assertPushed(SendGuestTicketEmailJob::class, fn (SendGuestTicketEmailJob $job): bool => $job->guestId === $guest->id);
    }

    public function test_register_returns_409_when_duplicate_email(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'slug' => 'dup-party',
            'status' => EventStatus::Published,
            'requires_turnstile' => false,
        ]);

        Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Existente',
            'email' => 'dup@example.com',
            'status' => GuestStatus::Confirmed,
        ]);

        $photo = UploadedFile::fake()->image('face.jpg');

        $response = $this->post('/api/v1/events/'.$event->slug.'/register', [
            'name' => 'Outro',
            'email' => 'dup@example.com',
            'consent_terms' => '1',
            'photo' => $photo,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }
}
