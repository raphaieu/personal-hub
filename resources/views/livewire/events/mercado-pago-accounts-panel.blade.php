<div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6 space-y-4">
    @if (session('events_mp_notice'))
        <div class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-800">
            {{ session('events_mp_notice') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-lg font-semibold text-gray-900">Contas Mercado Pago</h3>
        <button type="button" wire:click="startCreateAccount" class="text-xs font-semibold text-indigo-600 uppercase">Nova conta</button>
    </div>

    <p class="text-xs text-gray-500">
        Webhook (cadastre no painel MP): <code class="bg-gray-100 px-1 rounded break-all">{{ $webhookUrl }}</code>
    </p>

    <form wire:submit="saveAccount" class="grid grid-cols-1 md:grid-cols-6 gap-3 border border-gray-100 rounded-lg p-3">
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Nome / rótulo</label>
            <input wire:model="formLabel" type="text" class="w-full rounded-md border-gray-300 text-sm">
            @error('formLabel') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Public Key</label>
            <input wire:model="formPublicKey" type="text" class="w-full rounded-md border-gray-300 text-sm">
            @error('formPublicKey') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Ambiente</label>
            <select wire:model="formEnvironment" class="w-full rounded-md border-gray-300 text-sm">
                <option value="sandbox">sandbox</option>
                <option value="production">production</option>
            </select>
            @error('formEnvironment') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">Access Token</label>
            <input wire:model="formAccessToken" type="password" class="w-full rounded-md border-gray-300 text-sm" placeholder="{{ $editingAccountId ? 'Deixe em branco para manter' : 'Obrigatório' }}">
            @error('formAccessToken') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="md:col-span-6 flex gap-2">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase">Salvar conta</button>
            @if ($editingAccountId)
                <button type="button" wire:click="startCreateAccount" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase">Cancelar</button>
            @endif
        </div>
    </form>

    @if ($connectionMessage)
        <p class="text-sm text-gray-700">{{ $connectionMessage }}</p>
    @endif

    <ul class="divide-y divide-gray-100 text-sm">
        @forelse ($accounts as $account)
            <li class="py-2 flex flex-wrap justify-between gap-2" wire:key="mp-{{ $account->id }}">
                <div>
                    <span class="font-medium text-gray-900">{{ $account->label }}</span>
                    <span class="text-xs text-gray-500 ml-1">({{ $account->environment->value }})</span>
                    <div class="text-xs text-gray-500 mt-1">{{ $account->public_key }}</div>
                </div>
                <div class="flex gap-2 shrink-0">
                    <button type="button" wire:click="testConnection('{{ $account->id }}')" class="text-xs font-semibold text-indigo-600 uppercase">Testar</button>
                    <button type="button" wire:click="startEditAccount('{{ $account->id }}')" class="text-xs font-semibold text-gray-600 uppercase">Editar</button>
                    <button type="button" wire:click="deleteAccount('{{ $account->id }}')" wire:confirm="Remover esta conta?" class="text-xs font-semibold text-red-600 uppercase">Excluir</button>
                </div>
            </li>
        @empty
            <li class="py-2 text-gray-500">Nenhuma conta cadastrada. Use sandbox para testes.</li>
        @endforelse
    </ul>
</div>
