# SPEC — Raphael Hub

## Stack


| Camada                 | Tecnologia                                                                                                                                                      |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Backend                | Laravel 13 + PHP 8.4                                                                                                                                            |
| Frontend               | Blade + Livewire 4 + Sanctum                                                                                                                                    |
| Banco                  | PostgreSQL 17 (container próprio ou serviço local; não MySQL para este app)                                                                                     |
| Cache / Filas / Sessão | Redis 7 (container próprio)                                                                                                                                     |
| Monitor de filas       | Laravel Horizon                                                                                                                                                 |
| Storage                | MinIO S3 próprio — container `raphael-minio`, bucket configurável (`AWS_BUCKET`, típico `pessoal`)                                                              |
| Scraping               | Node 24 + Playwright — container separado `raphael-playwright`                                                                                                  |
| CAPTCHA                | CapSolver (reCAPTCHA v3 — apenas Coelba)                                                                                                                        |
| AI                     | NeuronAI (Laravel) — **local-first** com **Ollama no host** quando `OLLAMA_ENABLED=true`; em seguida Groq / OpenAI / Anthropic conforme configuração e fallback |
| WhatsApp               | Evolution API — containers próprios (`raphael-evolution` + Postgres/Redis dedicados), número pessoal do Raphael                                                 |
| Infra                  | Docker Compose + aaPanel Nginx proxy + GitHub Actions CI/CD                                                                                                     |


---

## Infraestrutura

### VPS

- Hostinger — Ubuntu 24 — 8 cores, 32GB RAM, ~380GB SSD
- IP: `31.97.17.4` (confirmar IP correto da Hostinger)
- aaPanel gerencia Nginx host e SSL (Cloudflare)
- Usuário de deploy: `deploy` (uid=1003, gid=1003)
- Path do projeto: `/home/deploy/raphael-hub`

### Domínio

- `api.raphael-martins.com` → proxy aaPanel → `raphael-nginx:80` (porta host 8082)
- DNS gerenciado na Cloudflare
- Registro A apontando para o IP da VPS

### Docker

- Network: `raphael-bridge` (isolada dos demais projetos); gateway típico na VPS para acesso host↔containers: `**172.23.0.1`** (usado pelo app para Ollama no host)
- Containers principais: `raphael-app`, `raphael-nginx`, `raphael-postgres`, `raphael-redis`, `raphael-horizon`, `raphael-queue`, `raphael-scheduler`, `raphael-playwright` (opcional), `raphael-minio`, `raphael-evolution`, `raphael-evolution-postgres`, `raphael-evolution-redis`
- `PUID=1003` / `PGID=1003` em todos os containers Laravel

### Serviços dedicados (stack do Raphael Hub)

- **MinIO** (`raphael-minio`): API S3 na porta interna **9000**; no host costuma estar em `**127.0.0.1:19000`** (API) e `**127.0.0.1:19001**` (console). Exposição pública via proxy: `**https://files.raphael-martins.com**`. Dentro do Compose o Laravel usa hostname `**minio**` (ex.: `AWS_ENDPOINT=http://minio:9000`).
- **Evolution API** (`raphael-evolution`): API própria; estado em `**raphael-evolution-postgres`** e cache em `**raphael-evolution-redis**`. URL pública da API conforme `**EVOLUTION_URL**` no `.env` (baseline em `.env.example`: `**https://evo.raphael-martins.com**`). Webhook do Laravel: `**POST /webhook/whatsapp**` → `**https://api.raphael-martins.com/webhook/whatsapp**`. A Evolution pode usar o mesmo MinIO para mídia (variáveis `S3_*` na stack).

### Ollama (host — fora do Compose)

- Rodando no **host** via systemd (não é container do projeto). Porta `**11434`**, bind típico `**0.0.0.0**` só para aceitar tráfego da bridge.
- Dos containers Laravel, URL típica: `**OLLAMA_BASE_URL=http://172.23.0.1:11434**` (gateway Docker → host).
- **Firewall (UFW):** permitir `**172.23.0.0/16` → `tcp/11434`** e não expor Ollama publicamente.

