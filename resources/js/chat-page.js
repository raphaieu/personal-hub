/**
 * Painel do chat IA (Alpine): x-data="chatPage(cfg)" via window.chatPage.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function parseJsonSafe(response) {
    try {
        return await response.json();
    } catch {
        return {};
    }
}

window.chatPage = function chatPage(cfg) {
    return {
        urls: cfg || {},
        providersPayload: [],
        transcription: {},
        imageGeneration: {},
        defaults: {},
        selectedProviderId: '',
        selectedModelId: '',
        pendingImages: [],
        draft: '',
        systemDraft: '',
        loading: false,
        error: '',
        recording: false,
        mediaRecorder: null,
        mediaStream: null,
        audioChunks: [],
        imagePrompt: '',
        generatingImage: false,

        messages: [],

        get providerList() {
            return this.providersPayload || [];
        },

        get selectedProvider() {
            return this.providerList.find((p) => p.id === this.selectedProviderId) ?? null;
        },

        get modelOptions() {
            return this.selectedProvider?.models ?? [];
        },

        get selectedModelMeta() {
            const m = this.modelOptions.find((x) => x.id === this.selectedModelId);
            return m ?? null;
        },

        get canVision() {
            return !!(this.selectedModelMeta?.vision);
        },

        get canTranscribeOpenAi() {
            return !!this.transcription?.openai;
        },

        get canTranscribeGroq() {
            return !!this.transcription?.groq;
        },

        get canGenerateImage() {
            return !!this.imageGeneration?.openai;
        },

        async init() {
            await this.loadOptions();
            if (this.providerList.length === 0) {
                this.error =
                    'Nenhum provedor configurado (defina chaves Groq / Anthropic / OpenAI ou Ollama no .env).';
            }
        },

        async loadOptions() {
            this.error = '';
            try {
                const res = await fetch(this.urls.optionsUrl || '', {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const body = await parseJsonSafe(res);
                if (!res.ok) {
                    throw new Error(body.message || `HTTP ${res.status}`);
                }
                this.providersPayload = body.providers ?? [];
                this.transcription = body.transcription ?? {};
                this.imageGeneration = body.image_generation ?? {};
                this.defaults = body.defaults ?? {};

                const first = this.providerList[0];
                if (first) {
                    this.selectedProviderId = first.id;
                    const fm = first.models?.[0];
                    this.selectedModelId = fm?.id ?? '';
                }
            } catch (e) {
                this.error = e instanceof Error ? e.message : String(e);
            }
        },

        onProviderChange() {
            const p = this.selectedProvider;
            const fm = p?.models?.[0];
            this.selectedModelId = fm?.id ?? '';
            this.pendingImages = [];
        },

        async send() {
            const text = (this.draft || '').trim();
            const hasImages = this.pendingImages.length > 0;

            if (!text && !hasImages) {
                return;
            }
            if (!this.selectedProviderId || !this.selectedModelId) {
                this.error = 'Escolha provedor e modelo.';
                return;
            }
            if (hasImages && !this.canVision) {
                this.error = 'Este modelo não aceita imagens.';
                return;
            }

            const userBubble = {
                role: 'user',
                content: text,
                images: this.pendingImages.map((img) => ({ preview: img.preview })),
            };
            this.messages.push(userBubble);

            const payload = {
                provider: this.selectedProviderId,
                model: this.selectedModelId,
                message: text,
                system: this.systemDraft || null,
                images:
                    hasImages ?
                        this.pendingImages.map((img) => ({
                            data: img.base64,
                            mime: img.mime,
                        }))
                    :   null,
            };

            this.draft = '';
            this.pendingImages = [];
            this.loading = true;
            this.error = '';

            try {
                const res = await fetch(this.urls.chatUrl || '', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
                const body = await parseJsonSafe(res);

                if (!body.ok) {
                    const errText = body.error || body.error_type || `HTTP ${res.status}`;
                    this.messages.push({ role: 'assistant', content: `Erro: ${errText}`, error: true });
                    return;
                }

                this.messages.push({
                    role: 'assistant',
                    content: body.content || '',
                    meta: {
                        provider: body.provider,
                        model: body.model,
                        latency_ms: body.latency_ms,
                    },
                });
            } catch (e) {
                this.messages.push({
                    role: 'assistant',
                    content: `Erro de rede: ${e instanceof Error ? e.message : String(e)}`,
                    error: true,
                });
            } finally {
                this.loading = false;
            }
        },

        onPickImages(event) {
            const files = event.target.files;
            if (!files?.length) {
                return;
            }
            const maxKb = 2048;
            const maxFiles = 4;

            Array.from(files).forEach((file) => {
                if (this.pendingImages.length >= maxFiles) {
                    return;
                }
                if (!file.type.startsWith('image/')) {
                    return;
                }
                const reader = new FileReader();
                reader.onload = () => {
                    const result = reader.result;
                    if (typeof result !== 'string') {
                        return;
                    }
                    const comma = result.indexOf(',');
                    const base64 = comma >= 0 ? result.slice(comma + 1) : result;
                    const approxKb = (base64.length * 3) / 4 / 1024;
                    if (approxKb > maxKb) {
                        this.error = `Imagem acima de ${maxKb} KiB ignorada.`;
                        return;
                    }
                    this.pendingImages.push({
                        mime: file.type,
                        base64,
                        preview: URL.createObjectURL(file),
                    });
                };
                reader.readAsDataURL(file);
            });
            event.target.value = '';
        },

        removePendingImage(index) {
            const img = this.pendingImages[index];
            if (img?.preview) {
                URL.revokeObjectURL(img.preview);
            }
            this.pendingImages.splice(index, 1);
        },

        async transcribe(engine) {
            if (!this.urls.transcribeUrl) {
                return;
            }

            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'audio/*';
            input.onchange = async () => {
                const file = input.files?.[0];
                if (!file) {
                    return;
                }

                const fd = new FormData();
                fd.append('audio', file);
                fd.append('engine', engine);

                this.loading = true;
                this.error = '';

                try {
                    const res = await fetch(this.urls.transcribeUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: fd,
                    });
                    const body = await parseJsonSafe(res);
                    if (!body.ok) {
                        this.error = body.error || 'Falha na transcrição.';
                        return;
                    }
                    const t = body.text || '';
                    this.draft = this.draft ? `${this.draft.trimEnd()}\n${t}` : t;
                } catch (e) {
                    this.error = e instanceof Error ? e.message : String(e);
                } finally {
                    this.loading = false;
                }
            };
            input.click();
        },

        toggleRecord() {
            if (this.recording) {
                this.stopRecord();
            } else {
                this.startRecord();
            }
        },

        async startRecord() {
            if (!navigator.mediaDevices?.getUserMedia) {
                this.error = 'Microfone não suportado neste navegador.';
                return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.mediaStream = stream;
                this.audioChunks = [];
                const mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : 'audio/mp4';
                this.mediaRecorder = new MediaRecorder(stream, { mimeType: mime });
                this.mediaRecorder.ondataavailable = (ev) => {
                    if (ev.data.size > 0) {
                        this.audioChunks.push(ev.data);
                    }
                };
                this.mediaRecorder.addEventListener(
                    'stop',
                    () => {
                        stream.getTracks().forEach((t) => t.stop());
                        this.mediaStream = null;
                    },
                    { once: true }
                );
                this.mediaRecorder.start();
                this.recording = true;
            } catch (e) {
                this.error = e instanceof Error ? e.message : String(e);
            }
        },

        async stopRecord() {
            if (!this.mediaRecorder) {
                this.recording = false;
                return;
            }
            const mr = this.mediaRecorder;
            const mimeType = mr.mimeType || 'audio/webm';

            await new Promise((resolve) => {
                mr.addEventListener('stop', () => resolve(), { once: true });
                if (mr.state !== 'inactive') {
                    mr.stop();
                } else {
                    resolve();
                }
            });
            this.recording = false;

            const blob = new Blob(this.audioChunks, { type: mimeType });
            const engine = this.canTranscribeGroq ? 'groq' : 'openai';
            if (!this.canTranscribeGroq && !this.canTranscribeOpenAi) {
                this.error = 'Nenhum motor de transcrição disponível.';
                return;
            }

            const fd = new FormData();
            fd.append(
                'audio',
                blob,
                mimeType.includes('webm') ? 'record.webm' : 'record.m4a'
            );
            fd.append('engine', engine);

            this.loading = true;
            try {
                const res = await fetch(this.urls.transcribeUrl || '', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: fd,
                });
                const body = await parseJsonSafe(res);
                if (!body.ok) {
                    this.error = body.error || 'Falha na transcrição.';
                    return;
                }
                const t = body.text || '';
                this.draft = this.draft ? `${this.draft.trimEnd()}\n${t}` : t;
            } catch (e) {
                this.error = e instanceof Error ? e.message : String(e);
            } finally {
                this.loading = false;
                this.mediaRecorder = null;
                this.audioChunks = [];
                this.mediaStream = null;
            }
        },

        async generateImage() {
            const prompt = (this.imagePrompt || '').trim();
            if (!prompt || !this.urls.imagesUrl) {
                return;
            }

            this.generatingImage = true;
            this.error = '';

            try {
                const res = await fetch(this.urls.imagesUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        prompt,
                        size: this.defaults?.openai_image_size || '1024x1024',
                        response_format: 'url',
                    }),
                });
                const body = await parseJsonSafe(res);
                if (!body.ok) {
                    this.error = body.error || 'Falha ao gerar imagem.';
                    return;
                }

                const url = body.url || null;
                const b64 = body.b64_json || null;

                if (url) {
                    this.messages.push({
                        role: 'assistant',
                        content: '',
                        imageUrl: url,
                        meta: { kind: 'image_gen', prompt },
                    });
                } else if (b64) {
                    this.messages.push({
                        role: 'assistant',
                        content: '',
                        imageUrl: `data:image/png;base64,${b64}`,
                        meta: { kind: 'image_gen', prompt },
                    });
                }

                this.imagePrompt = '';
            } catch (e) {
                this.error = e instanceof Error ? e.message : String(e);
            } finally {
                this.generatingImage = false;
            }
        },
    };
};
