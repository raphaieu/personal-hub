<x-mail::message>
# Novas mídias por contribuidores

Álbum: **{{ $album->title }}** (slug: `{{ $album->slug }}`)

Foram registrados **{{ count($lines) }}** novo(s) arquivo(s) na última janela de notificação:

@foreach ($lines as $line)
- **{{ $line['filename'] ?? '?' }}** ({{ $line['type'] ?? '?' }}) — {{ $line['contributor_email'] ?? '?' }}
@endforeach

<x-mail::button :url="route('albums.hub.show', ['album' => $album->id])">
Abrir álbum no hub
</x-mail::button>

Obrigado,<br>
{{ config('app.name') }}
</x-mail::message>