### Desenvolvimento local com infra externa (ex.: hub.test)

Quando Postgres 17, nginx, Redis 7, Mailpit e **MySQL 8.4** rodam em um **projeto Docker separado** e o Laravel executa no host (ou em outro container) com hostname tipo `**hub.test`** no browser:

- **Banco do Hub**: sempre **PostgreSQL** (`DB_CONNECTION=pgsql`). **MySQL** na mesma stack é para outros projetos — não definir `DB_CONNECTION=mysql` para este app.
- **APP_URL**: tipicamente `http://hub.test` ou `https://hub.test` conforme SSL no nginx local; deve coincidir com o virtual host para cookies/sessão.
- **DB_HOST / REDIS_HOST**: com portas mapeadas no host, usar `127.0.0.1` (ou `host.docker.internal` se o PHP estiver em container sem `network_mode: host`). Dentro da rede Compose do próprio repo, usar o nome do serviço (ex.: `raphael-postgres`).
- **Mailpit**: SMTP em `127.0.0.1:1025` (ou hostname do serviço Mailpit se o PHP compartilha rede com ele); UI de inspeção costuma ser `:8025` ou a porta exposta pelo compose de infra.
- **Playwright**: o Laravel chama o servidor Node por URL configurável no código/config (produção na rede Docker: `http://raphael-playwright:3001`; local: `http://127.0.0.1:3001` com porta publicada ou o hostname do container se estiver na mesma rede).

Para o mapa tabular `**APP_`* / `DB_*` / `REDIS_*` / mail** (produção vs hub.test), usar [CLAUDE.md](CLAUDE.md) e os comentários por bloco em `[.env.example](.env.example)`.

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

Recebe payload da Evolution, extrai tipo/conteúdo, identifica a source e despacha o Job correto.

**Eventos HTTP tratados como mensagem:** `messages.upsert` e `send.message` (aliases normalizados: `MESSAGES_UPSERT`, `SEND_MESSAGE`, `messages_upsert`, etc.).

**Regras de roteamento (ordem):**

1. `fromMe = true` + JID terminando em `@s.whatsapp.net` → `ProcessPersonalWhatsAppMessage`
2. `chat_jid` igual a `config('services.whatsapp.notes_solo_group_jid')` (tipicamente `WHATSAPP_NOTAS_GRUPO_JID`) → `ProcessPersonalWhatsAppMessage` — grupo “só você” para notas/mídia; `monitored_source_id` segue o registro `group` no banco quando existir
3. JID em `monitored_sources` com `kind = contact` → `ProcessContactWhatsAppMessage`
4. JID em `monitored_sources` com `kind = group` (e não coberto pelo item 2) → `ProcessGroupWhatsAppMessage`
5. Qualquer outro → persiste `message_logs` com rota ignorada e sem job

### Camada de IA (NeuronAI + roteamento)

**Pacote:** `neuron-core/neuron-ai` — providers oficiais (`Ollama`, `OpenAILike` para Groq, `Anthropic`, `OpenAI\OpenAI`).

| Classe | Papel |
|--------|--------|
| `OllamaService` | Instancia o provider Ollama (URL com sufixo `/api`, `OLLAMA_THINK`, timeout curto). |
| `AiRouterService` | Regras: tarefas leves e prompt curto → tenta Ollama primeiro; prompt “longo” (acima de `AI_PROMPT_LONG_THRESHOLD`) ou modo `chat_long` → pula Ollama; cadeia de fallback **Ollama (se habilitado) → Groq → Anthropic → OpenAI**; resposta vazia ou JSON inválido com `expect_json` → próximo provider. Timeouts em `config/ai.php`. Logs `ai.completion` / `ai.completion_failure`. |
| `NeuronAIService` | Façade: `complete(userPrompt, AiTask, ?system, expectJson)` devolve `AiCompletionResult` (texto, `provider`, `model`, `latency_ms`, `fallback_used`). |
| `App\Enums\AiTask` | `mode` HTTP (`classification`, `classify`, `sentiment`, `summary_short`, `chat`, `chat_long`, …). |

