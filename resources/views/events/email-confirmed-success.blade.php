<x-guest-layout>
    <p class="text-sm text-gray-700 mb-2">
        Obrigado, <strong>{{ $guest->name }}</strong>!
    </p>
    <p class="text-sm text-gray-700">
        Seu e-mail foi confirmado para <strong>{{ $guest->event->title }}</strong>.
        Enviamos o ingresso em PDF para <strong>{{ $guest->email }}</strong>.
    </p>
</x-guest-layout>
