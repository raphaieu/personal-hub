# SPEC — Raphael Hub

Este arquivo é o **índice técnico**. Cobre stack, infraestrutura básica, schema transversal e ponteiros para as SPECs por módulo. Detalhes de cada feature vivem em `docs/<modulo>/SPEC.md`.

## Stack

| Camada | Tecnologia |
|--------|------------|
| Backend | Laravel 13 + PHP 8.4 |
| Frontend | Blade + Livewire 4 + Sanctum |
| Banco | PostgreSQL 17 com `pgvector` instalado |
| Cache / Filas / Sessão | Redis 7 (container `raphael-redis`) |
| Monitor de filas | Laravel Horizon |
| Storage | MinIO S3 (`raphael-minio`, bucket configurável — típico `pessoal`) |
| Scraping | Node 24 + Playwright (container `raphael-playwright`) |
| CAPTCHA | CapSolver (reCAPTCHA v3 — apenas Coelba) |
| IA | NeuronAI — Ollama (host, gateway Docker) → Groq → Anthropic → OpenAI |
| WhatsApp | Evolution API em stack Docker própria (instância `raphael`) |
| Infra | Docker Compose + aaPanel Nginx + GitHub Actions |

---

## SPECs por módulo

| Módulo | SPEC |
|--------|------|
| Core (banco transversal, IA, dashboard, autenticação) | [docs/core/SPEC.md](docs/core/SPEC.md) |
| Inbox WhatsApp (webhook, jobs, `message_logs`) | [docs/whatsapp/SPEC.md](docs/whatsapp/SPEC.md) |
| Faturas Embasa/Coelba (scraping, `InvoiceService`, schedule) | [docs/utilities/SPEC.md](docs/utilities/SPEC.md) |
| Threads e Oportunidades | [docs/threads/SPEC.md](docs/threads/SPEC.md) |
| Eventos privados | [docs/events/SPEC.md](docs/events/SPEC.md) |
| Álbuns de mídia | [docs/album/SPEC.md](docs/album/SPEC.md) |
| Operações (Docker, deploy, Playwright, limites Nginx/PHP) | [docs/operations/SPEC.md](docs/operations/SPEC.md) |

Backlog em [docs/roadmap/BACKLOG.md](docs/roadmap/BACKLOG.md). Histórico em [CHANGELOG.md](CHANGELOG.md).

---

## Banco — convenções gerais

- Nomes de **tabelas e colunas em inglês**, alinhados ao código Laravel. Labels e mensagens de usuário em pt-BR na aplicação.
- Migrations sempre com `down()` implementado.
- `pgvector` está **instalado e disponível** no Postgres do hub; uso ativo será adicionado quando o módulo de RAG/busca semântica entrar (ver [BACKLOG](docs/roadmap/BACKLOG.md)).
- Schemas pensados para evoluções futuras (V2 de grupos, permissões) já incluem colunas opcionais — detalhes nas SPECs por módulo.

### Tabelas transversais

Estas tabelas são compartilhadas entre módulos e estão documentadas em [docs/core/SPEC.md](docs/core/SPEC.md):

- `users` (com `global_role`)
- `analysis_profiles` (profiles reutilizáveis de classificação IA)
- `monitored_sources` (fontes WhatsApp — self/contact/group)
- `monitored_source_user` (pivot para permissões por grupo, backlog)

Tabelas específicas de módulo:

- WhatsApp: `message_logs`, `message_attachments`, `reminders` → [docs/whatsapp/SPEC.md](docs/whatsapp/SPEC.md)
- Faturas: `utility_accounts`, `invoices` → [docs/utilities/SPEC.md](docs/utilities/SPEC.md)
- Threads: `threads_sources`, `threads_posts`, `threads_comments`, `threads_categories`, `threads_comment_votes` → [docs/threads/SPEC.md](docs/threads/SPEC.md)
- Eventos: `events`, `referral_links`, `guests` → [docs/events/SPEC.md](docs/events/SPEC.md)
- Álbuns: `albums`, `album_media`, `contributors`, `access_attempts`, `album_lockouts` → [docs/album/SPEC.md](docs/album/SPEC.md)

---

## Filas (Redis via Horizon)

Configuração em `config/horizon.php`. Workers em containers dedicados (`raphael-horizon`, `raphael-queue`).

| Fila | Uso |
|------|-----|
| `default` | Jobs gerais (`VerificarStatusFaturas`, `EnriquecerUrlLembrete`, `RecalculateCommentScoreJob`). |
| `scraping` | Playwright (`ScrapeConta`, `ScrapeThreadsUrlJob`, `ScrapeThreadsKeywordJob`) — timeout longo (120s). |
| `notifications` | Envios WhatsApp e digests (`NotificarVencimento`, `SendAlbumContributionDigestJob`). |
| `ai` | Classificação IA (`ProcessPersonal/Contact/GroupWhatsAppMessage`, `ClassifyCommentsJob`, `ReprocessMessageLogAnalysisJob`, `DispatchPendingThreadsClassificationJob`). |
| `media` | Processamento pesado de mídia (`ProcessAlbumPhotoJob` — GD/WebP). |

Padrão para todos os jobs: `$tries = 3`, `failed()` implementado, logs estruturados.

