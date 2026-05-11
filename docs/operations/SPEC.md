# SPEC — Operações

Tudo sobre infraestrutura, deploy, Docker, Playwright em runtime, dev local e limites de Nginx/PHP. O que muda com baixa frequência mas precisa estar centralizado em vez de espalhado pelos módulos.

---

## VPS

- Hostinger — Ubuntu 24 — 8 cores, 32 GB RAM, ~380 GB SSD.
- aaPanel gerencia Nginx host e SSL (Cloudflare).
- Usuário de deploy: `deploy` (uid=1003, gid=1003).
- Path do projeto: `/home/deploy/raphael-hub`.

---

## Domínios

- `api.raphael-martins.com` — API/hub principal. Proxy aaPanel → `raphael-nginx:80` (porta host 8082).
- `files.raphael-martins.com` — MinIO público (proxy para `raphael-minio` API 9000).
- `evo.raphael-martins.com` — Evolution API (instância `raphael`).
- `dev.raphael-martins.com` — Cloudflare Tunnel para webhooks/testes externos em dev.

DNS gerenciado na Cloudflare. Registros A apontando para o IP da VPS.

---

## Docker

### Rede e containers

- Network: `raphael-bridge` (isolada dos demais projetos).
- Gateway típico da bridge na VPS: `172.23.0.1` (usado pelo app para alcançar Ollama no host).
- `PUID=1003` / `PGID=1003` em todos os containers Laravel.

Containers principais:

| Container | Papel |
|-----------|-------|
| `raphael-app` | PHP-FPM (Laravel). |
| `raphael-nginx` | Nginx do Compose (à frente do app). |
| `raphael-postgres` | PostgreSQL 17 com pgvector. |
| `raphael-redis` | Redis 7. |
| `raphael-horizon` | Worker Horizon (filas). |
| `raphael-queue` | Worker queue adicional (opcional). |
| `raphael-scheduler` | `php artisan schedule:work`. |
| `raphael-playwright` | Node 24 + Playwright (porta interna 3001). |
| `raphael-minio` | MinIO S3 dedicado. |
| `raphael-evolution` | Evolution API. |
| `raphael-evolution-postgres` | Postgres da Evolution (estado). |
| `raphael-evolution-redis` | Redis da Evolution (cache). |

Todos os containers Laravel usam a imagem `raphael-hub:latest`.

### Imagem PHP (`Dockerfile`)

Inclui:

- `docker/php/zz-uploads.ini` — limites multipart (`max_file_uploads`, `upload_max_filesize`, `post_max_size`, `memory_limit`, tempos de execução alinhados a uploads em lote).
- Extensão PHP `zip` (já existia).
- Pacotes do SO: `ffmpeg`, `zip`, `unzip` — preparação para fase F de álbuns (código ainda pode não usar).
- Extensões para o PostgreSQL com pgvector (`pdo_pgsql`, `pgsql`).

### Nginx do Compose

`docker/nginx/default.conf` define `client_max_body_size` alinhado ao `post_max_size` do PHP.

### Limites de upload — cadeia completa

Para upload de álbuns (lotes grandes), três camadas precisam estar alinhadas:

1. **PHP** (container app) — `docker/php/zz-uploads.ini`.
2. **Nginx do Compose** — `docker/nginx/default.conf` (`client_max_body_size`).
3. **Nginx do host (aaPanel)** — o vhost que faz proxy para `raphael-nginx`. **Também precisa do mesmo limite de body**, ou uploads grandes falham no host mesmo com PHP/Nginx ajustados.

Mudanças em `Dockerfile`, `docker/php/*.ini` ou `docker/nginx/default.conf` exigem:

```bash
docker compose build app
docker compose up -d            # serviços app, horizon, queue, scheduler, nginx
```

### MinIO

