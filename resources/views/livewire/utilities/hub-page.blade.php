<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Hub Utilidades
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                @if (session('utilities_hub_notice'))
                    <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-800">
                        {{ session('utilities_hub_notice') }}
                    </div>
                @endif

                <p class="text-sm text-gray-600 mb-4">
                    Gerencie contas Embasa/Coelba, veja faturas ingeridas e dispare scrape manual na fila
                    <code class="text-xs bg-gray-100 px-1 rounded">scraping</code>
                    (<span class="font-medium">ignore janela + force</span>).
                </p>

                <div class="border-b border-gray-200 pb-4 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        {{ $editingId ? 'Editar conta' : 'Nova conta' }}
                    </h3>
                    <form wire:submit="saveAccount" class="grid grid-cols-1 gap-3 md:grid-cols-6">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Concessionária</label>
                            <select wire:model="formKind" class="w-full rounded-md border-gray-300 text-sm" @disabled($editingId)>
                                <option value="embasa">Embasa</option>
                                <option value="coelba">Coelba</option>
                            </select>
                            @error('formKind') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            @if ($editingId)
                                <p class="text-[11px] text-gray-500 mt-1">Kind não pode ser alterado após criação.</p>
                            @endif
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Referência da conta</label>
                            <input wire:model="formAccountRef" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Matrícula / código cliente">
                            @error('formAccountRef') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Label (opcional)</label>
                            <input wire:model="formLabel" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ex.: Casa principal">
                            @error('formLabel') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Dia vencimento</label>
                            <input wire:model.number="formDueDay" type="number" min="1" max="31" class="w-full rounded-md border-gray-300 text-sm">
                            @error('formDueDay') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Lembrete (dias antes)</label>
                            <input wire:model.number="formReminderLeadDays" type="number" min="0" max="90" class="w-full rounded-md border-gray-300 text-sm">
                            @error('formReminderLeadDays') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-end gap-3 md:col-span-2">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input wire:model="formIsActive" type="checkbox" class="rounded border-gray-300 text-indigo-600">
                                Ativa
                            </label>
                        </div>
                        <div class="flex flex-wrap items-end gap-2 md:col-span-4">
                            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                {{ $editingId ? 'Salvar alterações' : 'Cadastrar' }}
                            </button>
                            @if ($editingId)
                                <button type="button" wire:click="cancelEdit" class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-200">
                                    Cancelar edição
                                </button>
                            @else
                                <button type="button" wire:click="startCreate" class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-200">
                                    Limpar formulário
                                </button>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Contas</h3>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kind</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ref.</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Venc.</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lead</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ativa</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Último scrape</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($accounts as $row)
                                    <tr @class(['bg-indigo-50/40' => (int) $selectedAccountId === (int) $row->id])>
                                        <td class="px-3 py-2 whitespace-nowrap font-medium text-gray-900">{{ $row->kind }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row->label ?? '—' }}</td>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-800">{{ $row->account_ref }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row->due_day }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row->reminder_lead_days }}</td>
                                        <td class="px-3 py-2">
                                            @if ($row->is_active)
                                                <span class="text-green-700 text-xs font-medium">Sim</span>
                                            @else
                                                <span class="text-gray-500 text-xs">Não</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-600">
                                            {{ $row->last_scraped_at ? $row->last_scraped_at->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap space-x-1">
                                            <button type="button" wire:click="selectAccount({{ (int) $row->id }})" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">
                                                Faturas
                                            </button>
                                            <button type="button" wire:click="startEdit({{ (int) $row->id }})" class="text-gray-700 hover:text-gray-900 text-xs font-medium">
                                                Editar
                                            </button>
                                            <button type="button" wire:click="toggleActive({{ (int) $row->id }})" class="text-gray-700 hover:text-gray-900 text-xs font-medium">
                                                Ativar/desativar
                                            </button>
                                            <button type="button" wire:click="scrapeNow({{ (int) $row->id }})" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">
                                                Scrape agora
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-3 py-6 text-center text-sm text-gray-500">Nenhuma conta cadastrada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($selectedAccount)
                    <div class="border-t border-gray-200 pt-6">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <h3 class="text-lg font-semibold text-gray-900">
                                Faturas — {{ $selectedAccount->label ?? $selectedAccount->account_ref }}
                                <span class="text-sm font-normal text-gray-500">({{ $selectedAccount->kind }})</span>
                            </h3>
                            <button type="button" wire:click="selectAccount(null)" class="text-sm text-gray-600 hover:text-gray-900 underline">
                                Fechar painel
                            </button>
                        </div>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referência</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimento</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">PDF</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse ($invoices as $inv)
                                        <tr wire:key="inv-{{ $inv->id }}">
                                            <td class="px-3 py-2 font-mono text-xs text-gray-900">{{ $inv->billing_reference }}</td>
                                            <td class="px-3 py-2 text-gray-700">
                                                {{ $inv->due_date ? $inv->due_date->format('d/m/Y') : '—' }}
                                            </td>
                                            <td class="px-3 py-2 text-gray-800">
                                                @if ($inv->amount_total !== null)
                                                    R$ {{ number_format((float) $inv->amount_total, 2, ',', '.') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-gray-800">{{ $inv->status }}</td>
                                            <td class="px-3 py-2 text-right">
                                                @if (!empty($invoicePdfOk[$inv->id]))
                                                    <a href="{{ route('utilities.invoice.pdf', $inv) }}" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">
                                                        Baixar PDF
                                                    </a>
                                                @elseif ($inv->pdf_path)
                                                    <span class="text-xs text-amber-700" title="{{ $inv->pdf_path }}">Arquivo indisponível no disco</span>
                                                @else
                                                    <span class="text-xs text-gray-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">Nenhuma fatura para esta conta.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($invoices instanceof \Illuminate\Contracts\Pagination\Paginator && $invoices->hasPages())
                            <div class="mt-4">
                                {{ $invoices->links() }}
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
