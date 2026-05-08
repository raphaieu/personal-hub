<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Profiles de Analise
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                @if (session('analysis_profiles_hub_notice'))
                    <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-700">
                        {{ session('analysis_profiles_hub_notice') }}
                    </div>
                @endif

                <p class="text-sm text-gray-600 mb-4">
                    Administre profiles de análise salvos em banco para Threads e WhatsApp. Campos JSON aceitam estrutura válida e o profile padrão
                    <code class="text-xs bg-gray-100 px-1 rounded">threads-opportunities</code>
                    possui proteção parcial para evitar quebra acidental do fallback.
                </p>

                <div class="border-b border-gray-200 pb-4 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        {{ $editingId ? 'Editar profile' : 'Novo profile' }}
                    </h3>

                    <form wire:submit="saveProfile" class="grid grid-cols-1 gap-3 md:grid-cols-12">
                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Slug</label>
                            <input wire:model="formSlug" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="whatsapp-triage-v1">
                            @error('formSlug') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nome</label>
                            <input wire:model="formName" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="WhatsApp Triage">
                            @error('formName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Canal</label>
                            <select wire:model="formChannel" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">Neutro</option>
                                <option value="threads">Threads</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                            @error('formChannel') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                            <input wire:model="formAnalysisType" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="classification">
                            @error('formAnalysisType') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Threshold</label>
                            <input wire:model="formScoreThreshold" type="number" min="0" max="100" step="0.01" class="w-full rounded-md border-gray-300 text-sm" placeholder="65">
                            @error('formScoreThreshold') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-12">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Descrição</label>
                            <textarea wire:model="formDescription" rows="2" class="w-full rounded-md border-gray-300 text-sm" placeholder="Contexto de uso do profile"></textarea>
                            @error('formDescription') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-12">
                            <label class="block text-xs font-medium text-gray-600 mb-1">System prompt</label>
                            <textarea wire:model="formSystemPrompt" rows="6" class="w-full rounded-md border-gray-300 text-sm" placeholder="Instruções para o modelo"></textarea>
                            @error('formSystemPrompt') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Output schema (JSON)</label>
                            <textarea wire:model="formOutputSchema" rows="8" class="w-full rounded-md border-gray-300 font-mono text-xs" placeholder="{ ... }"></textarea>
                            @error('formOutputSchema') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Allowed categories (JSON)</label>
                            <textarea wire:model="formAllowedCategories" rows="8" class="w-full rounded-md border-gray-300 font-mono text-xs" placeholder="[ ... ]"></textarea>
                            @error('formAllowedCategories') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Settings (JSON)</label>
                            <textarea wire:model="formSettings" rows="8" class="w-full rounded-md border-gray-300 font-mono text-xs" placeholder="{ ... }"></textarea>
                            @error('formSettings') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-12 flex flex-wrap items-end gap-3">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input wire:model="formIsActive" type="checkbox" class="rounded border-gray-300 text-indigo-600">
                                Ativo
                            </label>
                            @error('formIsActive') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                {{ $editingId ? 'Salvar alterações' : 'Criar profile' }}
                            </button>

                            @if ($editingId)
                                <button type="button" wire:click="cancelEdit" class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                                    Cancelar edição
                                </button>
                            @else
                                <button type="button" wire:click="startCreate" class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                                    Limpar formulário
                                </button>
                            @endif
                        </div>
                    </form>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-semibold text-gray-900">Profiles cadastrados</h3>
                        <span class="text-sm text-gray-500">{{ $profiles->count() }} profile(s)</span>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Canal</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uso</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($profiles as $profile)
                                    <tr @class(['bg-indigo-50/40' => (int) $editingId === (int) $profile->id])>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-900">
                                            {{ $profile->slug }}
                                            @if ($profile->isDefaultThreadsProfile())
                                                <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700">
                                                    padrão threads
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-gray-800">{{ $profile->name }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $profile->channel ?? 'neutro' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $profile->analysis_type }}</td>
                                        <td class="px-3 py-2">
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                                'bg-emerald-100 text-emerald-700' => $profile->is_active,
                                                'bg-gray-100 text-gray-600' => ! $profile->is_active,
                                            ])>
                                                {{ $profile->is_active ? 'Ativo' : 'Inativo' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-700">
                                            threads_sources {{ $profile->threads_sources_count }}
                                            · monitored_sources {{ $profile->monitored_sources_count }}
                                            · categories {{ $profile->threads_categories_count }}
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap space-x-2">
                                            <button type="button" wire:click="startEdit({{ (int) $profile->id }})" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">
                                                Editar
                                            </button>
                                            <button type="button" wire:click="toggleActive({{ (int) $profile->id }})" class="text-gray-700 hover:text-gray-900 text-xs font-medium">
                                                Ativar/desativar
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500">
                                            Nenhum profile cadastrado.
                                        </td>
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
