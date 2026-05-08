<?php

namespace App\Services\Albums;

use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AlbumAccessService
{
    public function canAccess(Request $request, Album $album): bool
    {
        if ($album->is_locked) {
            return false;
        }

        return match ((string) $album->access_type) {
            'public' => true,
            'password' => $this->hasPasswordSession($request, $album),
            default => false,
        };
    }

    public function authenticateWithPassword(Request $request, Album $album, string $password): bool
    {
        if ((string) $album->access_type !== 'password') {
            return false;
        }

        $hash = $album->password_hash;
        if (! is_string($hash) || $hash === '') {
            return false;
        }

        if (! Hash::check($password, $hash)) {
            return false;
        }

        $request->session()->put($this->sessionKey($album), true);

        return true;
    }

    public function hasPasswordSession(Request $request, Album $album): bool
    {
        return (bool) $request->session()->get($this->sessionKey($album), false);
    }

    private function sessionKey(Album $album): string
    {
        return 'albums.auth.'.$album->id;
    }
}
