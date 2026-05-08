@extends('layouts.public')

@section('title', $album->title)
@section('public_header_title', $album->parent ? ($album->parent->title.' > '.$album->title) : $album->title)
@section('public_header_href', route('albums.viewer', ['slug' => $album->slug]))

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
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($mediaItems as $item)
                <article class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
                    @if ($item['processing_status'] !== 'done')
                        <div class="flex h-32 items-center justify-center rounded bg-gray-100 text-xs text-gray-500">
                            Processando mídia...
                        </div>
                    @elseif ($item['type'] === 'video')
                        <video controls class="h-32 w-full rounded bg-black object-cover">
                            <source src="{{ $item['view_url'] }}">
                            Seu navegador não suporta vídeo.
                        </video>
                    @else
                        <img
                            src="{{ $item['view_url'] }}"
                            alt="{{ $item['filename_original'] }}"
                            class="h-32 w-full rounded object-cover"
                            @if (! $album->download_enabled)
                                draggable="false"
                                oncontextmenu="return false"
                            @endif
                        >
                    @endif

                    <div class="mt-2 flex items-center justify-between gap-2">
                        <p class="truncate text-xs text-gray-600">{{ $item['filename_original'] }}</p>
                        @if ($album->download_enabled)
                            <a href="{{ $item['download_url'] }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                Baixar
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
