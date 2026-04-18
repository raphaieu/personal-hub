# SPEC — Raphael Hub

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 12 + PHP 8.4 |
| Frontend | Blade + Livewire 3 + Sanctum |
| Banco | PostgreSQL 17 (container próprio ou serviço local; não MySQL para este app) |
| Cache / Filas / Sessão | Redis 7 (container próprio) |
| Monitor de filas | Laravel Horizon |
| Storage | MinIO S3 — bucket `pessoal` (instância DopaCheck compartilhada) |
| Scraping | Node 24 + Playwright — container separado `raphael-playwright` |
| CAPTCHA | CapSolver (reCAPTCHA v3 — apenas Coelba) |
| AI | NeuronAI (Laravel) — Groq primário via OpenAILike, Claude fallback via Anthropic |
| WhatsApp | Evolution API — instância DopaCheck, número pessoal do Raphael |
| Infra | Docker Compose + aaPanel Nginx proxy + GitHub Actions CI/CD |

---

## Infraestrutura

### VPS
- Hostinger — Ubuntu 24 — 8 cores, 32GB RAM, 386GB disco
- IP: `77.37.68.36` (confirmar IP correto da Hostinger)
- aaPanel gerencia Nginx host e SSL (Cloudflare)
- Usuário de deploy: `deploy` (uid=1003, gid=1003)
- Path do projeto: `/home/deploy/raphael-hub`

### Domínio
- `api.raphael-martins.com` → proxy aaPanel → `raphael-nginx:80` (porta host 8082)
- DNS gerenciado na Cloudflare
- Registro A apontando para o IP da VPS

### Docker
- Network: `raphael-bridge` (isolada dos demais projetos)
- Containers: `raphael-app`, `raphael-nginx`, `raphael-postgres`, `raphael-redis`, `raphael-horizon`, `raphael-queue`, `raphael-scheduler`, `raphael-playwright`
- `PUID=1003` / `PGID=1003` em todos os containers Laravel

### Serviços compartilhados (DopaCheck)
- Evolution API: `https://whats.dopacheck.com.br` (porta 18080 interna)
- MinIO: `https://files.dopacheck.com.br` (porta 9000 interna)
- Acessados via URL pública (não há necessidade de join de network por ora)

### Desenvolvimento local com infra externa (ex.: hub.test)

Quando Postgres 17, nginx, Redis 7, Mailpit e **MySQL 8.4** rodam em um **projeto Docker separado** e o Laravel executa no host (ou em outro container) com hostname tipo **`hub.test`** no browser:

- **Banco do Hub**: sempre **PostgreSQL** (`DB_CONNECTION=pgsql`). **MySQL** na mesma stack é para outros projetos — não definir `DB_CONNECTION=mysql` para este app.
- **APP_URL**: tipicamente `http://hub.test` ou `https://hub.test` conforme SSL no nginx local; deve coincidir com o virtual host para cookies/sessão.
- **DB_HOST / REDIS_HOST**: com portas mapeadas no host, usar `127.0.0.1` (ou `host.docker.internal` se o PHP estiver em container sem `network_mode: host`). Dentro da rede Compose do próprio repo, usar o nome do serviço (ex.: `raphael-postgres`).
- **Mailpit**: SMTP em `127.0.0.1:1025` (ou hostname do serviço Mailpit se o PHP compartilha rede com ele); UI de inspeção costuma ser `:8025` ou a porta exposta pelo compose de infra.
- **Playwright**: o Laravel chama o servidor Node por URL configurável no código/config (produção na rede Docker: `http://raphael-playwright:3001`; local: `http://127.0.0.1:3001` com porta publicada ou o hostname do container se estiver na mesma rede).

Para o mapa tabular **`APP_*` / `DB_*` / `REDIS_*` / mail** (produção vs hub.test), usar [CLAUDE.md](CLAUDE.md) e os comentários por bloco em [`.env.example`](.env.example).

---

## Banco de Dados

**Convenção:** nomes de **tabelas e colunas em inglês** no PostgreSQL (alinhado ao código Laravel). Labels e mensagens de usuário continuam em pt-BR na aplicação. Campos pensados para **V2** estão no mesmo schema; backlog em [docs/v2.md](docs/v2.md).

### Tabelas

#### `users` (extensão além do Breeze)
```
global_role: super_admin | member (default member)
```
Seed opcional via `SuperAdminUserSeeder` (`HUB_SEED_*` no `.env`; senha nunca no repositório).