**Rota HTTP (gateway admin / dev remoto):** `POST /iara` — `IaraController`, body: `prompt`, `mode` opcional, `system` opcional, `expect_json` opcional. Fora de `local`/`testing`: header `X-Internal-Key` + `IARA_INTERNAL_KEY`; opcional `IARA_ALLOWED_IPS`. Throttle dedicado; CSRF excetuado (`iara`). **Não** substitui o daemon Ollama na rede interna — na VPS o app chama `OLLAMA_BASE_URL` (host); num notebook só se chama `POST https://api…/iara` com a chave.

**Ainda não implementados no domínio (SPEC alvo):**

- `classificarIntencaoPessoal(tipo, conteudo)` → estrutura `{intencao, sentimento, …}` em cima de `complete`.
- `classificarIntencaoContato(conteudo, permissoes)`
- `buildInvoiceReply(Invoice $invoice)`

**Variáveis:** ver `config/services.php` (`ollama`, `iara`, `groq`, `anthropic`, `openai`) e `config/ai.php`; baseline em `.env.example` (perfis VPS vs dev remoto vs Ollama local).

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


| Job                              | Fila            | Trigger                     |
| -------------------------------- | --------------- | --------------------------- |
| `ProcessPersonalWhatsAppMessage` | `default`       | Webhook isFromMe            |
| `ProcessContactWhatsAppMessage`  | `default`       | Webhook contato monitorado  |
| `ProcessGroupWhatsAppMessage`    | `default`       | Webhook grupo monitorado    |
| `ScrapeConta`                    | `scraping`      | Schedule ou on-demand       |
| `EnriquecerUrlLembrete`          | `default`       | Após salvar lembrete de URL |
| `NotificarVencimento`            | `notifications` | Schedule diário             |
| `RecalculateCommentScoreJob`     | `default`       | Após voto em `/oportunidades` |


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

## Playwright Threads (Fases 0, 1 e 2)

Implementação inicial do serviço em `playwright/` para scraping autenticado do Threads com sessão persistida e contrato HTTP para integração com Laravel.

### Endpoints Threads

- `GET /health`
  - Retorna `status`, `session_ready`, `session_path`.
- `POST /threads/auth/login`
  - Body: `{ "force_relogin": false }`
  - Faz login com `THREADS_USERNAME`/`THREADS_PASSWORD` e persiste `storageState`.
- `POST /threads/scrape-url`
  - Body: `{ "url": "https://www.threads.com/@handle/post/..." }`
  - Retorna post + comentários do thread, com scroll adaptativo e acumulação incremental (mitiga virtualização de DOM).
- `POST /threads/scrape-keyword`
  - Body mínimo: `{ "keyword": "freelance remoto php", "max_posts": 30 }`
  - Padrão: `include_comments=false` (modo recomendado para descoberta de posts de vaga/freela).
  - Suporta dedupe no próprio scraper: `known_post_ids`, `only_new`, `known_streak_stop`.

### Contrato de keyword para Laravel

Request (recomendado para jobs agendados):

```json
{
  "keyword": "freelance remoto php",
  "max_posts": 30,
  "include_comments": false,
  "only_new": true,
  "known_post_ids": ["DXaaS6-igb9", "DXATKvACX6e"],
  "known_streak_stop": 20
}
```

Response (campos relevantes):

```json
{
  "success": true,
  "mode": "keyword",
  "include_comments": false,
  "only_new": true,
  "data": {
    "posts": [
      {
        "post": {
          "external_id": "DXaaS6-igb9",
          "author_handle": "@rebekahyurll",
          "content": "..."
        },
        "is_known": false
      }
    ],
    "stats": {
      "posts_detected": 30,
      "posts_selected": 30,
      "posts_processed": 18,
      "known_detected": 12,
      "new_detected": 18,
      "skipped_known": 12,
      "early_stop_triggered": true,
      "known_streak_stop": 20,
      "comments_total": 0
    }
  }
}
```

### Variáveis Threads usadas pelo serviço

