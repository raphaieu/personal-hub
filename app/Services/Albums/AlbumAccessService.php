<?php

namespace App\Services\Albums;

use App\Models\AccessAttempt;
use App\Models\Album;
use App\Models\AlbumLockout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AlbumAccessService
{
    public function canAccess(Request $request, Album $album): bool
    {
        if ($album->is_locked) {
            return false;
        }

        if ($this->hasActiveIpLockout($request, $album)) {
            return false;
        }

        return match ((string) $album->access_type) {
            'public' => true,
            'password' => $this->hasPasswordSession($request, $album),
            'token' => $this->authorizeTokenAccess($request, $album),
            'one_time' => $this->authorizeOneTimeAccess($request, $album),
            default => false,
        };
    }

    public function authenticateWithPassword(Request $request, Album $album, string $password): bool
    {
        if ((string) $album->access_type !== 'password') {
            return false;
        }

        if ($this->hasActiveIpLockout($request, $album)) {
            return false;
        }

        $hash = $album->password_hash;
        if (! is_string($hash) || $hash === '') {
            $this->recordAccessAttempt($request, $album, false);

            return false;
        }

        if (! Hash::check($password, $hash)) {
            $this->recordAccessAttempt($request, $album, false);
            $this->applyLockoutIfNeeded($request, $album);

            return false;
        }

        $this->recordAccessAttempt($request, $album, true);
        $request->session()->put($this->sessionKey($album), true);

        return true;
    }

    public function hasPasswordSession(Request $request, Album $album): bool
    {
        return (bool) $request->session()->get($this->sessionKey($album), false);
    }

    public function unlockLockout(AlbumLockout $lockout): void
    {
        $lockout->forceFill([
            'unlocked_at' => now(),
            'unlocked_by' => 'admin',
        ])->save();
    }

    private function authorizeTokenAccess(Request $request, Album $album): bool
    {
        if ((bool) $request->session()->get($this->tokenSessionKey($album), false)) {
            return true;
        }

        if (! $this->hasValidTokenInQuery($request, $album)) {
            return false;
        }

        $request->session()->put($this->tokenSessionKey($album), true);

        return true;
    }

    private function authorizeOneTimeAccess(Request $request, Album $album): bool
    {
        if ((bool) $request->session()->get($this->oneTimeSessionKey($album), false)) {
            return true;
        }

        if ($album->one_time_used_at !== null) {
            return false;
        }

        if (! $this->hasValidTokenInQuery($request, $album)) {
            return false;
        }

        $album->forceFill(['one_time_used_at' => now()])->save();
        $request->session()->put($this->oneTimeSessionKey($album), true);

        return true;
    }

    private function hasValidTokenInQuery(Request $request, Album $album): bool
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            return false;
        }

        if (! is_string($album->token) || $album->token === '' || ! hash_equals($album->token, $token)) {
            return false;
        }

        if ($album->token_expires_at !== null && Carbon::parse($album->token_expires_at)->isPast()) {
            return false;
        }

        return true;
    }

    private function applyLockoutIfNeeded(Request $request, Album $album): void
    {
        $maxAttempts = max(1, (int) config('services.albums.brute_force_max_attempts', 5));
        $ip = $this->resolveIp($request);

        if (AlbumLockout::query()->where('album_id', $album->id)->where('ip', $ip)->whereNull('unlocked_at')->exists()) {
            return;
        }

        $failedCount = AccessAttempt::query()
            ->where('album_id', $album->id)
            ->where('ip', $ip)
            ->where('succeeded', false)
            ->count();

        if ($failedCount < $maxAttempts) {
            return;
        }

        AlbumLockout::query()->create([
            'album_id' => $album->id,
            'ip' => $ip,
            'locked_at' => now(),
        ]);
    }

    private function hasActiveIpLockout(Request $request, Album $album): bool
    {
        $ip = $this->resolveIp($request);

        return AlbumLockout::query()
            ->where('album_id', $album->id)
            ->where('ip', $ip)
            ->whereNull('unlocked_at')
            ->exists();
    }

    private function recordAccessAttempt(Request $request, Album $album, bool $succeeded): void
    {
        AccessAttempt::query()->create([
            'album_id' => $album->id,
            'ip' => $this->resolveIp($request),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'attempted_at' => now(),
            'succeeded' => $succeeded,
        ]);
    }

    private function resolveIp(Request $request): string
    {
        return (string) ($request->ip() ?: 'unknown');
    }

    private function sessionKey(Album $album): string
    {
        return 'albums.auth.'.$album->id;
    }

    private function tokenSessionKey(Album $album): string
    {
        return 'albums.token.'.$album->id;
    }

    private function oneTimeSessionKey(Album $album): string
    {
        return 'albums.one_time.'.$album->id;
    }
}
