@extends('layouts.public')

@section('title', 'E-mail confirmado')
@section('public_header_title', 'Contribuir — '.$album->title)
@section('public_header_href', url('/'))

@section('content')
    <div class="max-w-md mx-auto rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-gray-900">E-mail confirmado</h1>
        <p class="mt-2 text-sm text-gray-600">
            Você já pode enviar fotos e vídeos para o álbum <strong>{{ $album->title }}</strong>.
        </p>
        <a href="{{ $uploadUrl }}" class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Ir para o envio de arquivos
        </a>
    </div>
@endsection
