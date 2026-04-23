<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Hub Threads
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($tabLabels as $key => $label)
                        <button
                            type="button"
                            wire:click="setTab('{{ $key }}')"
                            @class([
                                'px-3 py-2 rounded-md text-sm font-medium transition',
                                'bg-indigo-600 text-white' => $currentTab === $key,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200' => $currentTab !== $key,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                @if ($currentTab === 'sources')
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Sources</h3>
                        <span class="text-sm text-gray-500">{{ $sources->count() }} cadastradas</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Tipo</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Label</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Alvo</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Status</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Ultimo scrape</th>
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
                                            {{ optional($source->last_scraped_at)?->format('d/m/Y H:i') ?? '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">
                                            Nenhuma source cadastrada ainda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($currentTab === 'review')
                    <div class="text-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Review</h3>
                        <p>Estrutura inicial pronta. A curadoria de comentarios entra no proximo bloco.</p>
                    </div>
                @else
                    <div class="text-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Published</h3>
                        <p>Estrutura inicial pronta. A visao de publicados entra no proximo bloco.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
