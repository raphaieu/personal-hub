# SPEC — Eventos privados

Hub `/hub/events`, API pública para landing externa, fluxo de dupla confirmação por e-mail, ingresso PDF com QR (SVG), portaria mobile-first com leitor de QR pela câmera.

Esta SPEC é a entrada canônica do módulo. [BRIEFING.md](BRIEFING.md) mantém a visão longa de produto e ADRs históricas; [API_Contract.md](API_Contract.md) detalha o contrato HTTP linha-a-linha.

> **Plataforma self-service (M0+):** o módulo virou backend SaaS multi-tenant — qualquer
> usuário registrado via API cria e publica suas landing pages (tema/conteúdo por evento,
> gerados por IA a partir de flyer em versão posterior). Ver seção "Plataforma self-service"
> abaixo e a SPEC do front (`events.raphael-martins.com/SPEC.md`).

---

## Banco

### `events`

```
id, owner_id (FK → users),
slug (unique),
status,                          -- draft | published | ended | archived
starts_at, ends_at, timezone,
capacity (nullable),
requires_ref (bool),
requires_turnstile (bool),
requires_photo (bool),           -- default true; se false, campo photo fica disabled no schema
registration_open (bool),
guest_form_schema_json (json),   -- schema do formulário do convidado
theme_json (jsonb nullable),     -- tema visual da landing (colors/fonts/mode/customCss)
content_json (jsonb nullable),   -- conteúdo das seções da landing (hero/info/rules/faq/...)
flyer_path (string nullable),    -- flyer original enviado pelo organizador (S3)
og_image_path (string nullable), -- OG image derivada do flyer (S3, pública)
published_at (timestamp nullable),
invite_template_key,             -- chave para custom templates (mail/pdf) em mail|pdf/events/custom/{key}
closed_message,
terms_url, privacy_url,
album_id (FK nullable → albums),
requires_payment (bool),
ticket_amount_cents (unsigned, nullable),
mercado_pago_account_id (FK nullable → mercado_pago_accounts),
timestamps
```

### `mercado_pago_accounts`

Contas Mercado Pago reutilizáveis por organizador (`owner_id`).

```
id, owner_id (FK → users),
label, public_key,
access_token (encrypted),
environment,                     -- sandbox | production
timestamps
```

### `guest_payments`

Snapshot 1:1 do pagamento no MVP.

```
id, guest_id (FK unique), mercado_pago_account_id (FK),
preference_id, payment_id (unique nullable),
status,                          -- pending | approved | rejected | refunded | expired
amount_cents, currency,
mp_last_payload (json),
paid_at,
timestamps
```

### `referral_links`

Links nomeados para lista controlada.

```
id, event_id (FK), name,
token (unique),
expires_at (nullable),
revoked_at (nullable),
used_count (int),
timestamps
```

### `guests`

```
id, event_id (FK), referral_link_id (FK nullable),
name, email, phone (nullable),
photo_path (nullable),
birth_year (nullable),
custom_data (json),              -- campos extras do formulário (schema dinâmico)
status,                          -- pending_email | pending_payment | confirmed | cancelled | blocked
consent_terms_at,
invite_sent_at,
email_confirmation_token (unique nullable),
email_confirmed_at,
payment_return_token (unique nullable),
payment_expires_at,
checked_in_at,
timestamps

UNIQUE: (event_id, email)
```

---

## Fluxo de registro

### 1. Landing externa chama a API

`GET /api/v1/events/{slug}/config` — retorna o `guest_form_schema_json`, flags (`requires_ref`, `requires_turnstile`, `registration_open`), capacidade, `terms_url`, `privacy_url`.

`POST /api/v1/events/{slug}/register` — body com dados do convidado validados contra o schema, opcional `ref` (token do `referral_link`) e Turnstile token.

- Cria `guest` com `status = pending_email` (padrão) ou `confirmed` se `skip_email_confirmation = true`.
- Gera `email_confirmation_token` (exceto skip).
- Envia `GuestInterestConfirmationMail` com link `GET /events/guest/confirm/{token}` **ou**, com skip, enfileira `SendGuestTicketEmailJob` e responde `flow: ticket_sent`.
- Config expõe `registration.requiresEmailConfirmation` para a landing.

