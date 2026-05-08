<x-mail::message>
# Seu link para enviar mídias

Você já confirmou o e-mail para o álbum **{{ $album->title }}**. Use o botão abaixo para enviar arquivos (o link permanece o mesmo enquanto estiver válido).

<x-mail::button :url="$uploadUrl">
Abrir página de envio
</x-mail::button>

Obrigado,<br>
{{ config('app.name') }}
</x-mail::message>
