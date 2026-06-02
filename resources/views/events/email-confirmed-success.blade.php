@extends('layouts.events-confirm')

@section('title', 'E-mail confirmado — ' . $guest->event->title)

@section('content')
<div class="icon-wrap">
    <svg class="icon-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
</div>
<h1>E-mail confirmado!</h1>
<p>
    Obrigado, <strong>{{ $guest->name }}</strong>!<br>
    Sua presença em <strong>{{ $guest->event->title }}</strong> está confirmada.
</p>
<p style="margin-top: 0.75rem; font-size: 0.85rem;">
    Estamos enviando o ingresso em PDF para <strong>{{ $guest->email }}</strong>.<br>
    Deve chegar em instantes — verifique a caixa de entrada e o spam.
</p>
@endsection
