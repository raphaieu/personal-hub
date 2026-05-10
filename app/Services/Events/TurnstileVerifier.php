<?php

declare(strict_types=1);

namespace App\Services\Events;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TurnstileVerifier
{
    public function verify(?string $token): bool
    {
        if (! config('events.turnstile.enabled')) {
            return true;
        }

        $secret = config('events.turnstile.secret_key');
        if ($secret === null || $secret === '') {
            if (app()->environment('local', 'testing')) {
                return true;
            }
            Log::warning('events.turnstile.secret_missing');

            return false;
        }

        if ($token === null || $token === '') {
            return false;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
            ]);

        if (! $response->successful()) {
            return false;
        }

        return (bool) ($response->json('success') ?? false);
    }
}
