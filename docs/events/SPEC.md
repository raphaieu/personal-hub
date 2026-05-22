# SPEC — Eventos privados

Hub `/hub/events`, API pública para landing externa, fluxo de dupla confirmação por e-mail, ingresso PDF com QR (SVG), portaria mobile-first com leitor de QR pela câmera.

Esta SPEC é a entrada canônica do módulo. [BRIEFING.md](BRIEFING.md) mantém a visão longa de produto e ADRs históricas; [API_Contract.md](API_Contract.md) detalha o contrato HTTP linha-a-linha.

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
invite_template_key,             -- chave para custom templates (mail/pdf) em mail|pdf/events/custom/{key}
closed_message,
terms_url, privacy_url,
album_id (FK nullable → albums),
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
status,                          -- pending_email | confirmed | cancelled | blocked
consent_terms_at,
invite_sent_at,
email_confirmation_token (unique nullable),
email_confirmed_at,
checked_in_at,
timestamps

UNIQUE: (event_id, email)
```

---

## Fluxo de registro

### 1. Landing externa chama a API

`GET /api/v1/events/{slug}/config` — retorna o `guest_form_schema_json`, flags (`requires_ref`, `requires_turnstile`, `registration_open`), capacidade, `terms_url`, `privacy_url`.

`POST /api/v1/events/{slug}/register` — body com dados do convidado validados contra o schema, opcional `ref` (token do `referral_link`) e Turnstile token.

- Cria `guest` com `status = pending_email`.
- Gera `email_confirmation_token`.
- Envia `GuestInterestConfirmationMail` com link `GET /events/guest/confirm/{token}`.

CORS, rate limits e Turnstile controlados via `EVENTS_*` no `.env`.

### 2. Confirmação pública por e-mail

`GET /events/guest/confirm/{token}` — pública, sem autenticação.

- Atualiza `guests.email_confirmed_at` e `status = confirmed`.
- Gera **PDF do ingresso** (DomPDF, view em `resources/views/pdf/events/*`) com **QR code PNG** (data URI via `chillerlan/php-qrcode`, GD sem Imagick) apontando para URL de check-in do convidado.
- Envia `GuestTicketMail` com o PDF anexado e QR code referenciado via URL pública `GET /events/qr/{guest}` no corpo do email.

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

## E-mails

| Classe | Quando |
|--------|--------|
| `GuestInterestConfirmationMail` | Após `POST /register`. Link de confirmação. |
| `GuestTicketMail` | Após confirmação por e-mail. PDF anexado. |
| `GuestInviteMail` | Convite direto pelo hub. PDF anexado. |

PDFs gerados sob demanda. Templates em `resources/views/pdf/events/*`.

Templates de email em `resources/views/mail/events/*` — custom templates em `custom/{key}.blade.php` resolvidos por `invite_template_key`. Variáveis disponíveis: `$guest`, `$checkInUrl`, `$qrDataUri` (data URI para PDF), `$qrUrl` (URL pública para email).

---

## Pacotes envolvidos

- `barryvdh/laravel-dompdf` — geração de PDF.
- `chillerlan/php-qrcode` — QR em PNG via GD (sem Imagick). Servido via rota `GET /events/qr/{guest}` para compatibilidade com leitores de e-mail.
- `html5-qrcode` (npm) — leitor de QR no browser.

---

## Variáveis de ambiente

```env
EVENTS_CORS_ALLOWED_ORIGINS=
EVENTS_RATE_LIMIT_PER_MINUTE=
EVENTS_TURNSTILE_SECRET=
EVENTS_TURNSTILE_SITE_KEY=
EVENTS_PUBLIC_BASE_URL=          # base para links de confirmação
```

---

## Documentos relacionados

- [BRIEFING.md](BRIEFING.md) — contexto de produto, ADRs históricas, visão v2.
- [API_Contract.md](API_Contract.md) — contrato HTTP detalhado (exemplos JSON, códigos, CORS, rate limit).

Backlog específico de eventos (templates dinâmicos, exportação CSV, integração com álbuns) em [BACKLOG](../roadmap/BACKLOG.md).
