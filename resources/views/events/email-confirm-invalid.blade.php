@extends('layouts.events-confirm')

@section('title', 'Link inválido — Events')

@section('content')
<div class="icon-wrap">
    <svg class="icon-error" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <line x1="15" y1="9" x2="9" y2="15"/>
        <line x1="9" y1="9" x2="15" y2="15"/>
    </svg>
</div>
<h1>Link inválido</h1>
<p>
    Este link de confirmação não é válido ou já foi utilizado.
</p>
<p style="margin-top: 0.75rem; font-size: 0.85rem;">
    Se você acredita que isso é um erro, entre em contato com o organizador do evento.
</p>
@endsection
