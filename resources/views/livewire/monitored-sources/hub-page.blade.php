<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Fontes Monitoradas
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900">Operacao de monitoramento WhatsApp</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Gerencie fontes monitoradas, associe profiles de analise e acompanhe o estado do pipeline text-first.
                </p>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                @if (session('monitored_sources_hub_notice'))
                    <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-700">
                        {{ session('monitored_sources_hub_notice') }}
                    </div>
                @endif

                <div class="border-b border-gray-200 pb-4 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        {{ $editingId ? 'Editar fonte monitorada' : 'Nova fonte monitorada' }}
                    </h3>

                    <form wire:submit="saveSource" class="grid grid-cols-1 gap-3 md:grid-cols-12">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Kind</label>
                            <select wire:model="formKind" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="self">self</option>
                                <option value="contact">contact</option>
                                <option value="group">group</option>
                            </select>
                            @error('formKind') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Identifier</label>
                            <input wire:model="formIdentifier" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="5511999999999@s.whatsapp.net">
                            @error('formIdentifier') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Label</label>
                            <input wire:model="formLabel" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Grupo Comercial">
                            @error('formLabel') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Profile de análise</label>
                            <select wire:model="formProfileId" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">Sem profile</option>
                                @foreach ($profiles as $profile)
                                    <option value="{{ $profile->id }}">{{ $profile->name }} ({{ $profile->slug }})</option>
                                @endforeach
                            </select>
                            @error('formProfileId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-9">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Notes (opcional)</label>
                            <textarea wire:model="formNotes" rows="2" class="w-full rounded-md border-gray-300 text-sm" placeholder="Observações operacionais da fonte"></textarea>
                            @error('formNotes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-3 flex flex-wrap items-end gap-2">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input wire:model="formIsActive" type="checkbox" class="rounded border-gray-300 text-indigo-600">
                                Ativa
                            </label>
                            @error('formIsActive') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                {{ $editingId ? 'Salvar alterações' : 'Criar fonte' }}
                            </button>
                            @if ($editingId)
                                <button type="button" wire:click="cancelEdit" class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                                    Cancelar
                                </button>
                            @else
                                <button type="button" wire:click="startCreate" class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                                    Limpar
                                </button>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Monitored sources</h3>
                    <span class="text-sm text-gray-500">{{ $sources->count() }} cadastradas</span>
                </div>
                <p class="mb-3 text-xs text-gray-600">
                    <span class="font-medium text-gray-700">Estados do pipeline:</span>
                    <span class="ml-1">classified = processado</span>,
                    <span>pending_media_processing/pending_text_extraction = aguardando extração</span>,
                    <span>skipped_no_profile = sem profile configurado na source.</span>
                </p>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Fonte</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Kind</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Identifier</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Status</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Profile</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Notes</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Pipeline</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($sources as $source)
                                <tr>
                                    <td class="px-3 py-2 text-gray-900">{{ $source->label }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $source->kind }}</td>
                                    <td class="px-3 py-2 text-gray-700 break-all">{{ $source->identifier }}</td>
                                    <td class="px-3 py-2">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-700' => $source->is_active,
                                            'bg-gray-100 text-gray-600' => ! $source->is_active,
                                        ])>
                                            {{ $source->is_active ? 'Ativa' : 'Inativa' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <select
                                                wire:model.live="sourceProfileForms.{{ $source->id }}"
                                                class="rounded-md border-gray-300 text-xs"
                                            >
                                                <option value="">Sem profile</option>
                                                @foreach ($profiles as $profile)
                                                    <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                                                @endforeach
                                            </select>
                                            <button
                                                type="button"
                                                wire:click="saveSourceProfile({{ $source->id }})"
                                                class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                            >
                                                Salvar
                                            </button>
                                        </div>
                                        <div class="mt-1 text-[11px] text-gray-500">
                                            {{ $source->analysisProfile?->slug ?? 'sem-profile (gera skipped_no_profile)' }}
                                        </div>
                                        <div class="mt-1">
                                            @if ($source->analysis_profile_id === null)
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                                    sem profile
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-medium text-indigo-700">
                                                    profile explícito
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600 max-w-[14rem]">
                                        <div class="truncate" title="{{ $source->notes ?? '' }}">
                                            {{ $source->notes ?: '-' }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-700">
                                        <div class="mb-1">
                                            <span class="text-gray-500">Último:</span>
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                                                'bg-emerald-100 text-emerald-700' => $source->latest_ai_pipeline_status === 'classified',
                                                'bg-amber-100 text-amber-700' => in_array($source->latest_ai_pipeline_status, ['pending_media_processing', 'pending_text_extraction'], true),
                                                'bg-rose-100 text-rose-700' => $source->latest_ai_pipeline_status === 'skipped_no_profile',
                                                'bg-gray-100 text-gray-600' => ! in_array($source->latest_ai_pipeline_status, ['classified', 'pending_media_processing', 'pending_text_extraction', 'skipped_no_profile'], true),
                                            ])>
                                                {{ $source->latest_ai_pipeline_status ?? '-' }}
                                            </span>
                                        </div>
                                        <div class="text-gray-500">
                                            classificados {{ $source->classified_count }}
                                            · pendentes {{ $source->pending_count }}
                                            · skipped {{ $source->skipped_count }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                wire:click="startEdit({{ $source->id }})"
                                                class="inline-flex items-center rounded-md bg-indigo-100 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200"
                                            >
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="toggleSource({{ $source->id }})"
                                                class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                            >
                                                {{ $source->is_active ? 'Desativar' : 'Ativar' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-6 text-center text-gray-500">
                                        Nenhuma fonte monitorada cadastrada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Mensagens recentes</h3>
                    <span class="text-sm text-gray-500">Top 25</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Fonte</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Tipo</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Status IA</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Processado</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Corpo</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($recentLogs as $log)
                                <tr>
                                    <td class="px-3 py-2 text-gray-700">
                                        {{ $log->monitoredSource?->label ?? 'sem source' }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $log->message_type }}</td>
                                    <td class="px-3 py-2 text-gray-700">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                                            'bg-emerald-100 text-emerald-700' => $log->ai_pipeline_status === 'classified',
                                            'bg-amber-100 text-amber-700' => in_array($log->ai_pipeline_status, ['pending_media_processing', 'pending_text_extraction'], true),
                                            'bg-rose-100 text-rose-700' => $log->ai_pipeline_status === 'skipped_no_profile',
                                            'bg-gray-100 text-gray-600' => ! in_array($log->ai_pipeline_status, ['classified', 'pending_media_processing', 'pending_text_extraction', 'skipped_no_profile'], true),
                                        ])>
                                            {{ $log->ai_pipeline_status ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $log->is_processed ? 'Sim' : 'Nao' }}</td>
                                    <td class="px-3 py-2 text-gray-700 max-w-[24rem] truncate">
                                        {{ $log->body ?: 'sem texto' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <button
                                            type="button"
                                            wire:click="reprocessMessageLog({{ $log->id }})"
                                            @disabled($log->monitored_source_id === null)
                                            class="inline-flex items-center rounded-md px-2.5 py-1.5 text-xs font-medium {{ $log->monitored_source_id === null ? 'cursor-not-allowed bg-gray-100 text-gray-400' : 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200' }}"
                                        >
                                            Reprocessar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-gray-500">
                                        Sem mensagens para exibir.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