**Fluxo pago** (`requires_payment = true`):

- Cria `guest` com `status = pending_payment`, `payment_return_token`, `payment_expires_at` (+15 min, configurável).
- Cria Preference no Mercado Pago (Checkout Pro; PIX + cartão; **sem boleto**; sem `expiration_date` na Preference — expiração só via job no Hub).
- Resposta API: `flow: checkout`, `checkoutUrl` — **não** envia e-mail de interesse.
- Webhook `POST /webhooks/mercadopago` confirma pagamento → `GuestTicketMail`.
- Return URL: `GET /events/payment/return/{token}` com polling em `GET /events/payment/status/{token}`.
- Job `ExpireUnpaidEventGuestsJob` (a cada 15 min): consulta MP; se não aprovado, **apaga** o guest (nova inscrição do zero).

Capacidade: `pending_payment` conta em `Event::reservedGuestsCount()`; check-in só `confirmed`.

CORS, rate limits e Turnstile controlados via `EVENTS_*` no `.env`.

### 2. Confirmação pública por e-mail

`GET /events/guest/confirm/{token}` — pública, sem autenticação.

- Atualiza `guests.email_confirmed_at` e `status = confirmed` (resposta HTML imediata).
- Enfileira `SendGuestTicketEmailJob` (fila `notifications`), que gera o PDF (DomPDF) e envia `GuestTicketMail` com anexo e QR (`GET /events/qr/{guest}` no corpo do e-mail).

### 3. Convite via hub (opcional)

No hub, o dono pode disparar `GuestInviteMail` com o mesmo PDF, mesmo antes do convidado se registrar. Útil para listas curadas.

### 4. Portaria — check-in

Rota autenticada `GET /events-checkin` (`event.checkin`), componente Livewire `EventCheckInPage`.

- Layout dedicado **mobile-first**.
- Leitor de QR via câmera (`html5-qrcode`, chunk em `resources/js/events-checkin.js`).
- Manifest PWA: `public/manifest-events-checkin.json`.
- Ao ler QR válido, atualiza `guests.checked_in_at`.

---

## Hub `/hub/events`

- `GET /hub/events` — listagem de eventos do dono.
- `GET /hub/events/{event}` — detalhe do evento, gestão de `referral_links`, `guests`, envio de convites.

Componentes Livewire em `App\Livewire\Events\*`.

---

## Plataforma self-service (API autenticada)

Backend SaaS multi-tenant do front `events.raphael-martins.com` (Nuxt). O front consome
estes endpoints via Nitro (BFF) com token Sanctum em cookie httpOnly — o browser nunca
fala direto com o hub autenticado.

### Auth (`/api/v1/auth/*`)

| Endpoint | Descrição |
|----------|-----------|
| `POST /auth/register` | Cria conta (Turnstile), envia verificação, retorna token Sanctum (30d, ability `events:*`). |
| `POST /auth/login` | Retorna token. Rate limit `auth` (5/min por IP, configurável via `EVENTS_RATE_LIMIT_MAX`). |
| `POST /auth/logout` (auth) | Revoga o token atual. |
| `GET /me` (auth) | Dados do usuário autenticado. |
| `POST /auth/email/verify/resend` (auth) | Reenvia e-mail de verificação (throttle 6/min). |
| `GET /auth/email/verify/{id}/{hash}` (signed) | Link do e-mail — marca verificado e redireciona para `{EVENTS_FRONTEND_URL}/auth/verified`. |

`User` implementa `MustVerifyEmail`; publicar evento exige e-mail verificado.

### Organizador (`/api/v1/me/*`, auth Sanctum)

| Endpoint | Descrição |
|----------|-----------|
| `GET /me/events` | Eventos do dono (paginado, com contadores). |
| `POST /me/events` | Cria rascunho (slug auto do título, único, sem reservados). |
| `GET /me/events/{event}` | Detalhe completo (tema, conteúdo, contadores, `publicUrl`). |
| `PATCH /me/events/{event}` | Update parcial: campos, flags, `theme` (com sanitização de `customCss`), `content` (seções validadas). Slug congela após publicar. |
| `POST /me/events/{event}/publish` | Publica: exige e-mail verificado, `starts_at`, e respeita `limits.max_published_events`. Abre inscrições. |
| `POST /me/events/{event}/archive` | Arquiva e fecha inscrições. |
| `GET /me/limits` | Limites da conta gratuita e uso atual. |

