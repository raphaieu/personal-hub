<?php


namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestStatus;
use App\Mail\Events\GuestInterestConfirmationMail;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class EventsPublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
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
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('guests', [
            'event_id' => $event->id,
            'email' => 'maria@example.com',
            'status' => GuestStatus::PendingEmail->value,
        ]);

        Mail::assertQueued(GuestInterestConfirmationMail::class);
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
