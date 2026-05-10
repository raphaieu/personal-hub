<x-guest-layout>
    <p class="text-sm text-gray-700 mb-2">
        Olá, <strong>{{ $guest->name }}</strong>.
    </p>
    <p class="text-sm text-gray-700">
        Seu e-mail já estava confirmado para <strong>{{ $guest->event->title }}</strong>.
        Verifique sua caixa de entrada pelo ingresso enviado anteriormente (PDF).
    </p>
</x-guest-layout>
