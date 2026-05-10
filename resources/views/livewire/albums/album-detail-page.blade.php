<div id="album-detail-scope" data-livewire-component-id="{{ $this->getId() }}">
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Mídias — {{ $album->title }}
            </h2>
            <a href="{{ route('albums.hub') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                ← Voltar ao hub de álbuns
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                @if (session('album_detail_notice'))
                    <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-800">
                        {{ session('album_detail_notice') }}
                    </div>
                @endif

                <div class="mb-6 rounded-xl border-2 border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-teal-50/90 p-4 sm:p-5 shadow-sm ring-1 ring-emerald-100">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Galeria pública</p>
                            <p class="mt-1 text-sm text-gray-700">
                                Endereço para <strong>só visualizar</strong> as mídias (visitantes não enviam arquivos por aqui).
                            </p>
                            <p class="mt-3 break-all rounded-lg bg-white/90 px-3 py-2 font-mono text-sm text-emerald-950 shadow-inner ring-1 ring-emerald-100/80">
                                {{ url(route('albums.viewer', ['slug' => $album->slug], false)) }}
                            </p>
                            <p class="mt-2 text-xs text-gray-500">Rota relativa: <span class="font-mono text-gray-700">/albums/{{ $album->slug }}</span></p>
                        </div>
                        <a
                            href="{{ route('albums.viewer', ['slug' => $album->slug]) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex shrink-0 items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            Abrir galeria
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8 border-b border-gray-200 pb-8 lg:grid-cols-2 lg:gap-10">
                    <div class="space-y-3 min-w-0">
                        <h3 class="text-base font-semibold text-gray-900">Envio de arquivos (hub)</h3>
                        <p class="text-sm text-gray-600">
                            Envie fotos ou vídeos; os arquivos vão para o bucket em segundo plano e as fotos ganham miniaturas processadas na fila.
                            Tamanho máximo por arquivo: {{ round(config('services.albums.max_upload_bytes') / (1024 * 1024), 0) }} MB.
                            Até <strong>{{ $effectiveMaxUploadFiles }}</strong> arquivo(s) por envio HTTP.
                            @if ($effectiveMaxUploadFiles < $configuredMaxFilesPerBatch)
                                <span class="block mt-2 text-amber-800">
                                    O PHP está limitando este envio a {{ $phpMaxFileUploads }} arquivo(s) por requisição (<code class="text-[11px] bg-amber-100 px-1 rounded">max_file_uploads</code>).
                                    Para mais arquivos, use vários lotes ou aumente <code class="text-[11px] bg-amber-100 px-1 rounded">max_file_uploads</code> no php.ini / pool do PHP-FPM.
                                </span>
                            @endif
                        </p>

                        <form wire:submit="uploadMedia" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Arquivos</label>
                                <input
                                    type="file"
                                    wire:model="uploadFiles"
                                    multiple
                                    class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                                >
                                <div wire:loading wire:target="uploadFiles" class="mt-1 text-xs text-gray-500">Carregando…</div>
                                @error('uploadFiles') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="uploadMedia">Enviar</span>
                                <span wire:loading wire:target="uploadMedia">Enviando…</span>
                            </button>
                        </form>
                    </div>

                    <div class="space-y-3 min-w-0 lg:border-l lg:border-gray-200 lg:pl-10">
                        <h3 class="text-base font-semibold text-gray-900">Contribuição externa</h3>
                        <p class="text-xs text-gray-600">
                            Gere um link para convidados enviarem mídias após confirmarem o e-mail. Você pode revogar uploads ou girar o token do convite a qualquer momento.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            @if (! $album->contribution_invite_token)
                                <button
                                    type="button"
                                    wire:click="generateContributionInvite"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    Gerar link de contribuição
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:click="rotateContributionInvite"
                                    wire:loading.attr="disabled"
                                    wire:confirm="Gerar novo token? O link antigo deixará de funcionar."
                                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    Novo token de convite
                                </button>
                                <button
                                    type="button"
                                    wire:click="revokeContributorUploads"
                                    wire:loading.attr="disabled"
                                    wire:confirm="Revogar o link de convite público e todos os tokens de upload dos contribuidores deste álbum?"
                                    class="inline-flex items-center rounded-md border border-red-200 bg-white px-3 py-2 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
                                >
                                    Revogar uploads
                                </button>
                            @endif
                        </div>
                        @if ($album->contribution_invite_token)
                            <div class="rounded-md bg-gray-50 border border-gray-200 p-3 text-xs break-all font-mono text-gray-800">
                                {{ route('albums.contribute.invite', ['album' => $album->id, 'token' => $album->contribution_invite_token]) }}
                            </div>
                            <div class="flex flex-wrap items-end gap-2">
                                <div>
                                    <label for="contrib-ttl" class="block text-xs font-medium text-gray-600 mb-1">Prazo do link de upload após confirmação (horas)</label>
                                    <input
                                        id="contrib-ttl"
                                        type="number"
                                        wire:model="contributionUploadTtlHours"
                                        min="1"
                                        max="8760"
                                        placeholder="{{ (int) config('services.albums.contribution_upload_ttl_hours', 72) }} (padrão)"
                                        class="w-40 rounded-md border-gray-300 text-sm shadow-sm"
                                    >
                                    @error('contributionUploadTtlHours')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button
                                    type="button"
                                    wire:click="saveContributionUploadTtl"
                                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    Salvar prazo
                                </button>
                            </div>
                            <p class="text-xs text-gray-500">Deixe em branco para usar o padrão global ({{ (int) config('services.albums.contribution_upload_ttl_hours', 72) }} h).</p>
                        @endif
                    </div>
                </div>

                
                <p class="text-xs mt-6 text-gray-500 mb-2">
                    Reordene arrastando pela coluna à esquerda (ícone de arrastar) ou informando o número da posição na coluna «Nº» (1 = primeiro item).
                </p>

                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h3 class="text-lg font-semibold text-gray-900">Itens neste álbum</h3>
                    <div class="flex flex-wrap items-center gap-3">
                        @if (count($selectedMediaIds) > 0)
                            <button
                                type="button"
                                wire:click="deleteSelectedMedia"
                                wire:confirm="Excluir {{ count($selectedMediaIds) }} mídia(s) selecionada(s) do banco e do bucket? Não é possível desfazer."
                                wire:loading.attr="disabled"
                                wire:target="deleteSelectedMedia"
                                class="inline-flex items-center rounded-md border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
                            >
                                Apagar selecionadas ({{ count($selectedMediaIds) }})
                            </button>
                        @endif
                        <button
                            type="button"
                            wire:click="selectAllAlbumMedia"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50"
                            @disabled($mediaItems->isEmpty())
                        >
                            Marcar todas
                        </button>
                        <button
                            type="button"
                            wire:click="clearMediaSelection"
                            class="text-xs font-medium text-gray-600 hover:text-gray-900"
                            @disabled(count($selectedMediaIds) === 0)
                        >
                            Limpar seleção
                        </button>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="showThumbnails" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span>Mostrar miniaturas</span>
                        </label>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-2 w-10 text-center" scope="col">
                                    <span class="sr-only">Selecionar</span>
                                </th>
                                <th class="px-2 py-2 w-10" aria-label="Reordenar"></th>
                                @if ($showThumbnails)
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Preview</th>
                                @endif
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome de exibição</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Arquivo original</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tamanho</th>
                                <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Nº</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="album-media-sortable-tbody" class="bg-white divide-y divide-gray-200">
                            @forelse ($mediaItems as $m)
                                <tr wire:key="media-{{ $m->id }}" data-media-id="{{ $m->id }}">
                                    <td class="px-2 py-2 align-middle text-center">
                                        <input
                                            type="checkbox"
                                            wire:model.live="selectedMediaIds"
                                            value="{{ $m->id }}"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                            aria-label="Selecionar mídia {{ $m->filename_original }}"
                                        >
                                    </td>
                                    <td class="px-2 py-2 align-middle text-gray-400 album-drag-handle cursor-grab active:cursor-grabbing select-none" title="Arrastar para reordenar">
                                        <svg class="w-5 h-5 mx-auto" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M7 4h2v2H7V4zm6 0h2v2h-2V4zM7 9h2v2H7V9zm6 0h2v2h-2V9zM7 14h2v2H7v-2zm6 0h2v2h-2v-2z"/>
                                        </svg>
                                    </td>
                                    @if ($showThumbnails)
                                        <td class="px-3 py-2 align-middle">
                                            @if ($m->type === 'photo')
                                                <img
                                                    src="{{ route('albums.hub.media.preview', ['album' => $album, 'media' => $m]) }}?variant=thumb"
                                                    alt=""
                                                    class="h-16 w-16 rounded object-cover bg-gray-100 border border-gray-200"
                                                    loading="lazy"
                                                >
                                            @elseif ($m->type === 'video')
                                                <div class="h-16 w-16 rounded bg-gray-900 flex items-center justify-center text-[10px] text-white text-center px-1">
                                                    vídeo
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-3 py-2 align-top">
                                        <div class="flex flex-col gap-1 max-w-xs">
                                            <input
                                                type="text"
                                                wire:model="nameEdits.{{ $m->id }}"
                                                wire:blur="saveDisplayName('{{ $m->id }}')"
                                                placeholder="Igual ao arquivo"
                                                class="w-full rounded-md border-gray-300 text-xs shadow-sm"
                                            >
                                            @error('nameEdits.'.$m->id)
                                                <span class="text-[11px] text-red-600">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600 font-mono text-[11px] break-all align-top">
                                        {{ $m->filename_original }}
                                        @if ($m->processing_status === 'failed' && ! empty(($m->metadata['processing_error'] ?? null)))
                                            <div class="mt-1 text-[11px] text-red-600">
                                                erro: {{ $m->metadata['processing_error'] }}
                                                @if (! empty($m->metadata['processing_error_detail'] ?? null))
                                                    — {{ \Illuminate\Support\Str::limit($m->metadata['processing_error_detail'], 120) }}
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $m->type }}</td>
                                    <td class="px-3 py-2">
                                        <span @class([
                                            'inline-flex items-center rounded px-2 py-0.5 text-xs font-medium',
                                            'bg-green-50 text-green-700' => $m->processing_status === 'done',
                                            'bg-amber-50 text-amber-700' => in_array($m->processing_status, ['pending', 'processing'], true),
                                            'bg-red-50 text-red-700' => $m->processing_status === 'failed',
                                        ])>
                                            {{ $m->processing_status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700">{{ number_format($m->size_bytes / 1024, 1) }} KB</td>
                                    <td class="px-2 py-2 text-center align-middle">
                                        <label class="sr-only" for="pos-{{ $m->id }}">Posição na lista</label>
                                        <input
                                            id="pos-{{ $m->id }}"
                                            type="number"
                                            min="1"
                                            max="{{ max(1, $mediaItems->count()) }}"
                                            value="{{ $loop->iteration }}"
                                            wire:change="setMediaPosition('{{ $m->id }}', $event.target.value)"
                                            class="w-14 rounded-md border-gray-300 text-xs text-center py-1 shadow-sm"
                                            title="Posição (1 = primeiro)"
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap space-x-3">
                                        @if ($m->type === 'photo' && in_array($m->processing_status, ['failed', 'pending'], true))
                                            <button
                                                type="button"
                                                wire:click="reprocessMedia('{{ $m->id }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="reprocessMedia('{{ $m->id }}')"
                                                class="text-indigo-600 hover:text-indigo-900 text-xs font-medium disabled:opacity-50"
                                            >
                                                Reprocessar
                                            </button>
                                        @endif
                                        <button
                                            type="button"
                                            wire:click="deleteMedia('{{ $m->id }}')"
                                            wire:confirm="Excluir essa mídia do banco e do bucket S3? Não é possível desfazer."
                                            wire:loading.attr="disabled"
                                            wire:target="deleteMedia('{{ $m->id }}')"
                                            class="text-red-600 hover:text-red-700 text-xs font-medium disabled:opacity-50"
                                        >
                                            Excluir
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $showThumbnails ? 10 : 9 }}" class="px-3 py-6 text-center text-sm text-gray-500">Nenhuma mídia ainda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 pt-6 border-t border-red-100 space-y-3">
                    <h3 class="text-sm font-semibold text-red-900">Apagar álbum</h3>
                    <p class="text-xs text-gray-600">
                        Remove todas as mídias deste álbum do armazenamento e do banco, e exclui o registro do álbum (subálbuns impedem esta ação).
                    </p>
                    <button
                        type="button"
                        wire:click="deleteAlbum"
                        wire:confirm="Apagar DEFINITIVAMENTE este álbum e todas as mídias no armazenamento? Esta operação não pode ser desfeita."
                        wire:loading.attr="disabled"
                        wire:target="deleteAlbum"
                        class="inline-flex items-center rounded-md bg-red-600 px-3 py-2 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                    >
                        Apagar álbum inteiro
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
        <script>
            (function () {
                var albumSortTimer = null;

                function scheduleAlbumDetailSortable() {
                    clearTimeout(albumSortTimer);
                    albumSortTimer = setTimeout(initAlbumDetailSortable, 50);
                }

                function initAlbumDetailSortable() {
                    if (typeof Sortable === 'undefined') {
                        return;
                    }

                    var tbody = document.getElementById('album-media-sortable-tbody');
                    if (! tbody) {
                        return;
                    }

                    var scope = document.getElementById('album-detail-scope');
                    var wid = scope && scope.getAttribute('data-livewire-component-id');
                    var component = wid && window.Livewire ? window.Livewire.find(wid) : null;

                    if (! component) {
                        var wireRoot = tbody.closest('[wire\\:id]');
                        if (! wireRoot || ! window.Livewire) {
                            return;
                        }

                        wid = wireRoot.getAttribute('wire:id');
                        if (! wid) {
                            return;
                        }

                        component = window.Livewire.find(wid);
                    }
                    if (! component) {
                        return;
                    }

                    if (tbody._albumSortableInstance) {
                        tbody._albumSortableInstance.destroy();
                        tbody._albumSortableInstance = null;
                    }

                    tbody._albumSortableInstance = Sortable.create(tbody, {
                        handle: '.album-drag-handle',
                        draggable: 'tr[data-media-id]',
                        animation: 150,
                        onEnd: function () {
                            var ids = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-media-id]')).map(function (tr) {
                                return tr.getAttribute('data-media-id');
                            });
                            component.call('reorderMedia', ids);
                        },
                    });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    initAlbumDetailSortable();

                    var root = document.getElementById('album-detail-scope');
                    if (root && typeof MutationObserver !== 'undefined') {
                        new MutationObserver(scheduleAlbumDetailSortable).observe(root, { childList: true, subtree: true });
                    }
                });

                document.addEventListener('livewire:navigated', function () {
                    queueMicrotask(initAlbumDetailSortable);
                });
            })();
        </script>
    @endpush
</div>
