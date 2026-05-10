<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Events\GuestStatus;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
final class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'referral_link_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'photo_path' => null,
            'birth_year' => null,
            'custom_data' => null,
            'status' => GuestStatus::Confirmed,
            'consent_terms_at' => now(),
            'invite_sent_at' => null,
            'email_confirmation_token' => null,
            'email_confirmed_at' => now(),
            'checked_in_at' => null,
        ];
    }
}
