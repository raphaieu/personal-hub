<div>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('analyses.hub') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-800">← Análises</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Análise #{{ $message->id }}</h2>
        </div>
    </x-slot>

    @php
        $showingPreviousResult = ($reprocessQueued || in_array($message->ai_pipeline_status, [null, ''], true))
            && $detail['analysis'] !== [];
        $processingLabel = match ($message->ai_pipeline_status) {
            'classified' => 'Classificado',
            'pending_media_processing' => 'Aguardando mídia',
            'pending_text_extraction' => 'Aguardando extração de texto',
            'skipped_no_profile' => 'Sem perfil',
            null, '' => 'Aguardando processamento',
            default => $message->ai_pipeline_status,
        };
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if ($notice)
                <div class="mx-4 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800 sm:mx-0" role="status">
                    {{ $notice }}
                    @if ($showingPreviousResult)
                        <span class="block mt-1">A análise exibida abaixo é o resultado anterior armazenado e não representa a nova execução.</span>
                    @endif
                </div>
            @endif

            <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                'bg-blue-100 text-blue-700' => $reprocessQueued,
                                'bg-emerald-100 text-emerald-700' => ! $reprocessQueued && $message->ai_pipeline_status === 'classified',
                                'bg-amber-100 text-amber-700' => ! $reprocessQueued && in_array($message->ai_pipeline_status, ['pending_media_processing', 'pending_text_extraction'], true),
                                'bg-violet-100 text-violet-700' => ! $reprocessQueued && $message->ai_pipeline_status === 'skipped_no_profile',
                                'bg-gray-100 text-gray-600' => ! $reprocessQueued && ! in_array($message->ai_pipeline_status, ['classified', 'pending_media_processing', 'pending_text_extraction', 'skipped_no_profile'], true),
                            ])>{{ $reprocessQueued ? 'Aguardando reprocessamento' : $processingLabel }}</span>
                            @if ($detail['category'])
                                <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">{{ $showingPreviousResult ? 'Resultado anterior:' : 'Resultado:' }} {{ $detail['category'] }}</span>
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Processamento e resultado são estados independentes; “Classificado” não significa aprovação editorial.</p>
                    </div>

                    <div class="max-w-xl rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        <p class="font-medium">Reprocessar usará o perfil atualmente vinculado à fonte e substituirá a análise anterior.</p>
                        <p class="mt-1">
                            Perfil atual:
                            <strong>{{ $reprocessing['profile'] ? $reprocessing['profile']->name.' ('.$reprocessing['profile']->slug.')' : 'indisponível' }}</strong>
                        </p>
                        @if ($reprocessing['allowed'])
                            <button
                                type="button"
                                wire:click="reprocess"
                                wire:confirm="Reprocessar usará o perfil atualmente vinculado à fonte e substituirá a análise anterior. Continuar?"
                                wire:loading.attr="disabled"
                                wire:target="reprocess"
                                @disabled($reprocessQueued)
                                class="mt-3 inline-flex rounded-md bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="reprocess">{{ $reprocessQueued ? 'Reprocessamento enfileirado' : 'Reprocessar análise' }}</span>
                                <span wire:loading wire:target="reprocess">Enfileirando…</span>
                            </button>
                        @else
                            <p class="mt-2 text-xs font-medium text-amber-800">{{ $reprocessing['reason'] }}</p>
                        @endif
                    </div>
                </div>

                <dl class="mt-6 grid grid-cols-2 gap-4 text-sm md:grid-cols-3 lg:grid-cols-6">
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">ID</dt><dd class="mt-1 text-gray-900">{{ $message->id }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Fonte</dt><dd class="mt-1 text-gray-900">{{ $message->monitoredSource?->label ?? 'Sem fonte monitorada' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Data/hora</dt><dd class="mt-1 text-gray-900">{{ $message->created_at?->format('d/m/Y H:i:s') }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Tipo</dt><dd class="mt-1 text-gray-900">{{ $message->message_type ?: 'Não informado' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Perfil utilizado</dt><dd class="mt-1 break-words text-gray-900">{{ $detail['profile_slug'] ?? 'Não registrado' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Score normalizado</dt><dd class="mt-1 text-gray-900">{{ is_numeric($detail['normalized_score']) ? number_format((float) $detail['normalized_score'], 2, ',', '.').' / 100' : 'Não informado' }}</dd></div>
                </dl>
            </section>

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <article class="min-w-0 bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Mensagem original</h3>
                    <div class="mt-4 min-h-48 whitespace-pre-wrap break-words rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-800">{{ $message->body ?? 'Sem conteúdo textual.' }}</div>
                </article>

                <article class="min-w-0 bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $showingPreviousResult ? 'Resultado anterior armazenado' : 'Resultado estruturado da IA' }}</h3>
                    <dl class="mt-4 space-y-4 text-sm">
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Categoria</dt><dd class="mt-1 whitespace-pre-wrap break-words text-gray-900">{{ $detail['category'] ?? 'Não informada' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Resumo</dt><dd class="mt-1 whitespace-pre-wrap break-words text-gray-900">{{ $detail['summary'] ?? 'Não informado' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Confiança declarada pelo modelo</dt><dd class="mt-1 whitespace-pre-wrap break-words text-gray-900">{{ $detail['confidence'] ?? 'Não informada' }}</dd></div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Labels</dt>
                            <dd class="mt-2 flex flex-wrap gap-2">
                                @forelse ($detail['labels'] as $label)
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700">{{ $label }}</span>
                                @empty
                                    <span class="text-gray-500">Nenhuma label informada</span>
                                @endforelse
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-6 border-t border-gray-200 pt-4">
                        <h4 class="text-sm font-semibold text-gray-900">Campos estruturados</h4>
                        <dl class="mt-3 space-y-3">
                            @forelse ($detail['structured_fields'] as $field)
                                <div>
                                    <dt class="font-mono text-xs text-gray-500">{{ $field['name'] }}</dt>
                                    @if ($field['multiline'])
                                        <dd class="mt-1 overflow-x-auto whitespace-pre-wrap break-words rounded bg-gray-50 p-2 font-mono text-xs text-gray-800">{{ $field['value'] }}</dd>
                                    @else
                                        <dd class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-800">{{ $field['value'] }}</dd>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Nenhum campo estruturado disponível.</p>
                            @endforelse
                        </dl>
                    </div>
                </article>
            </section>

            <section class="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="text-lg font-semibold text-gray-900">Ofertas extraídas</h3>
                    <p class="text-xs text-gray-500">Conteúdo declarado pela IA; loja, disponibilidade, preço e cupom não foram verificados.</p>
                </div>

                @if ($detail['offers_state'] === 'valid')
                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @foreach ($detail['offers'] as $index => $offer)
                            <article class="rounded-lg border border-gray-200 p-4">
                                <h4 class="font-semibold text-gray-900">Oferta {{ $index + 1 }}</h4>
                                <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                    @foreach ($offer['fields'] as $name => $field)
                                        <div @class(['sm:col-span-2' => in_array($name, ['title', 'conditions', 'validity_text'], true)])>
                                            <dt class="font-mono text-xs text-gray-500">{{ $name }}</dt>
                                            @if ($field['multiline'])
                                                <dd class="mt-1 whitespace-pre-wrap break-words rounded bg-gray-50 p-2 font-mono text-xs text-gray-800">{{ $field['value'] }}</dd>
                                            @else
                                                <dd class="mt-1 whitespace-pre-wrap break-words text-gray-900">{{ $field['value'] }}</dd>
                                            @endif
                                        </div>
                                    @endforeach
                                    <div class="sm:col-span-2">
                                        <dt class="font-mono text-xs text-gray-500">urls</dt>
                                        <dd class="mt-1 space-y-1">
                                            @forelse ($offer['urls'] as $url)
                                                @if ($url['safe_url'])
                                                    <a href="{{ $url['safe_url'] }}" target="_blank" rel="noopener noreferrer" class="block break-all text-indigo-600 underline hover:text-indigo-800">{{ $url['value'] }}</a>
                                                @else
                                                    <span class="block whitespace-pre-wrap break-all text-gray-700">{{ $url['value'] }}</span>
                                                @endif
                                            @empty
                                                <span class="text-gray-500">Ausente</span>
                                            @endforelse
                                        </dd>
                                    </div>
                                </dl>
                            </article>
                        @endforeach
                    </div>
                @elseif ($detail['offers_state'] === 'empty')
                    <p class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-600">A análise retornou uma lista de ofertas vazia. Isso não é um erro de processamento.</p>
                @elseif ($detail['offers_state'] === 'invalid')
                    <p class="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">O campo <code>offers</code> existe, mas não é uma lista válida de objetos. O valor original permanece disponível no JSON técnico abaixo.</p>
                @else
                    <p class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-600">Este perfil não retornou o formato <code>offers</code>. Os campos genéricos e o JSON completo continuam disponíveis.</p>
                @endif
            </section>

            <details class="group bg-white shadow-sm sm:rounded-lg">
                <summary class="cursor-pointer list-none px-4 py-4 font-semibold text-gray-900 sm:px-6">
                    Dados técnicos
                    <span class="ml-2 text-xs font-normal text-gray-500 group-open:hidden">Expandir</span>
                </summary>
                <div class="border-t border-gray-200 p-4 sm:p-6">
                    <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Provedor</dt><dd class="mt-1 break-words text-gray-900">{{ $detail['provider'] ?? 'Não informado' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Modelo</dt><dd class="mt-1 break-words text-gray-900">{{ $detail['model'] ?? 'Não informado' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Latência</dt><dd class="mt-1 text-gray-900">{{ $detail['latency_ms'] !== null ? $detail['latency_ms'].' ms' : 'Não informada' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Fallback</dt><dd class="mt-1 text-gray-900">{{ $detail['fallback_present'] ? $detail['fallback_value'] : 'Não informado' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Profile ID persistido</dt><dd class="mt-1 break-words text-gray-900">{{ $detail['profile_id'] ?? 'Não registrado' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Profile slug persistido</dt><dd class="mt-1 break-words text-gray-900">{{ $detail['profile_slug'] ?? 'Não registrado' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Status da análise</dt><dd class="mt-1 break-words text-gray-900">{{ $detail['analysis_status'] ?? 'Não informado' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Score original (raw_normalized)</dt><dd class="mt-1 break-words text-gray-900">{{ $detail['raw_score'] !== null ? $detail['raw_score'] : 'Não informado' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Score normalizado (metadata.analysis)</dt><dd class="mt-1 break-words text-gray-900">{{ $detail['normalized_score'] !== null ? $detail['normalized_score'].' / 100' : 'Não informado' }}</dd></div>
                    </dl>

                    <div class="mt-6" x-data="{ copied: false }">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold text-gray-900">JSON completo da análise</h4>
                            <button type="button" x-on:click="navigator.clipboard.writeText($refs.analysisJson.textContent).then(() => { copied = true; setTimeout(() => copied = false, 1500) })" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                                <span x-show="!copied">Copiar JSON</span>
                                <span x-show="copied" x-cloak>Copiado</span>
                            </button>
                        </div>
                        <pre x-ref="analysisJson" class="max-h-[36rem] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-gray-950 p-4 text-xs leading-relaxed text-gray-100">{{ $detail['analysis_json'] }}</pre>
                    </div>
                </div>
            </details>
        </div>
    </div>
</div>
