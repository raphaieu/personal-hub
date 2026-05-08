@extends('layouts.public')

@section('title', 'Link inválido')
@section('public_header_title', 'Contribuição')
@section('public_header_href', url('/'))

@section('content')
    <div class="max-w-md mx-auto rounded-lg border border-amber-200 bg-amber-50 p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-amber-900">Não foi possível confirmar</h1>
        <p class="mt-2 text-sm text-amber-800">{{ $message }}</p>
    </div>
@endsection
