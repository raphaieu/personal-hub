<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Hub Eventos
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('events_hub_notice'))
                <div class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-800">
                    {{ session('events_hub_notice') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">
                    {{ $editingId ? 'Editar evento' : 'Novo evento' }}
                </h3>
                <form wire:submit="saveEvent" class="grid grid-cols-1 gap-3 md:grid-cols-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Título</label>
                        <input wire:model.live="formTitle" type="text" class="w-full rounded-md border-gray-300 text-sm">
                        @error('formTitle') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Slug (URL)</label>
                        <input wire:model="formSlug" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="meu-evento">
                        @error('formSlug') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select wire:model="formStatus" class="w-full rounded-md border-gray-300 text-sm">
                            @foreach (\App\Enums\Events\EventStatus::cases() as $st)
                                <option value="{{ $st->value }}">{{ $st->value }}</option>
                            @endforeach
                        </select>
                        @error('formStatus') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Timezone</label>
                        <input wire:model="formTimezone" type="text" class="w-full rounded-md border-gray-300 text-sm">
                        @error('formTimezone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Início</label>
                        <input wire:model="formStartsAt" type="datetime-local" class="w-full rounded-md border-gray-300 text-sm">
                        @error('formStartsAt') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Fim</label>
                        <input wire:model="formEndsAt" type="datetime-local" class="w-full rounded-md border-gray-300 text-sm">
                        @error('formEndsAt') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Capacidade</label>
                        <input wire:model.number="formCapacity" type="number" min="1" class="w-full rounded-md border-gray-300 text-sm" placeholder="vazio = ilimitado">
                        @error('formCapacity') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-3 flex flex-wrap gap-4 items-center pt-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input wire:model.boolean="formRequiresRef" type="checkbox" class="rounded border-gray-300">
                            Exige link de referral (lista fechada)
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input wire:model.boolean="formRequiresTurnstile" type="checkbox" class="rounded border-gray-300">
                            Turnstile no formulário público
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input wire:model.boolean="formRequiresPhoto" type="checkbox" class="rounded border-gray-300">
                            Exige foto do convidado
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input wire:model.boolean="formRegistrationOpen" type="checkbox" class="rounded border-gray-300">
                            Inscrições abertas
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input wire:model.boolean="formRequiresPayment" type="checkbox" class="rounded border-gray-300">
                            Exige pagamento (Mercado Pago)
                        </label>
                    </div>
                    @if ($formRequiresPayment)
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Valor do ingresso (R$)</label>
                            <input wire:model="formTicketAmount" type="text" inputmode="decimal" class="w-full rounded-md border-gray-300 text-sm" placeholder="20.00">
                            @error('formTicketAmount') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Conta Mercado Pago</label>
                            <select wire:model="formMercadoPagoAccountId" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">Selecione…</option>
                                @foreach ($mercadoPagoAccounts as $mpAccount)
                                    <option value="{{ $mpAccount->id }}">{{ $mpAccount->label }} ({{ $mpAccount->environment->value }})</option>
                                @endforeach
                            </select>
                            @error('formMercadoPagoAccountId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div class="md:col-span-6">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mensagem quando fechado (opcional)</label>
                        <textarea wire:model="formClosedMessage" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                        @error('formClosedMessage') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Chave template convite (opcional)</label>
                        <input wire:model="formInviteTemplateKey" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="ex.: villa_party">
                        <p class="text-[11px] text-gray-500 mt-1">View em mail/events/custom/{chave}.blade.php</p>
                        @error('formInviteTemplateKey') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-6 flex gap-2">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase">
                            Salvar
                        </button>
                        @if ($editingId)
                            <button type="button" wire:click="cancelEdit" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase">
                                Cancelar
                            </button>
                        @endif
                    </div>
                </form>
            </div>

            <livewire:events.mercado-pago-accounts-panel />

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Título</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Slug</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Status</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Início</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($events as $event)
                            <tr wire:key="event-{{ $event->id }}">
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $event->title }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $event->slug }}</td>
                                <td class="px-4 py-2">{{ $event->status->value }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $event->starts_at?->timezone($event->timezone)->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    <a href="{{ route('events.hub.show', $event) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold uppercase">Convidados</a>
                                    <button type="button" wire:click="startEdit('{{ $event->id }}')" class="text-gray-600 hover:text-gray-900 text-xs font-semibold uppercase">Editar</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">Nenhum evento ainda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
