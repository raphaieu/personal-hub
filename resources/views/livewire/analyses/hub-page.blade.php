<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Análises
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Revisão de análises do WhatsApp</h3>
                        <p class="mt-1 max-w-3xl text-sm text-gray-600">
                            Compare mensagens com os resultados persistidos pela IA. “Classificado” indica somente que o processamento terminou; a categoria é exibida separadamente.
                        </p>
                    </div>
                    <a href="{{ route('monitored-sources.hub') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        Configurar fontes
                    </a>
                </div>
            </section>

            <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="analysis-profile-filter" class="mb-1 block text-xs font-medium text-gray-600">Perfil utilizado</label>
                        <select id="analysis-profile-filter" wire:model.live="profileFilter" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Todos</option>
                            @foreach ($profileOptions as $profile)
                                <option value="{{ $profile['slug'] }}">{{ $profile['name'] }} · {{ $profile['slug'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="analysis-source-filter" class="mb-1 block text-xs font-medium text-gray-600">Fonte monitorada</label>
                        <select id="analysis-source-filter" wire:model.live="sourceFilter" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Todas</option>
                            @foreach ($sourceOptions as $source)
                                <option value="{{ $source->id }}">{{ $source->label ?: $source->identifier }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="analysis-status-filter" class="mb-1 block text-xs font-medium text-gray-600">Processamento</label>
                        <select id="analysis-status-filter" wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Todos</option>
                            @foreach ($statusOptions as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="analysis-category-filter" class="mb-1 block text-xs font-medium text-gray-600">Categoria retornada</label>
                        <select id="analysis-category-filter" wire:model.live="categoryFilter" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Todas</option>
                            @foreach ($categoryOptions as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="analysis-date-from" class="mb-1 block text-xs font-medium text-gray-600">Período inicial</label>
                        <input id="analysis-date-from" wire:model.live="dateFrom" type="date" class="w-full rounded-md border-gray-300 text-sm">
                    </div>

                    <div>
                        <label for="analysis-date-to" class="mb-1 block text-xs font-medium text-gray-600">Período final</label>
                        <input id="analysis-date-to" wire:model.live="dateTo" type="date" class="w-full rounded-md border-gray-300 text-sm">
                    </div>

                    <div class="md:col-span-2">
                        <label for="analysis-search" class="mb-1 block text-xs font-medium text-gray-600">Buscar na mensagem ou análise</label>
                        <input
                            id="analysis-search"
                            wire:model.live.debounce.400ms="search"
                            type="search"
                            class="w-full rounded-md border-gray-300 text-sm"
                            placeholder="Produto, cupom, URL ou trecho do resultado"
                        >
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input wire:model.live="includeUnmonitored" type="checkbox" class="rounded border-gray-300 text-indigo-600">
                        Incluir tráfego sem fonte monitorada
                    </label>
                    <button type="button" wire:click="clearFilters" class="rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                        Limpar filtros
                    </button>
                </div>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-4 sm:px-6">
                    <h3 class="font-semibold text-gray-900">Mensagens</h3>
                    <span class="text-sm text-gray-500">{{ $messages->total() }} no filtro</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Data/hora</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Fonte</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Perfil utilizado</th>
                                <th class="min-w-64 px-3 py-2 text-left font-medium text-gray-600">Resumo</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Processamento</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Resultado</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Score</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Ofertas</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($messages as $message)
                                @php
                                    $processingLabel = match ($message->ai_pipeline_status) {
                                        'classified' => 'Classificado',
                                        'pending_media_processing' => 'Aguardando mídia',
                                        'pending_text_extraction' => 'Aguardando extração de texto',
                                        'skipped_no_profile' => 'Sem perfil',
                                        null, '' => 'Aguardando processamento',
                                        default => $message->ai_pipeline_status,
                                    };
                                    $summary = is_string($message->analysis_summary) && trim($message->analysis_summary) !== ''
                                        ? $message->analysis_summary
                                        : (is_string($message->body_excerpt) && trim($message->body_excerpt) !== '' ? $message->body_excerpt : 'Sem texto ou resumo');
                                    $profileSlug = is_string($message->analysis_profile_slug) ? $message->analysis_profile_slug : '';
                                    $hasPreviousResult = in_array($message->ai_pipeline_status, [null, ''], true)
                                        && ((is_string($message->display_category) && $message->display_category !== '')
                                            || (is_string($message->analysis_summary) && $message->analysis_summary !== ''));
                                @endphp
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-3 py-3 text-gray-700">{{ $message->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-3 text-gray-700">
                                        <div class="font-medium text-gray-900">{{ $message->monitoredSource?->label ?? 'Sem fonte monitorada' }}</div>
                                        @if ($message->monitoredSource?->identifier)
                                            <div class="mt-0.5 max-w-48 truncate text-xs text-gray-500" title="{{ $message->monitoredSource->identifier }}">{{ $message->monitoredSource->identifier }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-gray-700">
                                        @if ($profileSlug !== '')
                                            <div>{{ $profileNames[$profileSlug] ?? $profileSlug }}</div>
                                            <div class="mt-0.5 text-xs text-gray-500">{{ $profileSlug }}</div>
                                        @else
                                            <span class="text-gray-500">Não registrado</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-gray-700">
                                        <p class="max-w-md break-words">{{ \Illuminate\Support\Str::limit($summary, 180) }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span @class([
                                            'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-700' => $message->ai_pipeline_status === 'classified',
                                            'bg-amber-100 text-amber-700' => in_array($message->ai_pipeline_status, ['pending_media_processing', 'pending_text_extraction'], true),
                                            'bg-violet-100 text-violet-700' => $message->ai_pipeline_status === 'skipped_no_profile',
                                            'bg-gray-100 text-gray-600' => ! in_array($message->ai_pipeline_status, ['classified', 'pending_media_processing', 'pending_text_extraction', 'skipped_no_profile'], true),
                                        ])>{{ $processingLabel }}</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        @if (is_string($message->display_category) && $message->display_category !== '')
                                            <span class="inline-flex rounded-full bg-sky-100 px-2 py-1 text-xs font-medium text-sky-700">{{ $hasPreviousResult ? 'Anterior · ' : '' }}{{ $message->display_category }}</span>
                                        @else
                                            <span class="text-gray-500">Sem categoria</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-gray-700">
                                        {{ is_numeric($message->normalized_relevance_score) ? number_format((float) $message->normalized_relevance_score, 2, ',', '.').' / 100' : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center text-gray-700">
                                        {{ $message->offers_count !== null ? $message->offers_count : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <a href="{{ route('analyses.show', $message->id) }}" wire:navigate class="inline-flex rounded-md bg-indigo-100 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">
                                            Abrir detalhe
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-10 text-center text-gray-500">Nenhuma mensagem encontrada com estes filtros.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-4 py-4 sm:px-6">
                    {{ $messages->links() }}
                </div>
            </section>
        </div>
    </div>
</div>