---

## Schedule

Definido em `bootstrap/app.php` (`withSchedule`). Em produção, um worker rodando `php artisan schedule:work`.

```php
$schedule->job(new ScrapeConta('embasa'))->dailyAt('08:00');
$schedule->job(new ScrapeConta('coelba'))->dailyAt('08:05');
$schedule->job(new VerificarStatusFaturas())->dailyAt('09:00');
$schedule->job(new NotificarVencimento())->dailyAt('09:30');
$schedule->command('albums:prune-local-staging')->weeklyOn(1, '03:30');
```

Detalhes da lógica de cada job em [docs/utilities/SPEC.md](docs/utilities/SPEC.md) e [docs/album/SPEC.md](docs/album/SPEC.md).

---

## Autenticação

- Laravel Breeze (Blade stack), sem registro público.
- Login restrito por e-mail listado em `HORIZON_AUTH_EMAILS`.
- Horizon (`/horizon`) protegido pelo mesmo middleware.
- Dashboard hub (`/dashboard`) e todos os hubs internos (`/hub/*`) exigem autenticação.

---

## Variáveis de Ambiente — overview

O `.env.example` é **calibrado para produção** (Docker VPS, hostnames `raphael-postgres`, `raphael-redis`, etc.). Cada bloco inclui comentários com sobrescritos para dev local. Tabela completa de overrides em [docs/operations/SPEC.md](docs/operations/SPEC.md).

Blocos principais (detalhes em cada SPEC por módulo):

- `APP_*`, `DB_*`, `REDIS_*`, `MAIL_*` — base Laravel.
- `EMBASA_*`, `COELBA_*`, `CAPSOLVER_API_KEY` — concessionárias.
- `EVOLUTION_URL`, `EVOLUTION_API_KEY`, `EVOLUTION_INSTANCE`, `WHATSAPP_*_JID` — WhatsApp.
- `AWS_*` (MinIO) — storage.
- `OLLAMA_*`, `GROQ_*`, `ANTHROPIC_*`, `OPENAI_*`, `AI_*`, `IARA_*` — IA.
- `THREADS_*` — scraping autenticado.
- `EVENTS_*` — Turnstile, rate limit, e-mail de eventos.
- `ALBUMS_*` — limites de upload e contribuição.

---

## Estrutura de diretórios

```
app/
  Contracts/                  # ThreadsScraperClientInterface, UtilityScraperClientInterface
  Http/Controllers/           # IaraController, WebhookController, Dashboard, Threads, Events…
  Jobs/                       # Process*WhatsAppMessage, ScrapeConta, NotificarVencimento, ClassifyCommentsJob…
  Livewire/                   # Threads/HubPage, Utilities/HubPage, Albums/*, Events/*, MonitoredSources, AnalysisProfiles
  Models/                     # MonitoredSource, MessageLog, UtilityAccount, Invoice, ThreadsSource…
  Services/
    AiRouterService.php
    NeuronAIService.php
    OllamaService.php
    WebhookRouterService.php
    EvolutionService.php
    InvoiceService.php
    Analysis/                 # AnalysisProfileResolver, AnalysisExecutionService, adapters
    Threads/                  # ThreadsPlaywrightService, FakeThreadsScraperClient, ThreadsClassificationService
    Utilities/                # UtilityPlaywrightService, FakeUtilityScraperClient
    Albums/                   # AlbumService, AlbumMediaUploadService, AlbumAccessService…
    Events/                   # serviços de evento, geração de PDF/QR
  Support/
    UtilityScrapeWindow.php
    UtilityAccountScrapeGate.php
    UtilityInvoiceDisk.php
    AlbumUploadLimits.php

config/
  hub_dashboard.php           # cards do /dashboard
  ai.php                      # timeouts e thresholds da camada IA
  horizon.php                 # supervisores por fila
  services.php                # bind de providers IA, evolution, threads, utilities, albums

resources/
  views/
    dashboard.blade.php
    layouts/{app,guest,public}.blade.php
    components/hub/dashboard-icon.blade.php
    livewire/threads/hub/*.blade.php
    pdf/events/*.blade.php
    threads/opportunities.blade.php

database/
  migrations/
  seeders/                    # SuperAdminUserSeeder, ThreadsCategorySeeder, InitialDataSeeder

playwright/
  server.js
  src/
    embasa-scraper.js
    coelba-scraper-v2.js
    threads/*.js

docker/
  nginx/default.conf          # client_max_body_size alinhado ao PHP
  php/zz-uploads.ini          # max_file_uploads, post_max_size, memory_limit

.github/workflows/deploy.yml
```

---

## CI/CD

- Branch `main` → deploy automático via GitHub Actions.
- Na VPS, `deploy.sh` em `/home/deploy/raphael-hub` faz `git pull`, classifica arquivos alterados e decide rebuild de imagem, build de front, migration (só se `database/migrations/` mudou), refresh de cache Laravel e health checks (`/up`, MinIO, Evolution, Playwright opcional).
- Caches em produção: `php artisan optimize:clear` → `config:cache` → `route:cache` → `view:cache`.

Detalhes completos do pipeline e da imagem PHP/Nginx em [docs/operations/SPEC.md](docs/operations/SPEC.md).
