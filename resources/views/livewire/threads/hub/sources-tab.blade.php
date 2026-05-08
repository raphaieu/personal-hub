<div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-semibold text-gray-900">Sources</h3>
    <div class="flex items-center gap-3">
        <span class="text-sm text-gray-500">{{ $sources->count() }} cadastradas</span>
        <div class="flex items-center gap-2">
            <input
                type="number"
                min="1"
                max="200"
                wire:model.live="manualDispatchBatchSize"
                class="w-20 rounded-md border-gray-300 text-sm"
            >
            <button
                type="button"
                wire:click="dispatchPendingClassification"
                class="inline-flex items-center rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
            >
                Classificar pendentes
            </button>
        </div>
    </div>
</div>

<p class="mb-4 text-xs text-gray-600 leading-relaxed">
    <span class="font-medium text-gray-700">IA:</span>
    {{ $pendingClassificationCount }} comentario(s) sem resumo IA na base.
    Proximo disparo enfileira ate {{ $nextClassificationBatchEstimate }} job(s)
    (batch configurado: {{ $manualDispatchBatchSize }}, espaco ~{{ $aiDispatchSpacingSeconds }}s entre jobs na fila <code class="text-[11px] bg-gray-100 px-1 rounded">ai</code>).
</p>

<form wire:submit="createSource" class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-8">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
        <select wire:model.live="newSourceType" class="w-full rounded-md border-gray-300 text-sm">
            @foreach ($createTypes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        @error('newSourceType') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1">Label</label>
        <input wire:model="newSourceLabel" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ex.: Freelas PHP">
        @error('newSourceLabel') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1">
            {{ $newSourceType === 'keyword' ? 'Keyword' : 'URL alvo' }}
        </label>
        @if ($newSourceType === 'keyword')
            <input wire:model="newSourceKeyword" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="freelance php remoto">
            @error('newSourceKeyword') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        @else
            <input wire:model="newSourceTargetUrl" type="url" class="w-full rounded-md border-gray-300 text-sm" placeholder="https://www.threads.com/@...">
            @error('newSourceTargetUrl') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        @endif
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1">Profile de analise</label>
        <select wire:model.live="newSourceProfileId" class="w-full rounded-md border-gray-300 text-sm">
            <option value="">Padrao (fallback)</option>
            @foreach ($threadsProfiles as $profile)
                <option value="{{ $profile->id }}">{{ $profile->name }} ({{ $profile->slug }})</option>
            @endforeach
        </select>
        @error('newSourceProfileId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-end gap-3">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input wire:model="newSourceIsActive" type="checkbox" class="rounded border-gray-300 text-indigo-600">
            Ativa
        </label>
        <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Criar
        </button>
    </div>
</form>

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Tipo</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Label</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Alvo</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Status</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Profile</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Estado principal</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Ultimo scrape</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Acoes</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($sources as $source)
                <tr>
                    <td class="px-3 py-2 text-gray-700">{{ $source->type }}</td>
                    <td class="px-3 py-2 text-gray-900">{{ $source->label }}</td>
                    <td class="px-3 py-2 text-gray-700">
                        {{ $source->keyword ?: $source->target_url ?: '-' }}
                    </td>
                    <td class="px-3 py-2">
                        <span @class([
                            'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                            'bg-emerald-100 text-emerald-700' => $source->is_active,
                            'bg-gray-100 text-gray-600' => ! $source->is_active,
                        ])>
                            {{ $source->is_active ? 'Ativa' : 'Inativa' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-gray-700">
                        <div class="flex items-center gap-2">
                            <select
                                wire:model.live="sourceProfileForms.{{ $source->id }}"
                                class="rounded-md border-gray-300 text-xs"
                            >
                                <option value="">Padrao (fallback)</option>
                                @foreach ($threadsProfiles as $profile)
                                    <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                                @endforeach
                            </select>
                            <button
                                type="button"
                                wire:click="saveSourceProfile({{ $source->id }})"
                                class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200"
                            >
                                Salvar
                            </button>
                        </div>
                        <div class="mt-1 text-[11px] text-gray-500">
                            {{ $source->analysisProfile?->slug ?? 'fallback-default' }}
                        </div>
                    </td>
                    <td class="px-3 py-2 text-xs">
                        @if ($source->analysis_profile_id !== null)
                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-1 font-medium text-indigo-700">
                                profile explicito
                            </span>
                        @elseif ($defaultThreadsProfileId !== null)
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 font-medium text-amber-700">
                                fallback para padrao
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-1 font-medium text-rose-700">
                                sem profile disponivel
                            </span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-gray-700">
                        {{ optional($source->last_scraped_at)?->format('d/m/Y H:i') ?? '-' }}
                    </td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="toggleSource({{ $source->id }})"
                                class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                            >
                                {{ $source->is_active ? 'Desativar' : 'Ativar' }}
                            </button>
                            <button
                                type="button"
                                wire:click="scrapeNow({{ $source->id }})"
                                class="inline-flex items-center rounded-md bg-indigo-100 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200"
                            >
                                Scrape agora
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-3 py-6 text-center text-gray-500">
                        Nenhuma source cadastrada ainda.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
