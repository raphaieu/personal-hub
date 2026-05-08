<?php

namespace App\Services\Albums;

use App\Mail\AlbumContributionUploadReminderMail;
use App\Mail\AlbumContributionVerifyMail;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Models\Contributor;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AlbumContributionService
{
    public function isValidInvite(Album $album, string $token): bool
    {
        $expected = $album->contribution_invite_token;
        if (! is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public function ensureInviteToken(Album $album): string
    {
        if (is_string($album->contribution_invite_token) && $album->contribution_invite_token !== '') {
            return $album->contribution_invite_token;
        }

        $token = Str::random(40);
        $album->forceFill(['contribution_invite_token' => $token])->save();

        return $token;
    }

    public function rotateInviteToken(Album $album): string
    {
        $token = Str::random(40);
        $album->forceFill(['contribution_invite_token' => $token])->save();

        return $token;
    }

    public function revokeAllUploadTokens(Album $album): void
    {
        Contributor::query()
            ->where('album_id', $album->id)
            ->update([
                'upload_token' => null,
                'upload_expires_at' => null,
            ]);
    }

    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function requestEmailVerification(Album $album, string $inviteToken, string $email): void
    {
        if ($album->is_locked) {
            abort(404);
        }

        if (! $this->isValidInvite($album, $inviteToken)) {
            abort(404);
        }

        $email = $this->normalizeEmail($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Informe um e-mail válido.',
            ]);
        }

        $existing = Contributor::query()
            ->where('album_id', $album->id)
            ->where('email', $email)
            ->first();

        if ($existing !== null
            && $existing->email_verified
            && is_string($existing->upload_token) && $existing->upload_token !== ''
            && $existing->upload_expires_at !== null
            && $existing->upload_expires_at->isFuture()) {
            $uploadUrl = route('albums.contribute.upload.form', ['upload_token' => $existing->upload_token]);
            Mail::to($email)->send(new AlbumContributionUploadReminderMail($album, $uploadUrl));

            return;
        }

        $verifyTtlHours = max(1, (int) config('services.albums.contribution_verify_ttl_hours', 24));

        $verifyToken = Str::random(40);

        Contributor::query()->updateOrCreate(
            [
                'album_id' => $album->id,
                'email' => $email,
            ],
            [
                'email_verified' => false,
                'verify_token' => $verifyToken,
                'verify_expires_at' => now()->addHours($verifyTtlHours),
                'upload_token' => null,
                'upload_expires_at' => null,
            ],
        );

        $confirmUrl = route('albums.contribute.confirm', ['verify_token' => $verifyToken]);

        Mail::to($email)->send(new AlbumContributionVerifyMail($album, $confirmUrl));
    }

    /**
     * @return array{contributor: Contributor, uploadUrl: string}
     */
    public function confirmEmail(string $verifyToken): array
    {
        $contributor = Contributor::query()
            ->where('verify_token', $verifyToken)
            ->with('album')
            ->first();

        if ($contributor === null) {
            throw ValidationException::withMessages([
                'verify' => 'Este link de confirmação é inválido ou já foi utilizado.',
            ]);
        }

        $album = $contributor->album;
        if ($album === null || $album->is_locked) {
            throw ValidationException::withMessages([
                'verify' => 'Este álbum não está mais disponível para contribuições.',
            ]);
        }

        if ($contributor->verify_expires_at !== null && $contributor->verify_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'verify' => 'Este link de confirmação expirou. Solicite um novo e-mail na página do convite.',
            ]);
        }

        $uploadTtlHours = $album->contribution_upload_ttl_hours
            ?? (int) config('services.albums.contribution_upload_ttl_hours', 72);
        $uploadTtlHours = max(1, $uploadTtlHours);

        $uploadToken = Str::random(40);

        $contributor->forceFill([
            'email_verified' => true,
            'verify_token' => null,
            'verify_expires_at' => null,
            'upload_token' => $uploadToken,
            'upload_expires_at' => now()->addHours($uploadTtlHours),
        ])->save();

        $uploadUrl = route('albums.contribute.upload.form', ['upload_token' => $uploadToken]);

        return [
            'contributor' => $contributor->fresh() ?? $contributor,
            'uploadUrl' => $uploadUrl,
        ];
    }

    public function resolveVerifiedContributorForUpload(string $uploadToken): ?Contributor
    {
        $contributor = Contributor::query()
            ->where('upload_token', $uploadToken)
            ->where('email_verified', true)
            ->with('album')
            ->first();

        if ($contributor === null) {
            return null;
        }

        if ($contributor->upload_expires_at !== null && $contributor->upload_expires_at->isPast()) {
            return null;
        }

        $album = $contributor->album;
        if ($album === null || $album->is_locked) {
            return null;
        }

        if (! is_string($album->contribution_invite_token) || $album->contribution_invite_token === '') {
            return null;
        }

        return $contributor;
    }

    public function assertContributorMayUploadMore(Contributor $contributor): void
    {
        $max = max(1, (int) config('services.albums.contribution_max_media_per_contributor', 100));

        $count = AlbumMedia::query()
            ->where('contributor_id', $contributor->id)
            ->where('album_id', $contributor->album_id)
            ->count();

        if ($count >= $max) {
            throw ValidationException::withMessages([
                'files' => 'Você atingiu o limite de envios para este álbum ('.$max.' arquivos).',
            ]);
        }
    }
}
