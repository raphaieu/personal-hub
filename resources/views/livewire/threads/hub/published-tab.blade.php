<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-gray-900">Published</h3>
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500">{{ $publishedComments->total() }} publicado(s) neste filtro</span>
            <select wire:model.live="publishedPerPage" class="rounded-md border-gray-300 text-xs">
                <option value="10">10/pag</option>
                <option value="25">25/pag</option>
                <option value="50">50/pag</option>
                <option value="100">100/pag</option>
            </select>
        </div>
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
        <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 w-[38%]">Resumo</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 w-36">Categoria</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 w-28">Destaque</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 w-24">Metricas</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 w-[20%]">Origem</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 w-32 sticky right-0 bg-gray-50">Acoes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($publishedComments as $comment)
                    <tr @class(['bg-violet-50/40' => $comment->is_featured])>
                        <td class="px-3 py-2 align-top">
                            <textarea
                                wire:model="publishedForms.{{ $comment->id }}.ai_summary"
                                rows="4"
                                class="w-full rounded-md border-gray-300 text-xs"
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
                            <div class="text-xs break-words">{{ $comment->author_handle ?: '-' }}</div>
                            @if ($comment->post?->source?->label)
                                <div class="text-xs text-gray-500 mt-0.5 break-words">{{ $comment->post->source->label }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 align-top sticky right-0 bg-white">
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

    <div class="mt-4">
        {{ $publishedComments->links() }}
    </div>
</div>
