<?php

namespace App\Livewire\Albums;

use App\Models\Album;
use App\Models\AlbumLockout;
use App\Services\Albums\AlbumService;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Illuminate\Support\Str;

final class HubPage extends Component
{
    public ?string $editingId = null;

    public string $formParentId = '';

    public string $formSlug = '';

    public string $formTitle = '';

    public string $formDescription = '';

    public string $formAccessType = 'public';

    public string $formPassword = '';

    public string $formToken = '';

    public ?string $formTokenExpiresAt = null;

    public bool $formDownloadEnabled = false;

    public string $formSortOrder = 'date';

    public bool $formIsLocked = false;

    public int $formThumbWidth = 400;

    public ?int $formThumbHeight = null;

    public int $formThumbQuality = 80;

    public function startCreate(): void
    {
        $this->editingId = null;
        $this->resetFormDefaults();
        $this->resetValidation();
    }

    public function startEdit(string $albumId): void
    {
        $album = Album::query()->findOrFail($albumId);
        $this->editingId = $album->id;
        $this->formParentId = (string) ($album->parent_id ?? '');
        $this->formSlug = (string) $album->slug;
        $this->formTitle = (string) $album->title;
        $this->formDescription = (string) ($album->description ?? '');
        $this->formAccessType = (string) $album->access_type;
        $this->formPassword = '';
        $this->formToken = (string) ($album->token ?? '');
        $this->formTokenExpiresAt = $album->token_expires_at?->format('Y-m-d\TH:i');
        $this->formDownloadEnabled = (bool) $album->download_enabled;
        $this->formSortOrder = (string) $album->sort_order;
        $this->formIsLocked = (bool) $album->is_locked;
        $this->formThumbWidth = (int) $album->thumb_width;
        $this->formThumbHeight = $album->thumb_height !== null ? (int) $album->thumb_height : null;
        $this->formThumbQuality = (int) $album->thumb_quality;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->startCreate();
    }

    public function saveAlbum(AlbumService $service): void
    {
        $this->validate($this->rules());

        $payload = [
            'parent_id' => $this->formParentId !== '' ? $this->formParentId : null,
            'slug' => $this->formSlug,
            'title' => $this->formTitle,
            'description' => $this->formDescription !== '' ? $this->formDescription : null,
            'access_type' => $this->formAccessType,
            'download_enabled' => $this->formDownloadEnabled,
            'sort_order' => $this->formSortOrder,
            'is_locked' => $this->formIsLocked,
            'thumb_width' => $this->formThumbWidth,
            'thumb_height' => $this->formThumbHeight,
            'thumb_quality' => $this->formThumbQuality,
        ];

        if ($this->formAccessType === 'password' && $this->formPassword !== '') {
            $payload['password_hash'] = bcrypt($this->formPassword);
        }

        if (in_array($this->formAccessType, ['token', 'one_time'], true)) {
            $payload['token'] = $this->formToken !== '' ? $this->formToken : Str::random(40);
            $payload['token_expires_at'] = $this->formTokenExpiresAt !== null && $this->formTokenExpiresAt !== ''
                ? Carbon::parse($this->formTokenExpiresAt)
                : null;
            if ($this->formAccessType === 'token') {
                $payload['one_time_used_at'] = null;
            }
        } else {
            $payload['token'] = null;
            $payload['token_expires_at'] = null;
            $payload['one_time_used_at'] = null;
        }

        try {
            if ($this->editingId !== null) {
                $album = Album::query()->findOrFail($this->editingId);
                $service->update($album, $payload);
                session()->flash('albums_hub_notice', 'Álbum atualizado.');
            } else {
                $service->create($payload);
                session()->flash('albums_hub_notice', 'Álbum criado.');
            }
        } catch (InvalidArgumentException $e) {
            $this->addError('formParentId', $e->getMessage());

            return;
        }

        $this->startCreate();
    }

    public function deleteAlbum(string $albumId): void
    {
        $album = Album::query()->findOrFail($albumId);
        $album->delete();

        session()->flash('albums_hub_notice', 'Álbum removido.');
        if ($this->editingId === $albumId) {
            $this->startCreate();
        }
    }

    public function toggleLocked(string $albumId): void
    {
        $album = Album::query()->findOrFail($albumId);
        $album->forceFill(['is_locked' => ! $album->is_locked])->save();

        session()->flash('albums_hub_notice', 'Status de bloqueio atualizado.');
    }

    public function generateToken(): void
    {
        $this->formToken = Str::random(40);
    }

    public function unlockLockout(int $lockoutId): void
    {
        $lockout = AlbumLockout::query()->whereKey($lockoutId)->whereNull('unlocked_at')->firstOrFail();
        $lockout->forceFill([
            'unlocked_at' => now(),
            'unlocked_by' => 'admin',
        ])->save();

        session()->flash('albums_hub_notice', 'Lockout desbloqueado.');
    }

    private function resetFormDefaults(): void
    {
        $this->formParentId = '';
        $this->formSlug = '';
        $this->formTitle = '';
        $this->formDescription = '';
        $this->formAccessType = 'public';
        $this->formPassword = '';
        $this->formToken = '';
        $this->formTokenExpiresAt = null;
        $this->formDownloadEnabled = false;
        $this->formSortOrder = 'date';
        $this->formIsLocked = false;
        $this->formThumbWidth = 400;
        $this->formThumbHeight = null;
        $this->formThumbQuality = 80;
    }

    /**
     * @return array<string, list<\Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    private function rules(): array
    {
        return [
            'formParentId' => ['nullable', 'uuid', 'exists:albums,id'],
            'formSlug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('albums', 'slug')->ignore($this->editingId),
            ],
            'formTitle' => ['required', 'string', 'max:255'],
            'formDescription' => ['nullable', 'string'],
            'formAccessType' => ['required', Rule::in(['public', 'password', 'token', 'one_time'])],
            'formPassword' => ['nullable', 'string', 'min:4', 'max:255'],
            'formToken' => ['nullable', 'string', 'min:8', 'max:64'],
            'formTokenExpiresAt' => ['nullable', 'date'],
            'formDownloadEnabled' => ['boolean'],
            'formSortOrder' => ['required', Rule::in(['date', 'manual'])],
            'formIsLocked' => ['boolean'],
            'formThumbWidth' => ['required', 'integer', 'min:80', 'max:2400'],
            'formThumbHeight' => ['nullable', 'integer', 'min:80', 'max:2400'],
            'formThumbQuality' => ['required', 'integer', 'min:20', 'max:100'],
        ];
    }

    public function render()
    {
        $albums = Album::query()
            ->with(['parent'])
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        $rootAlbums = Album::query()
            ->whereNull('parent_id')
            ->when($this->editingId !== null, fn ($query) => $query->whereKeyNot($this->editingId))
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        $activeLockouts = AlbumLockout::query()
            ->with('album:id,title,slug')
            ->whereNull('unlocked_at')
            ->orderByDesc('locked_at')
            ->limit(50)
            ->get();

        return view('livewire.albums.hub-page', [
            'albums' => $albums,
            'rootAlbums' => $rootAlbums,
            'activeLockouts' => $activeLockouts,
        ])->layout('layouts.app');
    }
}
