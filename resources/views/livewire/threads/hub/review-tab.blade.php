<div class="space-y-4 mb-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-gray-900">Review</h3>
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500">{{ $reviewComments->total() }} itens no filtro</span>
            <select wire:model.live="reviewPerPage" class="rounded-md border-gray-300 text-xs">
                <option value="25">25/pag</option>
                <option value="50">50/pag</option>
                <option value="100">100/pag</option>
                <option value="200">200/pag</option>
            </select>
        </div>
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
    <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
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
                <th class="px-3 py-2 text-left font-medium text-gray-600 w-[30%]">Comentario</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600 w-[30%]">Resumo IA</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600 w-24">Relevancia</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600 w-28">Status</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600 w-20">Publico</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600 w-[260px] sticky right-0 bg-gray-50">Acoes</th>
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
                    <td class="px-3 py-2 text-gray-700 align-top">
                        <div class="whitespace-pre-wrap break-words">{{ $comment->content ?: '-' }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $comment->author_handle ?: '-' }}
                            @if ($comment->post?->source?->label)
                                • {{ $comment->post->source->label }}
                            @endif
                        </div>
                    </td>
                    <td class="px-3 py-2 text-gray-700 align-top">
                        <div class="whitespace-pre-wrap break-words">{{ $comment->ai_summary ?: 'Sem resumo IA' }}</div>
                    </td>
                    <td class="px-3 py-2 text-gray-700 align-top">
                        {{ $comment->ai_relevance_score !== null ? number_format((float) $comment->ai_relevance_score, 2, ',', '.') : '-' }}
                    </td>
                    <td class="px-3 py-2 align-top">
                        <span @class([
                            'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                            'bg-indigo-100 text-indigo-700' => $comment->status === 'pending_review',
                            'bg-amber-100 text-amber-700' => $comment->status === 'ignored',
                            'bg-gray-100 text-gray-600' => ! in_array($comment->status, ['pending_review', 'ignored'], true),
                        ])>
                            {{ $comment->status }}
                        </span>
                    </td>
                    <td class="px-3 py-2 align-top">
                        <span @class([
                            'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                            'bg-emerald-100 text-emerald-700' => $comment->is_public,
                            'bg-gray-100 text-gray-600' => ! $comment->is_public,
                        ])>
                            {{ $comment->is_public ? 'Sim' : 'Nao' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 sticky right-0 bg-white align-top">
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

<div class="mt-4">
    {{ $reviewComments->links() }}
</div>
