<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Hub Albums
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                @if (session('albums_hub_notice'))
                    <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-800">
                        {{ session('albums_hub_notice') }}
                    </div>
                @endif

                <p class="text-sm text-gray-600 mb-4">
                    Gestão de álbuns: CRUD com hierarquia de até 2 níveis. Use <strong>Upload / lista</strong> para enviar mídias (S3/MinIO) e ver o status de processamento.
                </p>

                <div class="border-b border-gray-200 pb-4 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        {{ $editingId ? 'Editar álbum' : 'Novo álbum' }}
                    </h3>

                    <form wire:submit="saveAlbum" class="grid grid-cols-1 gap-3 md:grid-cols-6">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Título</label>
                            <input wire:model="formTitle" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ex.: Viagem Europa">
                            @error('formTitle') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Slug</label>
                            <input wire:model="formSlug" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="viagem-europa">
                            @error('formSlug') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tipo de acesso</label>
                            <select wire:model="formAccessType" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="public">public</option>
                                <option value="password">password</option>
                                <option value="token">token</option>
                                <option value="one_time">one_time</option>
                            </select>
                            @error('formAccessType') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Álbum pai</label>
                            <select wire:model="formParentId" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">(raiz)</option>
                                @foreach ($rootAlbums as $root)
                                    <option value="{{ $root->id }}">{{ $root->title }}</option>
                                @endforeach
                            </select>
                            @error('formParentId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Descrição (opcional)</label>
                            <textarea wire:model="formDescription" rows="2" class="w-full rounded-md border-gray-300 text-sm" placeholder="Contexto do álbum"></textarea>
                            @error('formDescription') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Senha (quando password)</label>
                            <input wire:model="formPassword" type="password" class="w-full rounded-md border-gray-300 text-sm" placeholder="••••">
                            @error('formPassword') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Token (token/one_time)</label>
                            <div class="flex items-center gap-2">
                                <input wire:model="formToken" type="text" class="w-full rounded-md border-gray-300 text-sm font-mono" placeholder="gerado automaticamente se vazio">
                                <button type="button" wire:click="generateToken" class="inline-flex items-center rounded-md bg-gray-100 px-2 py-2 text-xs font-medium text-gray-800 hover:bg-gray-200">
                                    Gerar
                                </button>
                            </div>
                            @error('formToken') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Expira em</label>
                            <input wire:model="formTokenExpiresAt" type="datetime-local" class="w-full rounded-md border-gray-300 text-sm">
                            @error('formTokenExpiresAt') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Ordenação</label>
                            <select wire:model="formSortOrder" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="date">date</option>
                                <option value="manual">manual</option>
                            </select>
                            @error('formSortOrder') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Thumb width</label>
                            <input wire:model.number="formThumbWidth" type="number" min="80" max="2400" class="w-full rounded-md border-gray-300 text-sm">
                            @error('formThumbWidth') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Thumb height</label>
                            <input wire:model.number="formThumbHeight" type="number" min="80" max="2400" class="w-full rounded-md border-gray-300 text-sm" placeholder="auto">
                            @error('formThumbHeight') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Thumb quality</label>
                            <input wire:model.number="formThumbQuality" type="number" min="20" max="100" class="w-full rounded-md border-gray-300 text-sm">
                            @error('formThumbQuality') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-end gap-3 md:col-span-2">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input wire:model="formDownloadEnabled" type="checkbox" class="rounded border-gray-300 text-indigo-600">
                                Download habilitado
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input wire:model="formIsLocked" type="checkbox" class="rounded border-gray-300 text-indigo-600">
                                Bloqueado
                            </label>
                        </div>

                        <div class="flex flex-wrap items-end gap-2 md:col-span-6">
                            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                {{ $editingId ? 'Salvar alterações' : 'Criar álbum' }}
                            </button>

                            @if ($editingId)
                                <button type="button" wire:click="cancelEdit" class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-200">
                                    Cancelar edição
                                </button>
                            @endif
                        </div>
                    </form>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Álbuns</h3>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pai</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acesso</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Token expira</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Download</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lock</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mídias</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($albums as $row)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-900">
                                            @if ($row->parent_id)
                                                <span class="text-gray-400">↳</span>
                                            @endif
                                            {{ $row->title }}
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-800">{{ $row->slug }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row->parent?->title ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row->access_type }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-700">
                                            {{ $row->token_expires_at ? $row->token_expires_at->format('d/m/Y H:i') : '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row->download_enabled ? 'Sim' : 'Não' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row->is_locked ? 'Sim' : 'Não' }}</td>
                                        <td class="px-3 py-2">
                                            <a
                                                href="{{ route('albums.hub.show', $row) }}"
                                                wire:navigate
                                                class="text-indigo-600 hover:text-indigo-900 text-xs font-medium"
                                            >
                                                Upload / lista
                                            </a>
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap space-x-2">
                                            <button type="button" wire:click="startEdit('{{ $row->id }}')" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">
                                                Editar
                                            </button>
                                            <button type="button" wire:click="toggleLocked('{{ $row->id }}')" class="text-gray-700 hover:text-gray-900 text-xs font-medium">
                                                Lock/unlock
                                            </button>
                                            <button type="button" wire:click="deleteAlbum('{{ $row->id }}')" wire:confirm="Confirma remover este álbum?" class="text-red-600 hover:text-red-700 text-xs font-medium">
                                                Remover
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-3 py-6 text-center text-sm text-gray-500">Nenhum álbum cadastrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Lockouts ativos</h3>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Álbum</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Locked at</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($activeLockouts as $lockout)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-900">
                                            {{ $lockout->album?->title ?? 'Álbum removido' }}
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-800">{{ $lockout->ip }}</td>
                                        <td class="px-3 py-2 text-gray-700">
                                            {{ $lockout->locked_at ? $lockout->locked_at->format('d/m/Y H:i') : '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" wire:click="unlockLockout({{ (int) $lockout->id }})" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">
                                                Desbloquear
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">Sem lockouts ativos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
