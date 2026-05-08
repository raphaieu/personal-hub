@extends('layouts.public')

@section('title', $album->title)

@section('content')
    <div class="max-w-md mx-auto rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-gray-900">{{ $album->title }}</h1>
        <p class="mt-2 text-sm text-gray-600">Este álbum está protegido por senha.</p>

        <form method="post" action="{{ route('albums.viewer.auth', ['slug' => $album->slug]) }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label for="album-password" class="block text-xs font-medium text-gray-600 mb-1">Senha</label>
                <input
                    id="album-password"
                    type="password"
                    name="password"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm"
                    required
                >
                @error('password')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Entrar no álbum
            </button>
        </form>
    </div>
@endsection
