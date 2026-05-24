@extends('layouts.events-confirm')

@section('title', 'Pagamento — Events')

@push('head')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@section('content')
<div
    x-data="paymentReturn({
        token: @js($token),
        statusUrl: @js(route('events.payment.status', ['token' => $token])),
        pollIntervalMs: @js($pollIntervalMs),
        reservationMinutes: @js($reservationMinutes),
        initial: @js($initialPayload),
    })"
    x-init="init()"
>
    <template x-if="state === 'processing'">
        <div>
            <div class="icon-wrap">
                <div class="spinner" aria-hidden="true"></div>
            </div>
            <h1>Aguardando confirmação do pagamento</h1>
            <p>
                Se o pagamento não for confirmado em
                <strong x-text="countdownLabel"></strong>,
                seu cadastro será cancelado e será necessário inscrever-se novamente na página do evento.
            </p>
            <p style="margin-top: 0.75rem; font-size: 0.85rem; color: #a8a4b8;">
                Você pode fechar esta janela. Se o pagamento for aprovado, enviaremos o ingresso para
                <strong x-text="guestEmail || 'seu e-mail'"></strong>.
            </p>
        </div>
    </template>

    <template x-if="state === 'success'">
        <div>
            <div class="icon-wrap">
                <svg class="icon-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <h1>Pagamento confirmado!</h1>
            <p>
                Obrigado, <strong x-text="guestName"></strong>!<br>
                Sua presença em <strong x-text="eventTitle"></strong> está confirmada.
            </p>
            <p style="margin-top: 0.75rem; font-size: 0.85rem;">
                Enviamos o ingresso em PDF para <strong x-text="guestEmail"></strong>.<br>
                Verifique sua caixa de entrada (e a pasta de spam).
            </p>
            <p style="margin-top: 0.75rem; font-size: 0.85rem; color: #a8a4b8;">
                Você pode fechar esta página.
            </p>
        </div>
    </template>

    <template x-if="state === 'failed'">
        <div>
            <div class="icon-wrap">
                <svg class="icon-error" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>
            <h1>Pagamento não aprovado</h1>
            <p>
                Seu cadastro não foi mantido. Para participar do evento, faça uma nova inscrição na página do evento.
            </p>
        </div>
    </template>

    <template x-if="state === 'expired'">
        <div>
            <div class="icon-wrap">
                <svg class="icon-error" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <h1>Tempo esgotado</h1>
            <p>
                O prazo de <span x-text="reservationMinutes"></span> minutos terminou sem confirmação do pagamento.
                Refaça o cadastro e a compra na página do evento.
            </p>
        </div>
    </template>

    <div style="margin-top: 1.5rem; text-align: center;">
        <a
            x-show="eventPageUrl"
            :href="eventPageUrl"
            class="btn"
            style="display: inline-block;"
        >
            Voltar para a página do evento
        </a>
    </div>
</div>

<style>
    .spinner {
        width: 48px;
        height: 48px;
        border: 3px solid rgba(108, 92, 231, 0.2);
        border-top-color: #6c5ce7;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .icon-error { width: 48px; height: 48px; color: #e17055; margin: 0 auto; display: block; }
</style>

<script>
function paymentReturn(config) {
    return {
        token: config.token,
        statusUrl: config.statusUrl,
        pollIntervalMs: config.pollIntervalMs,
        reservationMinutes: config.reservationMinutes,
        state: 'processing',
        secondsRemaining: config.initial.secondsRemaining ?? 0,
        eventPageUrl: config.initial.eventPageUrl ?? null,
        eventTitle: config.initial.event?.title ?? '',
        guestName: config.initial.guestName ?? '',
        guestEmail: config.initial.guestEmail ?? '',
        pollTimer: null,
        tickTimer: null,

        get countdownLabel() {
            const s = Math.max(0, this.secondsRemaining);
            const m = Math.floor(s / 60);
            const r = s % 60;
            return `${String(m).padStart(2, '0')}:${String(r).padStart(2, '0')}`;
        },

        init() {
            this.applyPayload(config.initial);
            this.tickTimer = setInterval(() => {
                if (this.secondsRemaining > 0) {
                    this.secondsRemaining -= 1;
                }
            }, 1000);
            this.poll();
            this.pollTimer = setInterval(() => this.poll(), this.pollIntervalMs);
        },

        applyPayload(payload) {
            if (!payload) return;
            this.secondsRemaining = payload.secondsRemaining ?? 0;
            this.eventPageUrl = payload.eventPageUrl ?? this.eventPageUrl;
            this.eventTitle = payload.event?.title ?? this.eventTitle;
            this.guestName = payload.guestName ?? this.guestName;
            this.guestEmail = payload.guestEmail ?? this.guestEmail;

            const status = payload.status;
            if (status === 'confirmed') {
                this.state = 'success';
                this.stopPolling();
            } else if (status === 'rejected' || status === 'failed') {
                this.state = 'failed';
                this.stopPolling();
            } else if (status === 'expired' || status === 'not_found') {
                this.state = 'expired';
                this.stopPolling();
            } else {
                this.state = 'processing';
            }
        },

        async poll() {
            try {
                const response = await fetch(this.statusUrl, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) return;
                const payload = await response.json();
                this.applyPayload(payload);
            } catch (e) {
                /* silencioso — próximo poll */
            }
        },

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
            if (this.tickTimer) {
                clearInterval(this.tickTimer);
                this.tickTimer = null;
            }
        },
    };
}
</script>
@endsection
