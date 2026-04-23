@extends('layouts.public')

@section('title', 'Oportunidades')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Oportunidades</h1>
        <p class="mt-1 text-sm text-gray-600">Listagem publica de comentarios curados (somente visibilidade publica ativa).</p>
    </div>

    <form method="get" action="{{ route('threads.opportunities') }}" class="mb-8 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="filter-q" class="block text-xs font-medium text-gray-600 mb-1">Busca</label>
            <input
                id="filter-q"
                type="search"
                name="q"
                value="{{ $filters['q'] }}"
                placeholder="Resumo ou texto"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm"
            >
        </div>
        <div>
            <label for="filter-category" class="block text-xs font-medium text-gray-600 mb-1">Categoria</label>
            <select id="filter-category" name="category" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) $filters['category'] === (string) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-source" class="block text-xs font-medium text-gray-600 mb-1">Source</label>
            <select id="filter-source" name="source" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">Todas</option>
                @foreach ($sources as $source)
                    <option value="{{ $source->id }}" @selected((string) $filters['source'] === (string) $source->id)>
                        {{ $source->label }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-sort" class="block text-xs font-medium text-gray-600 mb-1">Ordenar</label>
            <select id="filter-sort" name="sort" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                <option value="relevance" @selected($filters['sort'] === 'relevance')>Relevancia IA</option>
                <option value="votes" @selected($filters['sort'] === 'votes')>Mais votado</option>
                <option value="newest" @selected($filters['sort'] === 'newest')>Mais recente</option>
            </select>
        </div>
        <div class="sm:col-span-2 lg:col-span-4 flex flex-wrap gap-2">
            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Aplicar filtros
            </button>
            <a href="{{ route('threads.opportunities') }}" class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                Limpar
            </a>
        </div>
    </form>

    @if ($comments->isEmpty())
        <p class="text-sm text-gray-500">Nenhuma oportunidade publica com estes filtros.</p>
    @else
        <ul class="space-y-4">
            @foreach ($comments as $comment)
                <li class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            @if ($comment->is_featured)
                                <span class="inline-flex mb-2 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800">
                                    Destaque
                                </span>
                            @endif
                            <p class="text-base font-medium text-gray-900">
                                {{ $comment->ai_summary ?: \Illuminate\Support\Str::limit((string) $comment->content, 160) }}
                            </p>
                            @if ($comment->category)
                                <p class="mt-2 text-xs text-gray-500">{{ $comment->category->name }}</p>
                            @endif
                            @if ($comment->post?->post_url)
                                <a href="{{ $comment->post->post_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-xs text-indigo-600 hover:text-indigo-800">
                                    Ver thread no Threads
                                </a>
                            @endif
                        </div>
                        <div class="shrink-0 text-right text-xs text-gray-600">
                            <div>Score <span class="font-semibold text-gray-900">{{ $comment->score_total }}</span></div>
                            <div class="mt-1">
                                <span class="text-emerald-700">+{{ $comment->upvotes }}</span>
                                <span class="text-gray-400"> / </span>
                                <span class="text-rose-700">-{{ $comment->downvotes }}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-end gap-2">
                                <form method="post" action="{{ route('threads.opportunities.vote', $comment) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button
                                        type="submit"
                                        class="inline-flex min-w-[2.25rem] items-center justify-center rounded-md bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-200"
                                        title="Voto util"
                                    >
                                        +1
                                    </button>
                                </form>
                                <form method="post" action="{{ route('threads.opportunities.vote', $comment) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button
                                        type="submit"
                                        class="inline-flex min-w-[2.25rem] items-center justify-center rounded-md bg-rose-100 px-2 py-1 text-xs font-medium text-rose-800 hover:bg-rose-200"
                                        title="Voto nao util"
                                    >
                                        -1
                                    </button>
                                </form>
                            </div>
                            <p class="mt-2 max-w-[12rem] text-[10px] leading-snug text-gray-400">
                                Um voto por dia por dispositivo/rede (anonimo).
                            </p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-8">
            {{ $comments->links() }}
        </div>
    @endif
@endsection
