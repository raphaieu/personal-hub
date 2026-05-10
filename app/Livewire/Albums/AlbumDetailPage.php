<?php

namespace App\Livewire\Albums;

use App\Jobs\Albums\IngestAlbumUploadBatchJob;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Services\Albums\AlbumContributionService;
use App\Services\Albums\AlbumMediaService;
use App\Support\AlbumUploadLimits;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

final class AlbumDetailPage extends Component
{
    use WithFileUploads;

    public Album $album;

    /** @var array<int, mixed> */
    public array $uploadFiles = [];

    public ?int $contributionUploadTtlHours = null;

    /** Legenda exibida no viewer (opcional); chave = id da mídia. */
    public array $nameEdits = [];

    public bool $showThumbnails = true;

    /** @var list<string> */
    public array $selectedMediaIds = [];

    public function mount(Album $album): void
    {
        $this->album = $album->load('parent');
        $this->contributionUploadTtlHours = $album->contribution_upload_ttl_hours;
        $this->syncNameEditsFromDb();
    }

    private function syncNameEditsFromDb(): void
    {
        $this->nameEdits = [];
        $mediaRows = $this->album->media()
            ->orderBy('sort_position')
            ->orderBy('created_at')
            ->get();

        foreach ($mediaRows as $m) {
            $this->nameEdits[$m->id] = $m->display_name ?? '';
        }
    }

    public function saveContributionUploadTtl(): void
    {
        $this->validate([
            'contributionUploadTtlHours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ], [], [
            'contributionUploadTtlHours' => 'prazo de upload (horas)',
        ]);

        $this->album->forceFill([
            'contribution_upload_ttl_hours' => $this->contributionUploadTtlHours,
        ])->save();

        $this->album = $this->album->fresh()->load('parent');
        session()->flash('album_detail_notice', 'Prazo de upload para contribuidores atualizado.');
    }

    public function uploadMedia(): void
    {
        $maxKb = max(1, (int) ceil(config('services.albums.max_upload_bytes') / 1024));
        $maxFiles = AlbumUploadLimits::maxFilesPerHttpRequest();

        $this->validate([
            'uploadFiles' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'uploadFiles.*' => ['file', 'max:'.$maxKb],
        ], [], [
            'uploadFiles' => 'arquivos',
        ]);

        /** @var list<array{path: string, original: string}> $batch */
        $batch = [];
        foreach ($this->uploadFiles as $file) {
            $dir = 'album-ingest/'.$this->album->id.'/'.Str::uuid();
            $stored = $file->storeAs($dir, $file->getClientOriginalName(), 'local');
            $batch[] = ['path' => $stored, 'original' => $file->getClientOriginalName()];
        }

        $job = new IngestAlbumUploadBatchJob((string) $this->album->id, $batch);
        if (App::runningUnitTests()) {
            Bus::dispatchSync($job);
        } else {
            Bus::dispatchAfterResponse($job);
        }

        $this->reset('uploadFiles');
        $this->album = $this->album->fresh()->load('parent');
        session()->flash(
            'album_detail_notice',
            'Upload recebido. Os arquivos serão enviados ao armazenamento em segundo plano; atualize em alguns instantes para ver novos itens na lista.'
        );
    }

    public function saveDisplayName(string $mediaId): void
    {
        $media = $this->resolveOwnedMedia($mediaId);
        if ($media === null) {
            return;
        }

        $raw = isset($this->nameEdits[$mediaId]) ? trim((string) $this->nameEdits[$mediaId]) : '';

        $this->validate([
            "nameEdits.$mediaId" => ['nullable', 'string', 'max:255'],
        ], [], [
            "nameEdits.$mediaId" => 'nome de exibição',
        ]);

        $next = $raw === '' ? null : $raw;
        $prev = $media->display_name;
        if (($prev ?? '') === ($next ?? '')) {
            return;
        }

        $media->forceFill([
            'display_name' => $next,
        ])->save();

        $this->album = $this->album->fresh()->load('parent');
        session()->flash('album_detail_notice', 'Nome de exibição atualizado.');
    }

    /**
     * @param  list<string>  $orderedIds  IDs na nova ordem (topo → fundo).
     */
    public function reorderMedia(array $orderedIds): void
    {
        $orderedIds = array_values(array_filter($orderedIds, static fn ($id): bool => is_string($id) && $id !== ''));

        $existing = $this->album->media()->pluck('id')->map(static fn ($id): string => (string) $id)->sort()->values()->all();
        $incoming = collect($orderedIds)->map(static fn ($id): string => (string) $id)->sort()->values()->all();

        if ($existing !== $incoming) {
            return;
        }

        $this->persistSortOrder($orderedIds);
        session()->flash('album_detail_notice', 'Ordem das mídias atualizada.');
    }