`PLAYWRIGHT_SERVICE_URL`, `THREADS_USERNAME`, `THREADS_PASSWORD`, `THREADS_SESSION_PATH`, `THREADS_MAX_POSTS_PER_KEYWORD`, `THREADS_STEP_TIMEOUT_MS`, `THREADS_RANDOM_DELAY_MIN_MS`, `THREADS_RANDOM_DELAY_MAX_MS`, `THREADS_MAX_SCROLL_ROUNDS`, `THREADS_SCROLL_IDLE_ROUNDS`, `THREADS_KNOWN_STREAK_STOP`, `THREADS_DEBUG_DIR`.

### Observações de operação

- Em dev, recomendado `PLAYWRIGHT_HEADLESS=false` para depuração de login/seletores.
- Para reduzir ruído/custo no pipeline, `keyword` deve operar em `posts-only`; comentários ficam para coletas por URL específica ou investigação pontual.
- A deduplicação final continua obrigatória no Laravel por `external_id` único.

### Integração Laravel (Fase 1 e Fase 2 concluídas)

- **Fase 1 (dados/domínio)**:
  - Migrations criadas: `threads_sources`, `threads_categories`, `threads_posts`, `threads_comments`, `threads_comment_votes`.
  - Models criados: `ThreadsSource`, `ThreadsCategory`, `ThreadsPost`, `ThreadsComment`, `ThreadsCommentVote`.
  - Seed base: `ThreadsCategorySeeder` integrado ao `DatabaseSeeder`.
- **Fase 2 (contrato mockável + cliente HTTP)**:
  - Contrato: `App\Contracts\ThreadsScraperClientInterface`.
  - Implementação real: `App\Services\Threads\ThreadsPlaywrightService`.
  - Implementação fake para testes: `App\Services\Threads\FakeThreadsScraperClient`.
  - Bind de container em `AppServiceProvider` para desacoplar jobs da runtime Node/Playwright.
  - Config dedicada: `services.playwright.url` e `services.playwright.timeout` (`PLAYWRIGHT_HTTP_TIMEOUT`).
- **Cobertura automática mínima da integração**:
  - `tests/Feature/Threads/ThreadsScraperClientTest.php` cobre resolução da interface, normalização de payload, tratamento de erro HTTP e comportamento do fake.

### Integração Laravel (Fase 3.1 concluída)

- **Pipeline base de scraping desacoplado da runtime Node**:
  - Jobs criados: `ScrapeThreadsUrlJob` e `ScrapeThreadsKeywordJob`.
  - Ambos dependem de `ThreadsScraperClientInterface` (sem acoplamento direto ao Playwright no domínio Laravel).
  - Fila padrão do bloco: `scraping`.
- **Persistência inicial idempotente**:
  - Serviço `ThreadsScrapeIngestionService` faz ingestão/upsert em `threads_posts` e `threads_comments`.
  - Dedupe por `external_id` (chave única já existente no schema) para permitir reprocessamento sem duplicidade.
  - `threads_sources.last_scraped_at` é atualizado quando o job recebe `threads_source_id`.
- **Cobertura automática mínima do bloco**:
  - `tests/Feature/Threads/ThreadsScrapeIngestionJobsTest.php` cobre execução dos jobs com `FakeThreadsScraperClient` e dedupe de post/comentário por `external_id`.

### Integração Laravel (Fase 3.2 concluída)

- **Classificação IA de comentários**:
  - Serviço `ThreadsClassificationService` usa `NeuronAIService::complete(..., expectJson: true)` com `AiTask::ThreadsOpportunityClassification`.
  - JSON esperado do classificador: `category_slug`, `summary`, `relevance_score`.
  - Mapeamento persistido para `threads_comments`: `threads_category_id`, `ai_summary`, `ai_relevance_score`, `ai_meta`.
- **Regra de threshold de relevância**:
  - Variável `THREADS_RELEVANCE_THRESHOLD` (configurada em `services.threads.relevance_threshold`).
  - `relevance_score` abaixo do corte => `status=ignored`.
  - `relevance_score` no/above corte => `status=pending_review`.
  - O serviço normaliza escala 0..1 e 0..100 para permitir configuração flexível.
