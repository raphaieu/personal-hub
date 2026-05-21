@extends('layouts.events-confirm')

@section('title', 'E-mail já confirmado — ' . $guest->event->title)

@section('content')
<div class="icon-wrap">
    <svg class="icon-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
</div>
<h1>E-mail já confirmado</h1>
<p>
    Olá, <strong>{{ $guest->name }}</strong>!<br>
    Seu e-mail já estava confirmado para <strong>{{ $guest->event->title }}</strong>.
</p>
<p style="margin-top: 0.75rem; font-size: 0.85rem;">
    Verifique sua caixa de entrada — o ingresso em PDF foi enviado anteriormente.
</p>
@endsection
