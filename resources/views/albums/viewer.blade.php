@extends('layouts.public')

@section('title', $album->title)
@section('public_header_title', $album->parent ? ($album->parent->title.' > '.$album->title) : $album->title)
@section('public_header_href', route('albums.viewer', ['slug' => $album->slug]))

@php
    $photoItems = $mediaItems
        ->filter(fn (array $i) => $i['type'] === 'photo' && $i['processing_status'] !== 'failed')
        ->values();
@endphp

@section('content')
    <nav class="mb-4 text-xs text-gray-500" aria-label="Breadcrumb">
        @if ($album->parent)
            <a href="{{ route('albums.viewer', ['slug' => $album->parent->slug]) }}" class="hover:text-indigo-700">
                {{ $album->parent->title }}
            </a>
            <span class="mx-1">/</span>
        @endif
        <span class="font-medium text-gray-700">{{ $album->title }}</span>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">{{ $album->title }}</h1>
        @if ($album->description)
            <p class="mt-1 text-sm text-gray-600">{{ $album->description }}</p>
        @endif
    </div>

    @if ($mediaItems->isEmpty())
        <p class="text-sm text-gray-500">Nenhuma mídia disponível neste álbum.</p>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" id="album-grid">
            @foreach ($mediaItems as $item)
                @php
                    $photoIndex = $item['type'] === 'photo' && $item['processing_status'] !== 'failed'
                        ? $photoItems->search(fn (array $p) => $p['id'] === $item['id'])
                        : false;
                @endphp
                <article class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
                    @if ($item['processing_status'] === 'failed')
                        <div class="flex h-32 items-center justify-center rounded bg-red-50 text-xs text-red-700 px-2 text-center">
                            Não foi possível processar este arquivo.
                        </div>
                    @elseif ($item['type'] === 'video')
                        <video controls class="h-32 w-full rounded bg-black object-cover">
                            <source src="{{ $item['medium_url'] }}">
                            Seu navegador não suporta vídeo.
                        </video>
                    @else
                        <button
                            type="button"
                            data-album-open
                            @if ($photoIndex !== false) data-album-index="{{ $photoIndex }}" @endif
                            class="group relative block w-full overflow-hidden rounded focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            aria-label="Ampliar {{ $item['caption'] }}"
                        >
                            <img
                                src="{{ $item['thumb_url'] }}"
                                alt="{{ $item['caption'] }}"
                                loading="lazy"
                                class="h-32 w-full rounded object-cover transition group-hover:scale-[1.02]"
                                @if (! $album->download_enabled)
                                    draggable="false"
                                    oncontextmenu="return false"
                                @endif
                            >
                            @if (in_array($item['processing_status'], ['pending', 'processing'], true))
                                <span class="absolute bottom-1 left-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">
                                    Gerando miniaturas…
                                </span>
                            @endif
                        </button>
                    @endif

                    <div class="mt-2 flex items-center justify-between gap-2">
                        <p class="truncate text-xs text-gray-600" title="{{ $item['filename_original'] }}">{{ $item['caption'] }}</p>
                        @if ($album->download_enabled)
                            <a href="{{ $item['download_url'] }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                Baixar
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if ($photoItems->isNotEmpty())
            <div
                id="album-lightbox"
                class="fixed inset-0 z-50 hidden items-center justify-center bg-black/85 px-2 py-4 sm:px-6"
                role="dialog"
                aria-modal="true"
                aria-label="Visualização ampliada"
            >
                <button
                    type="button"
                    data-album-close
                    class="absolute right-3 top-3 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white"
                    aria-label="Fechar"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 01-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" /></svg>
                </button>

                <button
                    type="button"
                    data-album-prev
                    class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white sm:left-6"
                    aria-label="Anterior"
                >
                    <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 010 1.06L8.06 10l4.72 4.72a.75.75 0 11-1.06 1.06l-5.25-5.25a.75.75 0 010-1.06l5.25-5.25a.75.75 0 011.06 0z" clip-rule="evenodd" /></svg>
                </button>

                <button
                    type="button"
                    data-album-next
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white sm:right-6"
                    aria-label="Próximo"
                >
                    <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.22 4.22a.75.75 0 011.06 0l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06-1.06L11.94 10 7.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" /></svg>
                </button>

                <div class="flex max-h-full max-w-5xl flex-col items-center gap-3">
                    <img
                        id="album-lightbox-image"
                        alt=""
                        class="max-h-[80vh] max-w-full rounded shadow-2xl"
                        @if (! $album->download_enabled)
                            draggable="false"
                            oncontextmenu="return false"
                        @endif
                    >
                    <div class="flex w-full items-center justify-between gap-3 text-xs text-white/80">
                        <span id="album-lightbox-caption" class="truncate"></span>
                        <span class="flex items-center gap-3">
                            @if ($album->download_enabled)
                                <a id="album-lightbox-download" href="#" class="font-medium text-white hover:text-indigo-200">
                                    Baixar original
                                </a>
                            @endif
                            <span id="album-lightbox-counter" class="tabular-nums"></span>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    @endif
@endsection

@push('scripts')
    @if ($photoItems->isNotEmpty())
        <script id="album-lightbox-data" type="application/json">@json($photoItems->values())</script>
        <script>
            (function () {
                const dataEl = document.getElementById('album-lightbox-data');
                if (!dataEl) return;
                const items = JSON.parse(dataEl.textContent || '[]');
                if (!items.length) return;

                const modal = document.getElementById('album-lightbox');
                const img = document.getElementById('album-lightbox-image');
                const caption = document.getElementById('album-lightbox-caption');
                const counter = document.getElementById('album-lightbox-counter');
                const downloadLink = document.getElementById('album-lightbox-download');
                let current = 0;

                function render(index) {
                    current = (index + items.length) % items.length;
                    const item = items[current];
                    img.src = item.medium_url;
                    img.alt = item.caption || '';
                    caption.textContent = item.caption || '';
                    counter.textContent = (current + 1) + ' / ' + items.length;
                    if (downloadLink) downloadLink.href = item.download_url;
                }

                function open(index) {
                    render(index);
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                }

                function close() {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.body.style.overflow = '';
                    img.removeAttribute('src');
                }

                document.querySelectorAll('[data-album-open]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const idx = parseInt(btn.getAttribute('data-album-index') || '0', 10);
                        open(Number.isFinite(idx) ? idx : 0);
                    });
                });

                modal.addEventListener('click', function (e) {
                    if (e.target === modal) close();
                });

                modal.querySelector('[data-album-close]').addEventListener('click', close);
                modal.querySelector('[data-album-prev]').addEventListener('click', function () { render(current - 1); });
                modal.querySelector('[data-album-next]').addEventListener('click', function () { render(current + 1); });

                document.addEventListener('keydown', function (e) {
                    if (modal.classList.contains('hidden')) return;
                    if (e.key === 'Escape') close();
                    else if (e.key === 'ArrowLeft') render(current - 1);
                    else if (e.key === 'ArrowRight') render(current + 1);
                });
            })();
        </script>
    @endif
@endpush