#### `monitored_sources`
```
id, kind (self|contact|group), identifier (unique JID), label,
permissions (json nullable), is_active (bool), notes,
media_storage_prefix (nullable), timestamps
```

#### `monitored_source_user` (pivot — V2 / multi-admin de grupo)
```
id, user_id (FK), monitored_source_id (FK), role (group_admin|viewer), timestamps
UNIQUE: user_id + monitored_source_id
```

#### `utility_accounts`
Contas de concessionárias (água/luz).

```
id, kind (embasa|coelba), account_ref, label, due_day (int),
reminder_lead_days (int, default 5), is_active (bool),
last_scraped_at (timestamp nullable), timestamps
```

#### `invoices`
```
id, utility_account_id (FK), billing_reference (ex.: 05/2026), due_date,
amount_total, amount_water, amount_sewage, amount_service,
water_consumption_m3 (nullable), status,
payment_date (nullable), pdf_path, raw_payload (json),
scraped_at, last_notified_at, timestamps
UNIQUE: utility_account_id + billing_reference
```

#### `message_logs`
Registro de mensagens processadas (grupo/DM): remetente, conversa, tipo extensível, menções.

```
id, monitored_source_id (FK nullable),

chat_jid, sender_jid (nullable),

direction — inbound | outbound,

message_type — text, audio, image, etc. (string extensível),

body, mentions (json),
quoted_evolution_message_id,

intent, sentiment, confidence, category,

metadata (json),
is_processed (bool),

transcription_text, transcription_provider, transcribed_at,
vision_summary, vision_provider,

ai_pipeline_status,
evolution_message_id (unique nullable),

timestamps
```

Índices: `(monitored_source_id, created_at)`, `(chat_jid, created_at)`, `sender_jid`.

#### `message_attachments`
Mídia/arquivos ligados a `message_logs`; objeto no MinIO usando `media_storage_prefix` da fonte quando aplicável.

```
id, message_log_id (FK),
kind, original_file_name, mime_type,
storage_path, file_bytes,
duration_seconds, width, height,
sha256, metadata (json), timestamps
```

#### `reminders`
```
id, kind (text|url|image|audio|document), body, file_path,
url_title, url_description, url_image,
category, is_archived,
message_log_id (FK nullable), timestamps
```

---

## Serviços Laravel

### `WebhookRouterService`
Recebe payload da Evolution, extrai tipo/conteúdo, identifica a source, loga e despacha o Job correto.

**Regras de roteamento:**
1. `fromMe = true` + JID terminando em `@s.whatsapp.net` → `ProcessarMensagemPessoal`
2. JID em `monitored_sources` com `kind = contact` → `ProcessarMensagemContato`
3. JID em `monitored_sources` com `kind = group` → `ProcessarMensagemGrupo`
4. Qualquer outro → loga e ignora silenciosamente

### `NeuronAIService`
Wrapper sobre NeuronAI. Métodos:
- `classificarIntencaoPessoal(tipo, conteudo)` → `{intencao, sentimento, categoria, confianca}`
- `classificarIntencaoContato(conteudo, permissoes)` → `{intencao, confianca}`
- `buildInvoiceReply(Invoice $invoice)` → string formatada para WhatsApp

**Provider config:**
- Primário: Groq via `OpenAILike` (url: `https://api.groq.com/openai/v1`, model: `llama-3.3-70b-versatile`)
- Fallback: Anthropic Claude (`claude-sonnet-4-20250514`)
- Troca automática em caso de erro/timeout do primário

### `PlaywrightService`
HTTP client que se comunica com o container `raphael-playwright` via rede Docker interna (`http://raphael-playwright:3001`).

Métodos:
- `scrapeEmbasa()` → array com faturas + pdf_path
- `scrapeCoelba()` → array com faturas + pdf_path
- `healthCheck()` → bool

### `EvolutionService`
Wrapper HTTP para a Evolution API.

Métodos:
- `sendText(jid, text)` → void
- `sendMedia(jid, url, caption, mediaType)` → void
- `sendDocument(jid, path, filename, caption)` → void

### `InvoiceService`
Orquestra o ciclo de vida de uma fatura no banco (`invoices`).

Métodos:
- `processScrapeResult(array $payload, UtilityAccount $account)` → upsert invoice, upload PDF, trigger notificação
- `uploadPdf(string $localPath, UtilityAccount $account, string $billingReference)` → path no MinIO
- `notifyHomeGroup(Invoice $invoice)` → formata mensagem e chama EvolutionService

---

## Jobs (filas Redis via Horizon)