- **Orquestração por filas**:
  - Job `ClassifyCommentsJob` criado na fila `ai`.
  - Jobs de scraping (`ScrapeThreadsUrlJob`, `ScrapeThreadsKeywordJob`) disparam classificação assíncrona quando há comentários ingeridos.
  - Horizon atualizado com supervisor dedicado para fila `ai`.
- **Cobertura automática mínima do bloco**:
  - `tests/Feature/Threads/ThreadsClassificationServiceTest.php` cobre threshold/status e execução do job de IA.
  - `tests/Feature/Threads/ThreadsScrapeIngestionJobsTest.php` cobre disparo (ou não) de `ClassifyCommentsJob` após ingestão.

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

Configurar `**APP_URL**`, `**DB_***` (somente PostgreSQL para este app), `**REDIS_***` e `**MAIL_***` conforme o ambiente.

O `[.env.example](.env.example)` é o **baseline de produção** (Docker VPS, serviços `raphael-postgres` / `raphael-redis`). Os **comentários no arquivo** e a secção *Infraestrutura → Desenvolvimento local com infra externa* no [CLAUDE.md](CLAUDE.md) descrevem sobrescritas para **hub.test** (Postgres 17, Redis 7, Mailpit, `127.0.0.1`, etc.).

```env
# Identificadores das concessionárias
EMBASA_CPF=
EMBASA_PASSWORD=
EMBASA_MATRICULA=28367294

COELBA_CPF=
COELBA_PASSWORD=
COELBA_CODIGO_CLIENTE=000030287096

# Evolution — URL da API (pública), instância e número
EVOLUTION_URL=https://evo.raphael-martins.com
EVOLUTION_API_KEY=
EVOLUTION_INSTANCE=raphael

# JID do grupo da casa para notificações
WHATSAPP_GRUPO_CASA_JID=

# MinIO — dentro do Compose use o hostname do serviço (ver .env.example); público via proxy
AWS_BUCKET=pessoal
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

# AI — Ollama no host + gateway /iara + timeouts de roteamento (ver .env.example completo)
# OLLAMA_ENABLED=true
# OLLAMA_BASE_URL=http://172.23.0.1:11434
# OLLAMA_MODEL=qwen3.5:4b
# OLLAMA_THINK=false
# OLLAMA_TIMEOUT=20
# AI_PROMPT_LONG_THRESHOLD=2000
# AI_OLLAMA_TIMEOUT_SIMPLE=45
# IARA_INTERNAL_KEY=
# IARA_GATEWAY_URL=   # só no cliente que chama a API remota

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

- Branch `main` → deploy automático (GitHub Actions conforme workflow do repositório)
- Na VPS, o script `**deploy.sh**` (em `/home/deploy/raphael-hub`): `git pull`, classifica arquivos alterados e decide **rebuild de imagem** (Dockerfile / Composer), **build de front** (`resources/`, `vite`, `package.json`), **migration** só se mudou `database/migrations/`, refresh de cache Laravel, **health checks** (`/up`, MinIO, Evolution, Playwright opcional), **prune** de imagens/builder
- Quando há mudança estrutural de imagem: `docker compose build` seletivo + `up -d` dos serviços necessários; caso contrário **restart leve** dos serviços base do app
- `composer install` / `npm ci && npm run build` dentro do container `**app`** apenas quando o diff exige
- Caches: `php artisan optimize:clear` seguido de `config:cache`, `route:cache`, `view:cache` após deploy

---

## Estrutura de Diretórios Laravel

```
app/
  Http/Controllers/
    IaraController.php
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
    AiRouterService.php
    NeuronAIService.php
    OllamaService.php
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

---

## Livewire (Fase 4.1)

- Pacote instalado: `livewire/livewire` v4.
- Layouts Blade base (`resources/views/layouts/app.blade.php` e `resources/views/layouts/guest.blade.php`) preparados com `@livewireStyles` e `@livewireScripts`.
- Smoke test da instalação: `tests/Feature/Livewire/LivewireInstallationTest.php` valida disponibilidade do pacote Livewire no container da aplicação.

