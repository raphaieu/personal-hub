<?php


namespace Database\Factories;

use App\Enums\Events\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
final class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'slug' => fake()->unique()->slug(3),
            'status' => EventStatus::Published,
            'title' => fake()->sentence(3),
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeeks(2),
            'timezone' => 'America/Sao_Paulo',
            'capacity' => null,
            'requires_ref' => false,
            'requires_turnstile' => false,
            'requires_photo' => true,
            'registration_open' => true,
            'skip_email_confirmation' => false,
            'guest_form_schema_json' => null,
            'invite_template_key' => null,
            'closed_message' => null,
            'terms_url' => null,
            'privacy_url' => null,
            'album_id' => null,
        ];
    }
}
