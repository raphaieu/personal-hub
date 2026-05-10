@extends('layouts.public')

@section('title', 'Contribuir — '.$album->title)
@section('public_header_title', 'Contribuir — '.$album->title)
@section('public_header_href', url('/'))

@section('content')
    <div class="max-w-md mx-auto rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-gray-900">Enviar mídias</h1>
        <p class="mt-2 text-sm text-gray-600">
            Álbum: <strong>{{ $album->title }}</strong>. Informe seu e-mail para receber o link de confirmação.
        </p>

        @if (session('contribute_status'))
            <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                {{ session('contribute_status') }}
            </div>
        @endif

        <form method="post" action="{{ route('albums.contribute.verify') }}" class="mt-4 space-y-3">
            @csrf
            <input type="hidden" name="album_id" value="{{ $album->id }}">
            <input type="hidden" name="invite_token" value="{{ $inviteToken }}">
            <div>
                <label for="contrib-email" class="block text-xs font-medium text-gray-600 mb-1">E-mail</label>
                <input
                    id="contrib-email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm"
                    required
                >
                @error('email')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Continuar
            </button>
        </form>
    </div>
@endsection