Tenancy: `EventPolicy` (owner-only; `super_admin` bypassa via `before`). A portaria
(`/events-checkin`) exige a ability `checkIn` da policy — corrigido o acesso aberto anterior.

### Tema, conteúdo e assets

- `theme_json`: `colors` (hex), `fonts` (allowlist `events.allowed_fonts`), `mode`,
  `borderRadius`, `customCss` (sanitizado por `ThemeService`: sem `@import`, `javascript:`,
  `position:fixed/sticky`, seletores globais, `url()` externa — exceto Google Fonts).
- `content_json`: seções fixas (`hero`, `info`, `countdown`, `rules`, `about`, `form`,
  `album`, `faq`) com `enabled` + campos validados no `UpdateEventRequest`.
- Config pública (`GET /events/{slug}/config`) expõe `theme` (merge default+evento),
  `content` e `og` (`image` do S3, `themeColor`).
- Tema padrão da plataforma em `config/events.php` (`default_theme`).
- Fotos de convidado agora no disco `EVENTS_GUEST_PHOTOS_DISK` (default `s3`/MinIO),
  servidas por URL assinada temporária (`Guest::photoTemporaryUrl()`, 15 min) na portaria.
  Migração one-off das fotos antigas: `php artisan events:migrate-guest-photos [--dry-run]`.
- `guests.custom_data`: campos custom do schema do formulário agora **persistem**
  (antes gravavam sempre `null`).

### Limites da conta gratuita (`config/events.php → limits`)

`max_published_events` (3), `max_guests_per_event` (500), `max_referral_links_per_event` (10),
`max_flyer_jobs_per_day` (5) — configuráveis via `EVENTS_MAX_*`. Enforcement progressivo
por milestone; `max_published_events` já aplicado no publish.

---

---

## E-mails

| Classe | Quando |
|--------|--------|
| `GuestInterestConfirmationMail` | Após `POST /register`. Link de confirmação. |
| `GuestTicketMail` | Após confirmação por e-mail **ou** pagamento aprovado. PDF anexado. |
| `GuestInviteMail` | Convite direto pelo hub. PDF anexado. |

PDFs gerados sob demanda. Templates em `resources/views/pdf/events/*`.

Templates de email em `resources/views/mail/events/*` — custom templates em `custom/{key}.blade.php` resolvidos por `invite_template_key`. Variáveis disponíveis: `$guest`, `$checkInUrl`, `$qrDataUri` (data URI para PDF), `$qrUrl` (URL pública para email).

---

## Pacotes envolvidos

- `barryvdh/laravel-dompdf` — geração de PDF.
- `chillerlan/php-qrcode` — QR em PNG via GD (sem Imagick). Servido via rota `GET /events/qr/{guest}` para compatibilidade com leitores de e-mail.
- `html5-qrcode` (npm) — leitor de QR no browser.
- `mercadopago/dx-php` — Checkout Pro / Payments API.

---

## Variáveis de ambiente

```env
EVENTS_FRONTEND_URL=             # landing PHP (botão voltar ao evento)
EVENTS_HUB_PUBLIC_URL=           # default APP_URL — back_urls e webhook MP
EVENTS_PAYMENT_RESERVATION_MINUTES=15
EVENTS_PAYMENT_POLL_INTERVAL_SECONDS=2
EVENTS_CORS_ORIGINS=
EVENTS_TURNSTILE_SECRET_KEY=
EVENTS_TURNSTILE_ENABLED=true
```

Webhook a cadastrar no painel Mercado Pago: `{EVENTS_HUB_PUBLIC_URL}/webhooks/mercadopago`

---

## Documentos relacionados

- [BRIEFING.md](BRIEFING.md) — contexto de produto, ADRs históricas, visão v2.
- [API_Contract.md](API_Contract.md) — contrato HTTP detalhado (exemplos JSON, códigos, CORS, rate limit).

Backlog específico de eventos (templates dinâmicos, exportação CSV, integração com álbuns) em [BACKLOG](../roadmap/BACKLOG.md).
