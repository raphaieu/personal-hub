<?php

namespace App\Livewire\Albums;

use App\Models\Album;
use App\Models\AlbumMedia;
use App\Services\Albums\AlbumMediaService;
use App\Services\Albums\AlbumMediaUploadService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

final class AlbumDetailPage extends Component
{
    use WithFileUploads;

    public Album $album;

    /** @var array<int, mixed> */
    public array $uploadFiles = [];

    public function mount(Album $album): void
    {
        $this->album = $album->load('parent');
    }

    public function uploadMedia(AlbumMediaUploadService $uploadService): void
    {
        $maxKb = max(1, (int) ceil(config('services.albums.max_upload_bytes') / 1024));

        $this->validate([
            'uploadFiles' => ['required', 'array', 'min:1', 'max:50'],
            'uploadFiles.*' => ['file', 'max:'.$maxKb],
        ], [], [
            'uploadFiles' => 'arquivos',
        ]);

        foreach ($this->uploadFiles as $file) {
            try {
                $uploadService->store($this->album, $file);
            } catch (ValidationException $e) {
                $msg = $e->validator->errors()->first('uploadFiles');
                if ($msg === '') {
                    $msg = $e->validator->errors()->first() ?: 'Arquivo inválido.';
                }

                throw ValidationException::withMessages([
                    'uploadFiles' => $msg,
                ]);
            }
        }

        $this->reset('uploadFiles');
        $this->album = $this->album->fresh()->load('parent');
        session()->flash('album_detail_notice', 'Upload concluído.');
    }

    public function reprocessMedia(string $mediaId, AlbumMediaService $mediaService): void
    {
        $media = $this->resolveOwnedMedia($mediaId);
        if ($media === null) {
            return;
        }

        if ($media->type !== 'photo') {
            session()->flash('album_detail_notice', 'Só é possível reprocessar fotos.');

            return;
        }

        $mediaService->reprocess($media);
        session()->flash('album_detail_notice', 'Mídia reenfileirada para reprocessamento.');
    }

    public function deleteMedia(string $mediaId, AlbumMediaService $mediaService): void
    {
        $media = $this->resolveOwnedMedia($mediaId);
        if ($media === null) {
            return;
        }

        $mediaService->delete($media);
        $this->album = $this->album->fresh()->load('parent');
        session()->flash('album_detail_notice', 'Mídia removida do bucket e do banco.');
    }

    private function resolveOwnedMedia(string $mediaId): ?AlbumMedia
    {
        return AlbumMedia::query()
            ->where('id', $mediaId)
            ->where('album_id', $this->album->id)
            ->first();
    }

    public function render()
    {
        $mediaItems = $this->album->media()
            ->orderBy('sort_position')
            ->orderBy('created_at')
            ->get();

        return view('livewire.albums.album-detail-page', [
            'mediaItems' => $mediaItems,
        ])->layout('layouts.app');
    }
}