## Dashboard Threads (Fase 4.2)

- Rota autenticada inicial: `GET /hub/threads` (`threads.hub`) renderizada por `App\Livewire\Threads\HubPage`.
- Navegação principal (desktop/mobile) atualizada com link para `Threads Hub`.
- Primeira entrega da UI:
  - abas `Sources`, `Review`, `Published` (estado em query string `?tab=`),
  - tabela inicial de `threads_sources` (tipo, label, alvo, status, último scrape),
  - placeholders de `Review`/`Published` para evolução nos próximos blocos.
- Cobertura mínima:
  - `tests/Feature/Threads/ThreadsHubPageTest.php` valida acesso autenticado e renderização da listagem de fontes.

## Dashboard Threads (Fase 5.1 — Sources management inicial)

- Componente `App\Livewire\Threads\HubPage` evoluído com ações de gestão de `threads_sources`:
  - criação de source `keyword` ou `url`,
  - alternância de status `is_active`,
  - ação `scrape agora` para enfileirar o job compatível com o tipo.
- Regras de enfileiramento:
  - `keyword` -> `ScrapeThreadsKeywordJob` (`onlyNew=true`, `knownPostIds` da própria source, limite padrão via env),
  - `url` -> `ScrapeThreadsUrlJob`.
- UI de `Sources` inclui formulário de criação e botões de ação por linha (toggle/scrape).
- Cobertura mínima:
  - `tests/Feature/Threads/ThreadsHubPageTest.php` cobre criação, toggle e dispatch dos jobs por ação Livewire.

## Pipeline IA Threads (ajuste de robustez)

- `ClassifyCommentsJob` passa a processar **1 comentário por execução** (`commentId`, opcional `force`) para reduzir travamentos em lote e permitir reprocessamento manual pontual.
- `DispatchPendingThreadsClassificationJob` criado para disparar classificação de pendentes (`ai_summary` nulo), com espaçamento entre jobs para respeitar capacidade/cota de providers.
- `THREADS_AI_DISPATCH_SPACING_SECONDS` controla a cadência dos dispatches no encadeamento da classificação.

## Dashboard Threads (Fase 5.2 — Review inicial)

- Aba `Review` no `HubPage` agora lista comentários com:
  - ordenação priorizando `pending_review` e `ignored`,
  - filtro por status (`all`, `pending_review`, `ignored`),
  - indicação visual para itens `ignored`.
- Ações manuais por comentário:
  - `reclassifyComment` (força novo `ClassifyCommentsJob` por id),
  - `moveCommentToPendingReview`,
  - `ignoreComment`,
  - `toggleCommentPublic`.
- Operação manual de backlog:
  - botão para `dispatchPendingClassification` (enfileira `DispatchPendingThreadsClassificationJob` com batch size configurável na tela).

## Dashboard Threads (Fase 5.2 — Review curation consolidada)

- A aba `Review` passa a suportar **curadoria em lote** com seleção múltipla de comentários (`selectedReviewCommentIds`).
- Ações batch disponíveis no `HubPage`:
  - `batchMoveSelectedToPendingReview`,
  - `batchIgnoreSelected`,
  - `batchPublishSelected`,
  - `batchUnpublishSelected`,
  - `batchReclassifySelected` (1 `ClassifyCommentsJob` com `force=true` por comentário selecionado).
- Filtros adicionais de produtividade:
  - `status`,
  - `categoria`,
  - `source`,
  - somente itens sem `ai_summary`.
- Ordenação configurável:
  - relevância IA (`ai_relevance_score`),
  - mais novo (`created_at`),
  - score (`score_total`).
- Cobertura mínima atualizada em `tests/Feature/Threads/ThreadsHubPageTest.php` para batch actions, filtros avançados e regressão de reclassificação manual em lote.

## Dashboard Threads (Fase 5.3 — Published management)

