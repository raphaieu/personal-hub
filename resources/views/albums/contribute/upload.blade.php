@extends('layouts.public')

@section('title', 'Enviar arquivos — '.$album->title)
@section('public_header_title', 'Enviar — '.$album->title)
@section('public_header_href', url('/'))

@section('content')
    <div class="max-w-lg mx-auto rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-gray-900">Enviar mídias</h1>
        <p class="mt-2 text-sm text-gray-600">
            Álbum: <strong>{{ $album->title }}</strong><br>
            Conta: <span class="font-mono text-xs">{{ $contributor->email }}</span>
        </p>
        <p class="mt-2 text-xs text-gray-500">
            Limite por contribuidor neste álbum: {{ (int) config('services.albums.contribution_max_media_per_contributor', 100) }} arquivos (total).
            Tamanho máximo por arquivo: {{ round(config('services.albums.max_upload_bytes') / (1024 * 1024), 0) }} MB.
            Até <strong>{{ $effectiveMaxUploadFiles }}</strong> arquivo(s) por envio.
            @if ($effectiveMaxUploadFiles < $configuredMaxFilesPerBatch)
                <span class="block mt-1 text-amber-800">Limite do PHP por requisição: {{ $phpMaxFileUploads }} (<code class="text-[10px] bg-amber-100 px-1 rounded">max_file_uploads</code>). Envie em vários lotes ou ajuste o php.ini.</span>
            @endif
        </p>

        @if (session('contribute_upload_ok'))
            <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                {{ session('contribute_upload_ok') }}
            </div>
        @endif

        <form
            method="post"
            action="{{ route('albums.contribute.upload', ['upload_token' => $uploadToken]) }}"
            enctype="multipart/form-data"
            class="mt-4 space-y-3"
        >
            @csrf
            <div>
                <label for="contrib-files" class="block text-xs font-medium text-gray-600 mb-1">Arquivos</label>
                <input
                    id="contrib-files"
                    type="file"
                    name="files[]"
                    multiple
                    class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                    required
                >
                @error('files')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
                @error('files.*')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Enviar
            </button>
        </form>
    </div>
@endsection