- Container `raphael-minio`. API S3 na porta interna **9000**; no host costuma estar em `127.0.0.1:19000` (API) e `127.0.0.1:19001` (console).
- Exposição pública via proxy: `https://files.raphael-martins.com`.
- Dentro do Compose o Laravel usa hostname `minio` (ex.: `AWS_ENDPOINT=http://minio:9000`).
- Bucket padrão: `pessoal`.
- A Evolution API pode usar o mesmo MinIO para mídia (variáveis `S3_*` na stack da Evolution).

### Evolution API

- Container `raphael-evolution`. API própria.
- Estado em `raphael-evolution-postgres`, cache em `raphael-evolution-redis`.
- URL pública conforme `EVOLUTION_URL` no `.env` (baseline: `https://evo.raphael-martins.com`).
- Webhook do Laravel: `POST /webhook/whatsapp`.

### Ollama (host — fora do Compose)

- Rodando no host via systemd.
- Porta `11434`, bind típico `0.0.0.0` só para aceitar tráfego da bridge.
- Dos containers Laravel, URL típica: `OLLAMA_BASE_URL=http://172.23.0.1:11434`.
- **Firewall (UFW):** permitir `172.23.0.0/16 → tcp/11434`. Não expor publicamente.

---

## Playwright em runtime

### Container

`raphael-playwright` (Node 24). Atualizar imagem/serviço **`raphael-playwright`** após mudanças em `playwright/src/*.js` (rebuild `Dockerfile.playwright` / imagem do serviço `playwright`).

### Endpoints

Documentados nas SPECs de cada uso:

- Utilidades: [docs/utilities/SPEC.md](../utilities/SPEC.md) (Embasa, Coelba).
- Threads: [docs/threads/SPEC.md](../threads/SPEC.md) (auth, scrape URL, scrape keyword).
- Health: `GET /health` — sempre disponível.

### Sessões persistidas

Cada scraper mantém seu `storageState`:

- `EMBASA_SESSION_PATH` (default `/app/storage/embasa-session.json`).
- `COELBA_SESSION_PATH` (default `/app/storage/coelba-session.json`).
- `THREADS_SESSION_PATH` (default `/app/storage/threads-session.json`).

---

## CI/CD

### GitHub Actions

Branch `main` → deploy automático via workflow em `.github/workflows/deploy.yml`.

### `deploy.sh` na VPS

Em `/home/deploy/raphael-hub`. Fluxo:

1. `git pull`.
2. Classifica arquivos alterados.
3. Decide:
   - **Rebuild de imagem** se mudou `Dockerfile` / `composer.json` / `composer.lock`.
   - **Build de front** se mudou `resources/` / `vite.config.*` / `package.json`.
   - **Migration** só se mudou `database/migrations/`.
   - **Rebuild Playwright** se mudou `playwright/`.
4. Refresh de cache Laravel: `php artisan optimize:clear` → `config:cache` → `route:cache` → `view:cache`.
5. Health checks: `/up`, MinIO, Evolution, Playwright (opcional).
6. Prune de imagens/builder ao final.

Quando há mudança estrutural de imagem: `docker compose build` seletivo + `up -d` dos serviços necessários. Caso contrário, restart leve dos serviços base.

`composer install` / `npm ci && npm run build` rodam dentro do container `app` apenas quando o diff exige.

---

## Desenvolvimento local

### Opção A — `docker compose` deste repositório

Compose completo do repo. Bom para isolamento total.

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

App: `http://localhost:8082` · Horizon: `http://localhost:8082/horizon` · Playwright: `http://localhost:3001`.

Ajustes típicos em relação ao `.env.example` de produção:

| Variável | Local |
|----------|-------|
| `APP_ENV` | `local` |
| `APP_DEBUG` | `true` |
| `APP_URL` | `http://localhost:8082` |
| `LOG_LEVEL` | `debug` |
| `SESSION_ENCRYPT` | `false` (HTTP puro) |
| `QUEUE_CONNECTION` | `redis` (com Horizon) ou `sync` para depuração rápida |

