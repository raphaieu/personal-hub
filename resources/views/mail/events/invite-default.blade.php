<x-mail::message>
# Você está convidado

Olá **{{ $guest->name }}**,

Este é o seu convite para o evento **{{ $guest->event->title }}**.

@if($guest->event->starts_at)
**Quando:** {{ $guest->event->starts_at->timezone($guest->event->timezone ?? 'America/Sao_Paulo')->locale('pt_BR')->isoFormat('LLLL') }}
@endif

@if(config('events.frontend_url'))
Confirme ou veja os detalhes em: {{ config('events.frontend_url') }}/{{ $guest->event->slug }}
@endif

Abraço,

{{ config('app.name') }}
</x-mail::message>
