<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Guest;
use Illuminate\Support\Facades\URL;

final class EventCheckInUrlGenerator
{
    public function url(Guest $guest): string
    {
        $guest->loadMissing('event');

        return URL::route('events.checkin.show', [], absolute: true)
            .'?'.http_build_query([
                'event' => (string) $guest->event_id,
                'guest' => (string) $guest->id,
            ]);
    }
}
