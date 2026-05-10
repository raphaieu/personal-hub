<div
    id="events-checkin-root"
    class="flex h-[100dvh] max-h-[100dvh] flex-col overflow-hidden bg-neutral-950"
    style="padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom)"
    data-scanner-idle="{{ $guestModel ? '0' : '1' }}"
    wire:key="events-checkin-shell"
>
    {{-- Barra superior fixa --}}
    <header class="flex shrink-0 items-center justify-between gap-2 border-b border-neutral-800 px-3 py-2">
        <div class="min-w-0">
            <p class="truncate text-[10px] font-semibold uppercase tracking-widest text-neutral-500">Portaria</p>
            <p class="truncate text-sm font-medium text-neutral-200">Check-in</p>
        </div>
        @if ($guestModel)
            <button
                type="button"
                wire:click="resetScanner"
                wire:loading.attr="disabled"
                class="shrink-0 rounded-lg bg-neutral-800 px-3 py-2 text-xs font-semibold text-neutral-200 active:bg-neutral-700"
            >
                Nova leitura
            </button>
        @endif
    </header>

    @if (session('events_checkin_notice'))
        <div
            class="shrink-0 border-b border-emerald-900/50 bg-emerald-950/90 px-3 py-2 text-center text-xs font-medium text-emerald-200"
            role="status"
        >
            {{ session('events_checkin_notice') }}
        </div>
    @endif

    @if ($notice !== '')
        <div
            class="shrink-0 border-b border-amber-900/50 bg-amber-950/90 px-3 py-2 text-center text-xs text-amber-100"
            role="alert"
        >
            {{ $notice }}
        </div>
    @endif

    <main class="flex min-h-0 flex-1 flex-col">
        @if (! $guestModel)
            {{-- Modo leitor: ocupa o espaço sem scroll --}}
            <div class="relative flex min-h-0 flex-1 flex-col bg-black">
                <div
                    id="events-checkin-qr-region"
                    class="h-full min-h-0 w-full flex-1"
                    wire:ignore
                ></div>
                <div
                    class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 to-transparent px-4 pb-6 pt-16 text-center"
                >
                    <p class="text-sm font-medium text-white">Aponte para o QR do ingresso</p>
                    <p class="mt-1 text-xs text-neutral-400">Permita o uso da câmera · Leitura na mesma aba, sem abrir outro app</p>
                </div>
            </div>
        @else
            {{-- Detalhe do convidado --}}
            <div class="flex min-h-0 flex-1 flex-col justify-between px-4 pt-3">
                <div class="space-y-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Evento</p>
                        <p class="text-lg font-semibold leading-snug text-white">{{ $guestModel->event->title }}</p>
                    </div>
                    <div class="rounded-2xl border border-neutral-800 bg-neutral-900/80 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Convidado</p>
                        <p class="mt-1 text-2xl font-bold tracking-tight text-white">{{ $guestModel->name }}</p>
                        <p class="mt-2 truncate text-sm text-neutral-400">{{ $guestModel->email }}</p>
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs text-neutral-400">
                        <span class="rounded-full bg-neutral-800 px-2.5 py-1 font-medium text-neutral-200">
                            {{ $guestModel->status->value }}
                        </span>
                        @if ($guestModel->checked_in_at)
                            <span class="rounded-full bg-emerald-950 px-2.5 py-1 text-emerald-300">
                                Check-in {{ $guestModel->checked_in_at->timezone($guestModel->event->timezone ?? 'America/Sao_Paulo')->format('d/m H:i') }}
                            </span>
                        @else
                            <span class="rounded-full bg-neutral-800 px-2.5 py-1 text-neutral-300">Sem check-in</span>
                        @endif
                    </div>
                </div>

                <div class="grid shrink-0 grid-cols-2 gap-3 pb-3 pt-4">
                    <button
                        type="button"
                        wire:click="confirmCheckIn"
                        wire:loading.attr="disabled"
                        class="flex min-h-14 items-center justify-center rounded-2xl bg-emerald-600 text-sm font-bold text-white shadow-lg shadow-emerald-900/40 active:bg-emerald-500 disabled:opacity-50"
                    >
                        Liberar
                    </button>
                    <button
                        type="button"
                        wire:click="denyEntry"
                        wire:loading.attr="disabled"
                        class="flex min-h-14 items-center justify-center rounded-2xl bg-neutral-800 text-sm font-bold text-neutral-200 active:bg-neutral-700"
                    >
                        Não liberar
                    </button>
                </div>
            </div>
        @endif
    </main>
</div>
