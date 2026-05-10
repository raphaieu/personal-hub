<div>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $event->title }}
            </h2>
            <a href="{{ route('events.hub') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">← Voltar aos eventos</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('events_hub_notice'))
                <div class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-800">
                    {{ session('events_hub_notice') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <p class="text-sm text-gray-600 mb-2">
                    <span class="font-medium text-gray-800">Landing pública:</span>
                    <code class="text-xs bg-gray-100 px-1 rounded">{{ $publicRegistrationUrl }}</code>
                </p>
                <p class="text-xs text-gray-500">API: <code class="bg-gray-100 px-1 rounded">GET {{ url('/api/v1/events/'.$event->slug.'/config') }}</code></p>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Links de lista (referral)</h3>
                <form wire:submit="createReferralLink" class="flex flex-wrap gap-2 items-end">
                    <div class="grow min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nome da lista</label>
                        <input wire:model="referralFormName" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Lista do João">
                        @error('referralFormName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase">
                        Gerar link
                    </button>
                </form>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($referrals as $ref)
                        <li class="py-2 flex flex-wrap justify-between gap-2" wire:key="ref-{{ $ref->id }}">
                            <div>
                                <span class="font-medium text-gray-900">{{ $ref->name }}</span>
                                @if ($ref->revoked_at)
                                    <span class="text-xs text-red-600">revogado</span>
                                @endif
                                <div class="text-xs text-gray-500 break-all mt-1">{{ $publicRegistrationUrl }}?ref={{ $ref->token }}</div>
                            </div>
                            @if (! $ref->revoked_at)
                                <button type="button" wire:click="revokeReferral('{{ $ref->id }}')" class="text-xs font-semibold text-red-600 uppercase shrink-0">Revogar</button>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">Nenhum link. Crie um se o evento usar lista fechada (<code>requires_ref</code>).</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ $guestEditingId ? 'Editar convidado' : 'Novo convidado' }}
                </h3>
                <form wire:submit="saveGuest" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nome</label>
                        <input wire:model="guestFormName" type="text" class="w-full rounded-md border-gray-300 text-sm">
                        @error('guestFormName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">E-mail</label>
                        <input wire:model="guestFormEmail" type="email" class="w-full rounded-md border-gray-300 text-sm">
                        @error('guestFormEmail') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Telefone</label>
                        <input wire:model="guestFormPhone" type="text" class="w-full rounded-md border-gray-300 text-sm">
                        @error('guestFormPhone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select wire:model="guestFormStatus" class="w-full rounded-md border-gray-300 text-sm">
                            @foreach (\App\Enums\Events\GuestStatus::cases() as $gs)
                                <option value="{{ $gs->value }}">{{ $gs->value }}</option>
                            @endforeach
                        </select>
                        @error('guestFormStatus') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-6 flex gap-2">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase">
                            Salvar convidado
                        </button>
                        @if ($guestEditingId)
                            <button type="button" wire:click="startCreateGuest" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase">
                                Cancelar
                            </button>
                        @endif
                    </div>
                </form>

                <table class="min-w-full divide-y divide-gray-200 text-sm mt-6">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Nome</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">E-mail</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Status</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Convite</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($guests as $guest)
                            <tr wire:key="guest-{{ $guest->id }}">
                                <td class="px-3 py-2 font-medium text-gray-900">{{ $guest->name }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $guest->email }}</td>
                                <td class="px-3 py-2">{{ $guest->status->value }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $guest->invite_sent_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                                    <button type="button" wire:click="sendInvite('{{ $guest->id }}')" class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold uppercase">Enviar convite</button>
                                    <button type="button" wire:click="startEditGuest('{{ $guest->id }}')" class="text-gray-600 hover:text-gray-900 text-xs font-semibold uppercase">Editar</button>
                                    <button type="button" wire:click="deleteGuest('{{ $guest->id }}')" wire:confirm="Remover este convidado?" class="text-red-600 hover:text-red-800 text-xs font-semibold uppercase">Excluir</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-gray-500">Nenhum convidado cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
