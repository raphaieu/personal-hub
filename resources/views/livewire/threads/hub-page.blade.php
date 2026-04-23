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
                @if (session('threads_hub_notice'))
                    <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-700">
                        {{ session('threads_hub_notice') }}
                    </div>
                @endif

                @if ($currentTab === 'sources')
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

                    <form wire:submit="createSource" class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-6">
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
                                        <td colspan="6" class="px-3 py-6 text-center text-gray-500">
                                            Nenhuma source cadastrada ainda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($currentTab === 'review')
                    <div class="space-y-4 mb-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold text-gray-900">Review</h3>
                            <span class="text-sm text-gray-500">{{ $reviewComments->count() }} itens no filtro</span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                <select wire:model.live="reviewStatus" class="w-full rounded-md border-gray-300 text-sm">
                                    @foreach ($reviewStatusOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Categoria</label>
                                <select wire:model.live="reviewCategory" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="all">Todas</option>
                                    @foreach ($reviewCategoryOptions as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Source</label>
                                <select wire:model.live="reviewSource" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="all">Todas</option>
                                    @foreach ($reviewSourceOptions as $source)
                                        <option value="{{ $source->id }}">{{ $source->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Resumo IA</label>
                                <select wire:model.live="reviewWithoutSummary" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="0">Todos</option>
                                    <option value="1">Sem resumo IA</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Ordenar por</label>
                                <select wire:model.live="reviewSort" class="w-full rounded-md border-gray-300 text-sm">
                                    @foreach ($reviewSortOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <p class="text-xs text-gray-600 leading-relaxed">
                            <span class="font-medium text-gray-700">IA (base inteira):</span>
                            {{ $pendingClassificationCount }} sem resumo IA · espaco ~{{ $aiDispatchSpacingSeconds }}s entre jobs.
                        </p>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm text-gray-600">{{ $reviewSelectedCount }} selecionado(s)</span>
                            <button
                                type="button"
                                wire:click="batchMoveSelectedToPendingReview"
                                class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-200"
                            >
                                Mover p/ review
                            </button>
                            <button
                                type="button"
                                wire:click="batchIgnoreSelected"
                                class="inline-flex items-center rounded-md bg-amber-100 px-2.5 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-200"
                            >
                                Ignorar
                            </button>
                            <button
                                type="button"
                                wire:click="batchPublishSelected"
                                class="inline-flex items-center rounded-md bg-indigo-100 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200"
                            >
                                Publicar
                            </button>
                            <button
                                type="button"
                                wire:click="batchUnpublishSelected"
                                class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                            >
                                Despublicar
                            </button>
                            <button
                                type="button"
                                wire:click="batchReclassifySelected"
                                class="inline-flex items-center rounded-md bg-violet-100 px-2.5 py-1.5 text-xs font-medium text-violet-700 hover:bg-violet-200"
                            >
                                Reclassificar
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 w-10">
                                        <label class="inline-flex items-center gap-1 cursor-pointer" title="Selecionar todos nesta pagina (filtro atual)">
                                            <input
                                                type="checkbox"
                                                wire:click.prevent="toggleSelectAllReviewOnPage"
                                                @checked($reviewAllVisibleSelected)
                                                class="rounded border-gray-300 text-indigo-600"
                                            >
                                            <span class="sr-only">Selecionar todos</span>
                                        </label>
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Comentario</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Resumo IA</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Relevancia</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Status</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Publico</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Acoes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($reviewComments as $comment)
                                    <tr @class(['bg-amber-50/40' => $comment->status === 'ignored'])>
                                        <td class="px-3 py-2">
                                            <input
                                                type="checkbox"
                                                wire:model.live="selectedReviewCommentIds"
                                                value="{{ $comment->id }}"
                                                class="rounded border-gray-300 text-indigo-600"
                                            >
                                        </td>
                                        <td class="px-3 py-2 text-gray-700">
                                            <div class="max-w-md truncate">{{ $comment->content ?: '-' }}</div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ $comment->author_handle ?: '-' }}
                                                @if ($comment->post?->source?->label)
                                                    • {{ $comment->post->source->label }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-gray-700">
                                            <div class="max-w-sm truncate">{{ $comment->ai_summary ?: 'Sem resumo IA' }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-gray-700">
                                            {{ $comment->ai_relevance_score !== null ? number_format((float) $comment->ai_relevance_score, 2, ',', '.') : '-' }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                                'bg-indigo-100 text-indigo-700' => $comment->status === 'pending_review',
                                                'bg-amber-100 text-amber-700' => $comment->status === 'ignored',
                                                'bg-gray-100 text-gray-600' => ! in_array($comment->status, ['pending_review', 'ignored'], true),
                                            ])>
                                                {{ $comment->status }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                                'bg-emerald-100 text-emerald-700' => $comment->is_public,
                                                'bg-gray-100 text-gray-600' => ! $comment->is_public,
                                            ])>
                                                {{ $comment->is_public ? 'Sim' : 'Nao' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button
                                                    type="button"
                                                    wire:click="reclassifyComment({{ $comment->id }})"
                                                    class="inline-flex items-center rounded-md bg-indigo-100 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200"
                                                >
                                                    Reclassificar
                                                </button>
                                                @if ($comment->status === 'ignored')
                                                    <button
                                                        type="button"
                                                        wire:click="moveCommentToPendingReview({{ $comment->id }})"
                                                        class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-200"
                                                    >
                                                        Mover p/ review
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        wire:click="ignoreComment({{ $comment->id }})"
                                                        class="inline-flex items-center rounded-md bg-amber-100 px-2.5 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-200"
                                                    >
                                                        Ignorar
                                                    </button>
                                                @endif
                                                <button
                                                    type="button"
                                                    wire:click="toggleCommentPublic({{ $comment->id }})"
                                                    class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                                >
                                                    {{ $comment->is_public ? 'Despublicar' : 'Publicar' }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3 py-6 text-center text-gray-500">
                                            Nenhum comentario para review neste filtro.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold text-gray-900">Published</h3>
                            <span class="text-sm text-gray-500">{{ $publishedComments->count() }} publicado(s) neste filtro</span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Categoria</label>
                                <select wire:model.live="publishedCategory" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="all">Todas</option>
                                    @foreach ($publishedCategoryOptions as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Source</label>
                                <select wire:model.live="publishedSource" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="all">Todas</option>
                                    @foreach ($publishedSourceOptions as $source)
                                        <option value="{{ $source->id }}">{{ $source->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Ordenar por</label>
                                <select wire:model.live="publishedSort" class="w-full rounded-md border-gray-300 text-sm">
                                    @foreach ($publishedSortOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Resumo</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Categoria</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Destaque</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Metricas</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Origem</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($publishedComments as $comment)
                                        <tr @class(['bg-violet-50/40' => $comment->is_featured])>
                                            <td class="px-3 py-2 align-top">
                                                <textarea
                                                    wire:model="publishedForms.{{ $comment->id }}.ai_summary"
                                                    rows="2"
                                                    class="w-full max-w-md rounded-md border-gray-300 text-xs"
                                                    placeholder="Resumo exibido no feed publico"
                                                ></textarea>
                                                @error('publishedForms.'.$comment->id.'.ai_summary')
                                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <select
                                                    wire:model="publishedForms.{{ $comment->id }}.threads_category_id"
                                                    class="w-full min-w-[8rem] rounded-md border-gray-300 text-xs"
                                                >
                                                    <option value="">—</option>
                                                    @foreach ($publishedCategoryOptions as $cat)
                                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('publishedForms.'.$comment->id.'.threads_category_id')
                                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                                                    <input
                                                        wire:model="publishedForms.{{ $comment->id }}.is_featured"
                                                        type="checkbox"
                                                        class="rounded border-gray-300 text-indigo-600"
                                                    >
                                                    Destaque
                                                </label>
                                            </td>
                                            <td class="px-3 py-2 text-gray-700 align-top whitespace-nowrap">
                                                <div class="text-xs">
                                                    <span class="text-emerald-700">+{{ $comment->upvotes }}</span>
                                                    <span class="text-gray-400 mx-1">/</span>
                                                    <span class="text-rose-700">-{{ $comment->downvotes }}</span>
                                                </div>
                                                <div class="text-xs font-medium text-gray-900 mt-1">
                                                    Score {{ $comment->score_total }}
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-gray-600 align-top">
                                                <div class="max-w-xs truncate text-xs">{{ $comment->author_handle ?: '-' }}</div>
                                                @if ($comment->post?->source?->label)
                                                    <div class="text-xs text-gray-500 mt-0.5">{{ $comment->post->source->label }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <div class="flex flex-col gap-2">
                                                    <button
                                                        type="button"
                                                        wire:click="savePublishedComment({{ $comment->id }})"
                                                        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                                                    >
                                                        Salvar
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="unpublishPublishedComment({{ $comment->id }})"
                                                        class="inline-flex items-center justify-center rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                                    >
                                                        Despublicar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-3 py-6 text-center text-gray-500">
                                                Nenhum comentario publicado neste filtro.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
