<x-mail::message>
# Confirme sua contribuição

Você foi convidado a enviar mídias para o álbum **{{ $album->title }}**.

<x-mail::button :url="$confirmUrl">
Confirmar e-mail
</x-mail::button>

Este link expira em aproximadamente {{ (int) config('services.albums.contribution_verify_ttl_hours', 24) }} horas.

Se você não solicitou este convite, ignore este e-mail.

Obrigado,<br>
{{ config('app.name') }}
</x-mail::message>