| Job | Fila | Trigger |
|---|---|---|
| `ProcessPersonalWhatsAppMessage` | `default` | Webhook isFromMe |
| `ProcessContactWhatsAppMessage` | `default` | Webhook contato monitorado |
| `ProcessGroupWhatsAppMessage` | `default` | Webhook grupo monitorado |
| `ScrapeConta` | `scraping` | Schedule ou on-demand |
| `EnriquecerUrlLembrete` | `default` | Após salvar lembrete de URL |
| `NotificarVencimento` | `notifications` | Schedule diário |

---

## Schedule (`app/Console/Kernel.php`)

```php
// Scrape completo — X dias antes do vencimento de cada conta
// Roda diariamente, verifica se está dentro da janela de cada conta
$schedule->job(new ScrapeConta('embasa'))->dailyAt('08:00');
$schedule->job(new ScrapeConta('coelba'))->dailyAt('08:05');

// Verificação leve de status — diário enquanto há fatura pendente
$schedule->job(new VerificarStatusFaturas())->dailyAt('09:00');

// Lembrete recorrente — diário até pagamento
$schedule->job(new NotificarVencimento())->dailyAt('09:30');
```

**Lógica do `ScrapeConta`:**
1. Busca conta ativa no banco
2. Calcula se hoje está dentro da janela `(dia_vencimento - dias_antecedencia)` a `(dia_vencimento + 30)`
3. Se sim → chama `PlaywrightService::scrapeEmbasa/Coelba()`
4. Chama `InvoiceService::processScrapeResult()`
5. Atualiza `utility_accounts.last_scraped_at`

**Lógica do `NotificarVencimento`:**
1. Busca invoices com status != pago e vencimento nos próximos N dias
2. Para cada uma, verifica se já notificou hoje (`last_notified_at`)
3. Se não → monta mensagem → `EvolutionService::sendText(grupo_da_casa)`
4. Atualiza `last_notified_at`

---

## Container Playwright

Servidor HTTP Node.js rodando na porta `3001` (interno à rede Docker).

### Rotas
- `GET /health` → `{ status: 'ok' }`
- `POST /scrape/embasa` → executa scraper Embasa, retorna JSON
- `POST /scrape/coelba` → executa scraper Coelba com CapSolver, retorna JSON

### Resposta padrão dos scrapers
```json
{
  "success": true,
  "data": {
    "concessionaria": "embasa|coelba",
    "matricula": "...",
    "scraped_at": "ISO8601",
    "faturas": [
      {
        "referencia": "05/2026",
        "vencimento": "08/05/2026",
        "valor_total": "R$ 77,81",
        "status": "pendente|pago|processando|a_vencer|vencida",
        "data_pagamento": null
      }
    ],
    "pdf_path": "/app/downloads/embasa_1234567890.pdf"
  }
}
```

### CapSolver (Coelba)
- Tipo: `ReCaptchaV3TaskProxyLess`
- `websiteURL`: `https://agenciavirtual.neoenergia.com`
- `pageAction`: `login`
- `minScore`: `0.5`
- Fallback: tenta submeter sem token se CapSolver falhar

---

## Fluxo Embasa (mapeado)

```
URL login:   https://atendimentovirtual.embasa.ba.gov.br/login
Campos:      input CPF + input[type=password]
Botão:       button "Entrar"
Pós-login:   redireciona para /home

Matrícula:   clica dropdown "Matrícula: Selecionar" no header
             modal "Minhas Matrículas" abre
             clica na matrícula (text: "28367294")
             clica "SELECIONAR MATRÍCULA"

2ª via:      navega direto para /segunda-via?pay=true
             campo matrícula já preenchido → clica "PRÓXIMO"
             tela "Débitos da Matrícula" exibe todas as faturas

Dados:       Referência, Vencimento, Consumo m², Valor Água,
             Valor Esgoto, Valor Serviço, Valor Total, Status
Status:      "Aguardando pagamento" → pendente
             "Conta Paga ✓"        → pago
             "em processamento bancário" → processando

PDF:         botão "BAIXAR 2ª VIA" em cada fatura
```

---

## Fluxo Coelba (mapeado)