    /**
     * Move uma mídia para a posição informada (1 = primeiro item).
     */
    public function setMediaPosition(string $mediaId, mixed $positionInput): void
    {
        $items = $this->album->media()
            ->orderBy('sort_position')
            ->orderBy('created_at')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $count = count($items);
        if ($count === 0) {
            return;
        }

        $pos = is_numeric($positionInput) ? (int) $positionInput : 1;
        if ($pos < 1) {
            $pos = 1;
        }
        if ($pos > $count) {
            $pos = $count;
        }

        $idx = array_search($mediaId, $items, true);
        if ($idx === false) {
            return;
        }

        if ($pos === (int) $idx + 1) {
            return;
        }

        $without = array_values(array_diff($items, [$mediaId]));
        array_splice($without, $pos - 1, 0, [$mediaId]);

        $this->persistSortOrder($without);
        session()->flash('album_detail_notice', 'Ordem das mídias atualizada.');
    }

    /**
     * @param  list<string>  $orderedIds
     */
    private function persistSortOrder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                AlbumMedia::query()
                    ->where('album_id', $this->album->id)
                    ->where('id', $id)
                    ->update(['sort_position' => $index + 1]);
            }
        });

        $this->album = $this->album->fresh()->load('parent');
        $this->syncNameEditsFromDb();
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
        unset($this->nameEdits[$mediaId]);
        $this->selectedMediaIds = array_values(array_diff($this->selectedMediaIds, [$mediaId]));
        $this->album = $this->album->fresh()->load('parent');
        session()->flash('album_detail_notice', 'Mídia removida do bucket e do banco.');
    }

    public function deleteSelectedMedia(AlbumMediaService $mediaService): void
    {
        $ids = array_values(array_unique(array_filter($this->selectedMediaIds, static fn ($id): bool => is_string($id) && $id !== '')));
        if ($ids === []) {
            return;
        }

        $ownedIds = $this->album->media()->whereIn('id', $ids)->pluck('id')->map(static fn ($id): string => (string) $id)->all();

        foreach ($ownedIds as $id) {
            $media = AlbumMedia::query()->find($id);
            if ($media !== null) {
                $mediaService->delete($media);
            }
            unset($this->nameEdits[$id]);
        }

        $this->selectedMediaIds = [];
        $this->album = $this->album->fresh()->load('parent');
        $this->syncNameEditsFromDb();
        session()->flash('album_detail_notice', count($ownedIds).' mídia(s) removida(s) do bucket e do banco.');
    }

    public function selectAllAlbumMedia(): void
    {
        $this->selectedMediaIds = $this->album->media()
            ->orderBy('sort_position')
            ->orderBy('created_at')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    public function clearMediaSelection(): void
    {
        $this->selectedMediaIds = [];
    }

    public function deleteAlbum(AlbumMediaService $mediaService): void
    {
        $album = $this->album->fresh();

        if ($album->children()->exists()) {
            session()->flash('album_detail_notice', 'Não é possível apagar este álbum enquanto existirem subálbuns. Remova ou reorganize os subálbuns antes.');

            return;
        }

        foreach ($album->media()->get() as $media) {
            $mediaService->delete($media);
        }

        $album->delete();

        session()->flash('albums_hub_notice', 'Álbum e mídias removidos.');

        $this->redirect(route('albums.hub'));
    }

    public function generateContributionInvite(AlbumContributionService $contributionService): void
    {
        $contributionService->ensureInviteToken($this->album);
        $this->album = $this->album->fresh()->load('parent');
        session()->flash('album_detail_notice', 'Link de contribuição gerado. Copie a URL abaixo e compartilhe apenas com quem deve enviar mídias.');
    }

    public function rotateContributionInvite(AlbumContributionService $contributionService): void
    {
        $contributionService->rotateInviteToken($this->album);
        $this->album = $this->album->fresh()->load('parent');
        session()->flash('album_detail_notice', 'Novo token de convite gerado. Links antigos deixam de funcionar.');
    }

    public function revokeContributorUploads(AlbumContributionService $contributionService): void
    {
        $contributionService->revokeAllUploadTokens($this->album);
        $this->album = $this->album->fresh()->load('parent');
        session()->flash('album_detail_notice', 'Convite público e tokens de upload dos contribuidores foram revogados.');
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

        foreach ($mediaItems as $m) {
            if (! array_key_exists($m->id, $this->nameEdits)) {
                $this->nameEdits[$m->id] = $m->display_name ?? '';
            }
        }

        return view('livewire.albums.album-detail-page', [
            'mediaItems' => $mediaItems,
            'effectiveMaxUploadFiles' => AlbumUploadLimits::maxFilesPerHttpRequest(),
            'phpMaxFileUploads' => AlbumUploadLimits::phpMaxFileUploads(),
            'configuredMaxFilesPerBatch' => max(1, (int) config('services.albums.max_files_per_batch')),
        ])->layout('layouts.app');
    }
}
