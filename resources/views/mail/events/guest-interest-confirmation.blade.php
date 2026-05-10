<x-mail::message>
# Olá, {{ $guest->name }}

Recebemos sua inscrição de interesse no evento **{{ $guest->event->title }}**.

Para **confirmar seu e-mail** e receber seu ingresso, clique no botão abaixo.

<x-mail::button :url="route('events.guest.confirm', ['token' => $guest->email_confirmation_token])">
Confirmar e receber ingresso
</x-mail::button>

@if($guest->event->starts_at)
**Data:** {{ $guest->event->starts_at->timezone($guest->event->timezone ?? 'America/Sao_Paulo')->locale('pt_BR')->isoFormat('LLL') }}
@endif

Se você não solicitou esta inscrição, pode ignorar este e-mail.

Abraço,

{{ config('app.name') }}
</x-mail::message>
