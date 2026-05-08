<div>
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

                <p class="text-sm text-gray-600 mb-4">
                    Envie fotos ou vídeos; os arquivos são gravados no bucket S3/MinIO e as fotos passam por processamento assíncrono (miniaturas).
                    Tamanho máximo por arquivo: {{ round(config('services.albums.max_upload_bytes') / (1024 * 1024), 0) }} MB.
                </p>

                <form wire:submit="uploadMedia" class="space-y-3 mb-8 pb-8 border-b border-gray-200">
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

                <h3 class="text-lg font-semibold text-gray-900 mb-3">Itens neste álbum</h3>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Arquivo</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tamanho</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($mediaItems as $m)
                                <tr>
                                    <td class="px-3 py-2 text-gray-900 font-mono text-xs break-all">
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
                                    <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">Nenhuma mídia ainda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-200 space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900">Contribuição externa</h3>
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
                                wire:confirm="Revogar todos os tokens de upload dos contribuidores deste álbum?"
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

                <p class="mt-6 text-xs text-gray-500">
                    Viewer público:
                    <a href="{{ route('albums.viewer', ['slug' => $album->slug]) }}" target="_blank" rel="noopener noreferrer" class="font-medium text-indigo-600 hover:text-indigo-800">
                        /albums/{{ $album->slug }}
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
