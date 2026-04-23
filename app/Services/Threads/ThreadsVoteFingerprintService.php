<?php

namespace App\Services\Threads;

use Illuminate\Http\Request;

final class ThreadsVoteFingerprintService
{
    /**
     * Identificador anonimo diario por visitante (IP + User-Agent + data UTC + salt).
     * Deve permanecer estavel para o mesmo request entre votos no mesmo dia.
     */
    public function forRequest(Request $request): string
    {
        $day = now()->format('Y-m-d');
        $ip = (string) ($request->ip() ?? '');
        $ua = substr((string) $request->userAgent(), 0, 512);
        $salt = (string) config('services.threads.vote_fingerprint_salt', '');

        return hash('sha256', "{$ip}|{$ua}|{$day}|{$salt}");
    }
}
