<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Chat IA') }}
        </h2>
    </x-slot>

    <div
        class="py-8"
        x-data='chatPage(@json($chatConfig))'
    >
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100 overflow-hidden">
                <div class="border-b border-gray-100 px-4 py-3 flex flex-wrap gap-3 items-end bg-gray-50">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-500">{{ __('Provedor') }}</label>
                        <select
                            x-model="selectedProviderId"
                            @change="onProviderChange()"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm min-w-[10rem]"
                        >
                            <template x-for="p in providerList" :key="p.id">
                                <option :value="p.id" x-text="p.label"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-500">{{ __('Modelo') }}</label>
                        <select
                            x-model="selectedModelId"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm min-w-[14rem]"
                        >
                            <template x-for="m in modelOptions" :key="m.id">
                                <option :value="m.id" x-text="m.label || m.id"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2 ms-auto items-center">
                        <label
                            class="inline-flex items-center gap-1 text-xs text-gray-600 cursor-pointer"
                            :class="{ 'opacity-40 cursor-not-allowed pointer-events-none': !canVision }"
                        >
                            <input type="file" accept="image/*" multiple class="hidden" @change="onPickImages($event)" :disabled="!canVision" />
                            <span class="inline-flex items-center px-2 py-1 rounded border border-gray-200 bg-white text-xs">
                                {{ __('Anexar imagem') }}
                            </span>
                        </label>
                        <button
                            type="button"
                            class="inline-flex items-center px-2 py-1 rounded border border-gray-200 bg-white text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-40"
                            @click="toggleRecord()"
                            :disabled="(!canTranscribeOpenAi && !canTranscribeGroq) || loading"
                            x-text="recording ? '{{ __('Parar') }}' : '{{ __('Gravar áudio') }}'"
                        ></button>
                        <button
                            type="button"
                            class="inline-flex items-center px-2 py-1 rounded border border-gray-200 bg-white text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-40"
                            @click="transcribe(canTranscribeGroq ? 'groq' : 'openai')"
                            :disabled="!(canTranscribeOpenAi || canTranscribeGroq) || loading"
                        >
                            {{ __('Enviar arquivo de áudio') }}
                        </button>
                    </div>
                </div>

                <template x-if="pendingImages.length">
                    <div class="px-4 py-2 flex flex-wrap gap-2 border-b border-gray-100 bg-white">
                        <template x-for="(img, idx) in pendingImages" :key="idx">
                            <div class="relative">
                                <img :src="img.preview" alt="" class="h-16 w-16 object-cover rounded border border-gray-200" />
                                <button
                                    type="button"
                                    class="absolute -top-1 -right-1 bg-gray-900 text-white rounded-full w-5 h-5 text-xs leading-5"
                                    @click="removePendingImage(idx)"
                                >&times;</button>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="h-[28rem] overflow-y-auto px-4 py-3 space-y-3 bg-white" id="chat-scroll">
                    <template x-if="messages.length === 0">
                        <p class="text-sm text-gray-400 text-center py-12">{{ __('Envie uma mensagem para começar.') }}</p>
                    </template>
                    <template x-for="(msg, idx) in messages" :key="idx">
                        <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div
                                class="max-w-[85%] rounded-lg px-3 py-2 text-sm whitespace-pre-wrap break-words shadow-sm"
                                :class="msg.role === 'user'
                                    ? 'bg-indigo-600 text-white'
                                    : (msg.error ? 'bg-red-50 text-red-900 border border-red-100' : 'bg-gray-100 text-gray-900 border border-gray-200')"
                            >
                                <template x-if="msg.imageUrl">
                                    <img :src="msg.imageUrl" alt="" class="max-w-full rounded mb-2 border border-gray-200" />
                                </template>
                                <template x-if="msg.images?.length">
                                    <div class="flex flex-wrap gap-1 mb-2">
                                        <template x-for="(im, j) in msg.images" :key="j">
                                            <img :src="im.preview" alt="" class="h-20 w-20 object-cover rounded border border-white/30" />
                                        </template>
                                    </div>
                                </template>
                                <div x-text="msg.content"></div>
                                <template x-if="msg.meta && !msg.error">
                                    <div class="mt-1 text-[10px] opacity-70" x-text="msg.meta.provider + ' · ' + msg.meta.model + (msg.meta.latency_ms ? ' · ' + msg.meta.latency_ms + ' ms' : '')"></div>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="loading">
                        <p class="text-xs text-gray-400">{{ __('Aguardando resposta…') }}</p>
                    </template>
                </div>

                <div class="border-t border-gray-100 px-4 py-3 space-y-2 bg-gray-50">
                    <p x-show="error" x-text="error" class="text-xs text-red-600"></p>
                    <details class="group">
                        <summary class="text-xs text-gray-500 cursor-pointer list-none flex items-center gap-1">
                            <span class="group-open:rotate-90 transition">▸</span> {{ __('Instruções do sistema (opcional)') }}
                        </summary>
                        <textarea
                            x-model="systemDraft"
                            rows="2"
                            class="mt-2 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="{{ __('Ex.: Responda sempre em pt-BR.') }}"
                        ></textarea>
                    </details>

                    <template x-if="canGenerateImage">
                        <div class="flex flex-wrap gap-2 items-center">
                            <input
                                type="text"
                                x-model="imagePrompt"
                                class="flex-1 min-w-[12rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="{{ __('Gerar imagem (DALL·E) — descreva a cena…') }}"
                            />
                            <button
                                type="button"
                                @click="generateImage()"
                                class="inline-flex items-center px-3 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 disabled:opacity-40"
                                :disabled="generatingImage || loading"
                            >
                                {{ __('Gerar imagem') }}
                            </button>
                        </div>
                    </template>

                    <div class="flex gap-2">
                        <textarea
                            x-model="draft"
                            rows="3"
                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="{{ __('Digite sua mensagem…') }}"
                            @keydown.enter.prevent="if (!$event.shiftKey) send()"
                        ></textarea>
                        <button
                            type="button"
                            @click="send()"
                            class="shrink-0 inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-40"
                            :disabled="loading"
                        >
                            {{ __('Enviar') }}
                        </button>
                    </div>
                    <p class="text-[11px] text-gray-400">{{ __('Shift+Enter nova linha. Um único provedor/modelo por envio — sem fallback automático.') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
