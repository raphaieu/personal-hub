<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class EventsGuestPhotoStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    public function test_guest_photo_is_stored_on_the_configured_disk(): void
    {
        Config::set('events.guest_photos_disk', 's3');
        Storage::fake('s3');

        $event = Event::factory()->for(User::factory(), 'owner')->create([
            'status' => EventStatus::Published,
            'requires_photo' => true,
        ]);

        $this->post('/api/v1/events/'.$event->slug.'/register', [
            'name' => 'Foto Feliz',
            'email' => 'foto@example.com',
            'consent_terms' => '1',
            'photo' => UploadedFile::fake()->image('rosto.jpg', 200, 200),
        ], ['Accept' => 'application/json'])->assertCreated();

        $guest = Guest::query()->where('event_id', $event->id)->where('email', 'foto@example.com')->firstOrFail();

        $this->assertNotNull($guest->photo_path);
        Storage::disk('s3')->assertExists($guest->photo_path);
    }

    public function test_photo_temporary_url_handles_driver_support_and_missing_path(): void
    {
        Storage::fake('s3');

        $guest = Guest::factory()->create(['photo_path' => 'events/guests/x/foto.jpg']);
        Storage::disk('s3')->put('events/guests/x/foto.jpg', 'fake');

        // O fake do Laravel gera URL assinada local; em S3 real seria presigned do MinIO.
        $url = $guest->photoTemporaryUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('events/guests/x/foto.jpg', $url);

        // Sem foto, sempre null (e nunca lança exceção).
        $this->assertNull(Guest::factory()->create(['photo_path' => null])->photoTemporaryUrl());
    }
}