`DB_HOST=raphael-postgres` e `REDIS_HOST=raphael-redis` já batem com o compose.

### Opção B — infra externa + vhost `hub.test`

Quando Postgres 17, Nginx, Redis 7, Mailpit (e opcionalmente MySQL 8.4 para outros projetos) rodam num **compose de infra separado** e o Laravel executa no host (ou em outro container) com vhost `hub.test`:

| Variável | Produção | Local (hub.test) |
|----------|----------|------------------|
| `APP_ENV` | `production` | `local` |
| `APP_DEBUG` | `false` | `true` |
| `APP_URL` | `https://api.raphael-martins.com` | `http://hub.test` ou `https://hub.test` |
| `LOG_LEVEL` | `error` ou `warning` | `debug` |
| `SESSION_ENCRYPT` | `true` | `false` em HTTP puro |
| `DB_HOST` | `raphael-postgres` | `127.0.0.1` se a porta Postgres estiver publicada |
| `DB_PORT` | `5432` | porta mapeada pelo compose de infra |
| `REDIS_HOST` | `raphael-redis` | `127.0.0.1` ou nome do serviço Redis na rede compartilhada |
| `QUEUE_CONNECTION` | `redis` | `redis` (com Horizon) ou `sync` para debug |
| `MAIL_MAILER` | `log` ou SMTP real | `smtp` (Mailpit) |
| `MAIL_HOST` / `MAIL_PORT` | conforme provedor | `127.0.0.1:1025` (Mailpit), `MAIL_ENCRYPTION=null` |

**Importante:** o Raphael Hub usa **apenas PostgreSQL** (`DB_CONNECTION=pgsql`). MySQL no compose de infra é para outros projetos.

Mailpit: UI de inspeção na porta exposta pelo compose (ex.: 8025). Se o PHP não alcança `127.0.0.1` do host (rodando em container), usar o hostname do serviço Mailpit na rede Docker compartilhada.

### Playwright local

O Laravel chama o servidor Node por URL configurável (`PLAYWRIGHT_SERVICE_URL`):

- Produção: `http://raphael-playwright:3001` (rede Docker).
- Local com compose deste repo: mesmo hostname (mesma rede).
- Local com infra externa: `http://127.0.0.1:3001` (porta publicada) ou hostname do container na rede compartilhada.

Em dev, recomendado `PLAYWRIGHT_HEADLESS=false` para debug de seletores.

---

## Logs e observabilidade

- Logs estruturados de IA: `ai.completion`, `ai.completion_failure`.
- Logs de webhook (em `APP_DEBUG=true`): `correlation_id` + `routing`.
- Logs de scraping com timestamp por step (debug de seletores).
- Logs de utilities: `utilities.scrape_conta.skipped_idempotent`, `utilities.invoice_disk.*`.
- Horizon em `/horizon` para inspeção de filas (mesmo middleware do hub).

---

## Pontos de atenção operacionais

Estes são os "pegadinhas" que precisam estar em runtime, repetidos do [LLM.md](../../LLM.md) para acessibilidade:

1. **Permissões de storage** após `docker compose up`: `chown -R deploy:deploy storage bootstrap/cache`.
2. **MinIO path style** obrigatório: `AWS_USE_PATH_STYLE_ENDPOINT=true`.
3. **Postgres no Docker** dos containers: `DB_HOST=raphael-postgres`. Com infra externa no host: `127.0.0.1` + porta mapeada.
4. **Nginx aaPanel**: precisa do mesmo `client_max_body_size` que o container Nginx para uploads grandes.
5. **Rebuild Playwright** quando muda `playwright/src/*.js`: rebuild da imagem do serviço.

---

## Migração e housekeeping

### Comandos úteis

```bash
# Hardening profiles
php artisan analysis:repair-profile-linkage

# Manutenção álbuns
php artisan albums:prune-local-staging --dry-run
```