```
URL:         https://agenciavirtual.neoenergia.com/#/login
CAPTCHA:     reCAPTCHA v3 invisível — CapSolver resolve antes de submeter

Botão:       clica "LOGIN" no header/hero → abre modal
Campos:      input CPF/CNPJ + input[type=password]
Botão:       "ENTRAR"
Pós-login:   redireciona para /#/home/selecionar-estado

Estado:      clica card "Bahia"
             redireciona para /#/home/meus-imoveis

Unidade:     código do cliente: 000030287096
             clica na linha/card da unidade
             redireciona para /#/home

Faturas:     clica "Faturas e 2ª via de faturas" (serviços rápidos)
             redireciona para /#/home/servicos/consultar-debitos

Dados:       Referência, Vencimento, Valor Fatura, Situação, Data Pagamento
Status:      "A Vencer"  → a_vencer
             "Vencida"   → vencida
             "Pago"      → pago

PDF:         expande o item da fatura → botão "BAIXAR"
```

---

## Autenticação Dashboard

- Laravel Breeze (Blade stack)
- Acesso apenas via email cadastrado no `.env` (`HORIZON_AUTH_EMAILS`)
- Horizon protegido pelo mesmo middleware
- Sem registro público — apenas login

---

## Variáveis de Ambiente Críticas

Configurar **`APP_URL`**, **`DB_*`** (somente PostgreSQL para este app), **`REDIS_*`** e **`MAIL_*`** conforme o ambiente.

O [`.env.example`](.env.example) é o **baseline de produção** (Docker VPS, serviços `raphael-postgres` / `raphael-redis`). Os **comentários no arquivo** e a secção *Infraestrutura → Desenvolvimento local com infra externa* no [CLAUDE.md](CLAUDE.md) descrevem sobrescritas para **hub.test** (Postgres 17, Redis 7, Mailpit, `127.0.0.1`, etc.).

```env
# Identificadores das concessionárias
EMBASA_CPF=
EMBASA_PASSWORD=
EMBASA_MATRICULA=28367294

COELBA_CPF=
COELBA_PASSWORD=
COELBA_CODIGO_CLIENTE=000030287096

# Evolution — instância e número
EVOLUTION_URL=https://whats.dopacheck.com.br
EVOLUTION_API_KEY=
EVOLUTION_INSTANCE=raphael

# JID do grupo da casa para notificações
WHATSAPP_GRUPO_CASA_JID=

# MinIO
AWS_BUCKET=pessoal
AWS_ENDPOINT=https://files.dopacheck.com.br
AWS_USE_PATH_STYLE_ENDPOINT=true

# AI
GROQ_API_KEY=
GROQ_MODEL=llama-3.3-70b-versatile
GROQ_URL=https://api.groq.com/openai/v1
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-20250514

# CapSolver
CAPSOLVER_API_KEY=
```

---

## CI/CD

- Branch `main` → deploy automático
- `rsync` para `/home/deploy/raphael-hub` excluindo `.env`, `vendor`, `node_modules`, `playwright/node_modules`
- `composer install --no-dev` no host antes do Docker subir
- `npm install` dentro de `playwright/`
- `docker compose up -d --build --force-recreate --remove-orphans`
- `php artisan migrate --force` após containers subirem
- `php artisan config:cache && route:cache && view:cache`
- Limpeza com `docker system prune -af --volumes=false`

---

## Estrutura de Diretórios Laravel

```
app/
  Http/Controllers/
    WebhookController.php
    DashboardController.php
    UtilityAccountController.php
    InvoiceController.php
    ReminderController.php
    MonitoredSourceController.php
  Jobs/
    ProcessPersonalWhatsAppMessage.php
    ProcessContactWhatsAppMessage.php
    ProcessGroupWhatsAppMessage.php
    ScrapeConta.php
    VerificarStatusFaturas.php
    NotificarVencimento.php
    EnriquecerUrlLembrete.php
  Models/
    MonitoredSource.php
    UtilityAccount.php
    Invoice.php
    MessageLog.php
    MessageAttachment.php
    Reminder.php
  Services/
    WebhookRouterService.php
    NeuronAIService.php
    PlaywrightService.php
    EvolutionService.php
    InvoiceService.php

database/
  migrations/
    ..._create_monitored_sources_table.php
    ..._create_utility_accounts_table.php
    ..._create_invoices_table.php
    ..._create_message_logs_table.php
    ..._create_message_attachments_table.php
    ..._create_reminders_table.php
    ..._add_hub_fields_to_users_table.php
    ..._create_monitored_source_user_table.php
  seeders/
    SuperAdminUserSeeder.php
    InitialDataSeeder.php  ← (futuro) utility accounts Embasa/Coelba + source self

playwright/
  server.js
  package.json
  scrapers/
    embasa.js
    coelba.js

docker/
  nginx/
    default.conf

.github/
  workflows/
    deploy.yml
```
