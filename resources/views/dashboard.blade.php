{{-- $hubCards definido na rota /dashboard (config/hub_dashboard.php) --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-10 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <p class="text-sm text-gray-600 px-4 sm:px-0 max-w-3xl">
                Acesso rápido às áreas do hub. O menu superior continua disponível; novas funcionalidades devem ganhar card aqui e link na navegação.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 px-4 sm:px-0">
                @foreach ($hubCards as $card)
                    <a
                        href="{{ route($card['route']) }}"
                        wire:navigate
                        class="group flex flex-col rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition duration-150 hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        <div class="flex items-start gap-4">
                            <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100 group-hover:bg-indigo-100 group-hover:text-indigo-700 transition-colors">
                                <x-hub.dashboard-icon :name="$card['icon']" class="!w-7 !h-7" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold text-gray-900 group-hover:text-indigo-700 transition-colors">
                                    {{ $card['title'] }}
                                </h3>
                                <p class="mt-2 text-sm text-gray-600 leading-relaxed">
                                    {{ $card['description'] }}
                                </p>
                            </div>
                        </div>
                        <span class="mt-4 inline-flex items-center text-sm font-medium text-indigo-600 group-hover:text-indigo-800">
                            Abrir
                            <svg class="ms-1 w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
