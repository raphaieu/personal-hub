# Raphael Hub

> Plataforma pessoal de automação operada via WhatsApp + dashboard web, com IA híbrida (Ollama local + nuvem) e infraestrutura própria.

O hub centraliza tarefas reais do dia a dia: monitoramento de contas (Embasa/Coelba), inbox pessoal de WhatsApp, eventos privados com ingresso/portaria, álbuns de mídia com viewer público, e curadoria de oportunidades a partir do Threads. Usa o WhatsApp como interface natural e o sistema como cérebro operacional.

Para entender o produto, ler [PRD.md](PRD.md). Para detalhes técnicos, [SPEC.md](SPEC.md) (índice) e SPECs por módulo em [docs/](docs/). Para o que ainda vem, [docs/roadmap/BACKLOG.md](docs/roadmap/BACKLOG.md).

---

## Stack

| Camada | Tecnologia |
|--------|------------|
| Backend | Laravel 13 + PHP 8.4 |
| Frontend | Blade + Livewire 4 + Sanctum |
| Banco | PostgreSQL 17 (com `pgvector`) |
| Cache / Filas / Sessão | Redis 7 + Laravel Horizon |
| Storage | MinIO S3 próprio (`raphael-minio`, bucket `pessoal`) |
| Scraping | Node 24 + Playwright (container dedicado) |
| WhatsApp | Evolution API em stack Docker própria |
| IA | NeuronAI: Ollama (host) → Groq → Anthropic → OpenAI |
| Infra | Docker Compose + aaPanel Nginx proxy + GitHub Actions |

---

## Módulos

Cada módulo tem SPEC dedicada com schema, contratos, endpoints e fluxos.

- **Faturas (Embasa/Coelba)** — scraping agendado, PDF no MinIO, lembrete WhatsApp no grupo da casa. Ver [docs/utilities/SPEC.md](docs/utilities/SPEC.md).
- **Inbox pessoal WhatsApp** — webhook Evolution, roteamento por origem (self/contact/group), persistência em `message_logs`, análise text-first profile-driven. Ver [docs/whatsapp/SPEC.md](docs/whatsapp/SPEC.md).
- **Threads e Oportunidades** — scraping autenticado, classificação IA por profile, curadoria no hub, feed público em `/oportunidades` com votação anônima. Ver [docs/threads/SPEC.md](docs/threads/SPEC.md).
- **Eventos privados** — hub `/hub/events`, registro público com confirmação por e-mail, ingresso PDF com QR, portaria `/events-checkin`. Ver [docs/events/SPEC.md](docs/events/SPEC.md).
- **Álbuns de mídia** — hub `/hub/albums`, viewer público com lockout, contribuição externa por convite. Ver [docs/album/SPEC.md](docs/album/SPEC.md).
- **Camada de IA** — `NeuronAIService` + `AiRouterService` (Ollama → Groq → Anthropic → OpenAI), gateway `POST /iara`, análise profile-driven (`analysis_profiles`). Ver [docs/core/SPEC.md](docs/core/SPEC.md) (seção *IA*).

---

## Ambientes

- **Produção:** `https://hub.raphael-martins.com` — deploy automatizado via GitHub Actions, `deploy.sh` na VPS com rebuild seletivo.
- **MinIO público:** `https://files.raphael-martins.com` (proxy para o `raphael-minio`).
- **Dev local:** `http://hub.test` (vhost com infra externa) ou `http://localhost:8082` (compose do repo).
- **Tunnel dev:** `https://dev.raphael-martins.com` para webhooks e testes externos.

Detalhes operacionais (proxy aaPanel, Cloudflare Tunnel, deploy, limites Nginx/PHP para upload) em [docs/operations/SPEC.md](docs/operations/SPEC.md).

---

## Como rodar localmente

Pré-requisitos: Docker, Docker Compose, Node 24, PHP 8.4 (apenas se for rodar Composer fora do container).

### Opção A — `docker compose` deste repositório

```bash
cp .env.example .env
# Ajustar para dev local: APP_ENV=local, APP_DEBUG=true,
# APP_URL=http://localhost:8082, LOG_LEVEL=debug.
# Os hosts DB_HOST=raphael-postgres e REDIS_HOST=raphael-redis
# já batem com o compose.

docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# App:     http://localhost:8082
# Horizon: http://localhost:8082/horizon
```

Testar o scraper Playwright direto:

```bash
curl -X POST http://localhost:3001/embasa/scrape
```

### Opção B — infra externa + vhost `hub.test`

Quando Postgres 17, Redis 7 e Mailpit rodam num compose de infra separado e o Laravel está no host (ou em outro container) com vhost `hub.test`, sobrescreva `APP_URL`, `DB_HOST`, `REDIS_HOST` e `MAIL_*` no `.env` conforme a tabela em [docs/operations/SPEC.md](docs/operations/SPEC.md). O Raphael Hub usa **apenas PostgreSQL** (`DB_CONNECTION=pgsql`); MySQL na mesma stack é para outros projetos.

---

## Documentação

- [PRD.md](PRD.md) — visão de produto e módulos atuais.
- [SPEC.md](SPEC.md) — índice técnico (banco, IA, integrações transversais).
- [LLM.md](LLM.md) — convenções e regras de código para agentes (Codex, Cursor, Claude Code).
- [CHANGELOG.md](CHANGELOG.md) — histórico de mudanças.
- [docs/roadmap/BACKLOG.md](docs/roadmap/BACKLOG.md) — backlog de evolução.
- [docs/operations/SPEC.md](docs/operations/SPEC.md) — infra, deploy, limites Docker/Nginx/PHP, sessão Playwright.

SPECs por módulo:

- [docs/core/SPEC.md](docs/core/SPEC.md) — banco, IA, `analysis_profiles`, `monitored_sources`.
- [docs/whatsapp/SPEC.md](docs/whatsapp/SPEC.md) — webhook, jobs, `message_logs`.
- [docs/utilities/SPEC.md](docs/utilities/SPEC.md) — Embasa/Coelba, `InvoiceService`, schedule.
- [docs/threads/SPEC.md](docs/threads/SPEC.md) — scraping, classificação, hub, feed público.
- [docs/events/SPEC.md](docs/events/SPEC.md) — registro, confirmação, ingresso, portaria.
- [docs/album/SPEC.md](docs/album/SPEC.md) — hub, viewer, contribuição externa.