- Aba `Published` no `HubPage` lista comentários com `is_public=true` (limite de 100), com relacionamentos `post.source` e `category` para contexto operacional.
- Query string:
  - `pub_category` — filtro por `threads_category_id` (`all` ou id),
  - `pub_source` — filtro por `threads_posts.threads_source_id` (`all` ou id),
  - `pub_sort` — ordenação: `score` (`score_total`), `newest` (`updated_at`), `relevance` (`ai_relevance_score`).
- Estado de edição rápida por linha em `publishedForms[commentId]` (`ai_summary`, `threads_category_id`, `is_featured`), inicializado ao renderizar itens visíveis.
- Ações:
  - `savePublishedComment` — persiste `ai_summary`, categoria e `is_featured` para comentário ainda publicado,
  - `unpublishPublishedComment` — define `is_public=false` e remove o estado local da linha.
- Métricas exibidas: `upvotes`, `downvotes`, `score_total` (atualizados pelo fluxo de votos públicos + `RecalculateCommentScoreJob`).
- Cobertura mínima em `tests/Feature/Threads/ThreadsHubPageTest.php`: somente públicos na aba, save de campos rápidos e despublicar.

## Dashboard Threads — Polimento UX IA + seleção em massa (Review)

- Contador global de comentários **sem classificação** alinhado ao job: `threads_comments.ai_summary IS NULL` (mesmo critério de `DispatchPendingThreadsClassificationJob` com `force=false`).
- Exibição no Hub (abas Sources e Review) do total pendente, estimativa `min(batch configurado, pendentes)` para o próximo disparo e cadência da fila `ai` via `config('services.threads.ai_dispatch_spacing_seconds')` (`THREADS_AI_DISPATCH_SPACING_SECONDS` no `.env`).
- Ao disparar classificação pendente, flash descreve quantos jobs foram enfileirados neste clique, batch, espaçamento e pendentes restantes estimados.
- Aba Review: controle **selecionar todos nesta página** (mesmos filtros/ordenação da tabela, limite 100), método `toggleSelectAllReviewOnPage`.
- Testes em `tests/Feature/Threads/ThreadsHubPageTest.php` para batch size do job e toggle de seleção.

## Página pública Oportunidades (Fase 6 — listagem SSR inicial)

- Rota pública: `GET /oportunidades`, nome `threads.opportunities` (sem autenticação).
- Controller invokável `App\Http\Controllers\ThreadsOpportunitiesController`: lista apenas `threads_comments` com `is_public=true`, com `category`, `post` e `post.source`.
- Query string suportada: `q` (busca em `ai_summary` e `content`, `LIKE` compatível com SQLite em testes), `category` (id), `source` (id da `threads_sources` via post), `sort` (`relevance` | `votes` | `newest`).
- Paginação: 20 itens por página; ordenação padrão por `ai_relevance_score` descendente.
- Views: `resources/views/threads/opportunities.blade.php` estendendo `layouts.public` (header mínimo, link Entrar/Dashboard conforme sessão).
- Cobertura: `tests/Feature/Threads/ThreadsOpportunitiesPageTest.php`.

## Página pública — Votação anônima e score (Fase 7 — MVP)

- Rota `POST /oportunidades/votos/{comment}`, nome `threads.opportunities.vote`, throttle `120,1`, sem autenticação.
- `ThreadsCommentVoteController::store` aceita apenas comentários `is_public=true` (caso contrário 404); body `direction` = `up` | `down`.
- `App\Services\Threads\ThreadsVoteFingerprintService`: `session_fingerprint` = `hash('sha256', ip|user_agent|Y-m-d|salt)` com `config('services.threads.vote_fingerprint_salt')` (`THREADS_VOTE_FINGERPRINT_SALT`).
- Persistência em `threads_comment_votes` com `updateOrCreate` por `(threads_comment_id, session_fingerprint)`; `vote` ∈ `{1, -1}`.
- `App\Jobs\RecalculateCommentScoreJob` recalcula contagens e `score_total = upvotes - downvotes` no `threads_comments` correspondente.
- UI em `threads/opportunities.blade.php`: botões +1 / -1 com `@csrf`.
- Cobertura: `tests/Feature/Threads/ThreadsCommentVoteTest.php`.

