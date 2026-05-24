# LLM.md — Raphael Hub

Instruções para agentes de IA (Codex, Gemini, Claude Code, Cursor, etc.) ao trabalhar neste projeto.
Ler este arquivo inteiro antes de escrever qualquer código.

---

## O que é este projeto

Plataforma pessoal de automação operada via WhatsApp + dashboard web. Módulos atuais: faturas Embasa/Coelba, inbox WhatsApp, Threads e Oportunidades, eventos privados, álbuns de mídia. Detalhes em [PRD.md](PRD.md).

**Documentos de referência obrigatórios:**

- [PRD.md](PRD.md) — visão de produto e descrição dos módulos atuais.
- [SPEC.md](SPEC.md) — índice técnico (banco transversal, filas, schedule, autenticação).
- [docs/core/SPEC.md](docs/core/SPEC.md) — IA (NeuronAI, AiRouter, Iara), `analysis_profiles`, `monitored_sources`, dashboard.
- [docs/whatsapp/SPEC.md](docs/whatsapp/SPEC.md) — webhook Evolution, `WebhookRouterService`, `message_logs`, jobs de análise.
- [docs/utilities/SPEC.md](docs/utilities/SPEC.md) — Embasa/Coelba, Playwright, `InvoiceService`, schedule.
- [docs/threads/SPEC.md](docs/threads/SPEC.md) — scraping autenticado, classificação, hub, feed público.
- [docs/events/SPEC.md](docs/events/SPEC.md) — eventos privados, registro, ingresso PDF/QR, portaria.
- [docs/album/SPEC.md](docs/album/SPEC.md) — álbuns, viewer, contribuição externa.
- [docs/operations/SPEC.md](docs/operations/SPEC.md) — Docker, deploy, Playwright, limites Nginx/PHP, dev local.
- [docs/roadmap/BACKLOG.md](docs/roadmap/BACKLOG.md) — backlog de evolução; não duplicar nas SPECs.

---

## Stack e versões

Versões abaixo refletem o **ambiente de desenvolvimento local** atual. Produção pode fixar minors no Dockerfile/CI.

- **PHP**: 8.4 — features modernas (readonly, enums, fibers, match, named args).
- **Laravel**: 13 — sem sintaxe de versões anteriores.
- **Livewire**: 4 — sem Livewire 3.
- **Node**: 24 — runtime do serviço Playwright.
- **PostgreSQL**: 17 com `pgvector` instalado — **não** usar MySQL para o Raphael Hub.
- **NeuronAI**: pacote `neuron-core/neuron-ai` — não usar SDK de provider direto.

---

## Dashboard e navegação do hub

- **`GET /dashboard`** lista módulos em cards (ícone, título, descrição). Itens vêm de `config/hub_dashboard.php` (`route`, `title`, `description`, `icon`).
- Ícones em `resources/views/components/hub/dashboard-icon.blade.php` — adicionar `@case` ao criar `icon` novo.
- **Nova área autenticada no hub**: atualizar `config/hub_dashboard.php`, `resources/views/layouts/navigation.blade.php` (links desktop + responsivo) e o componente de ícone se for símbolo novo.
- Cards e menu refletem o mesmo conjunto de rotas até decidir reduzir um dos dois.

---

## Regras de código

### Geral

- Código em **inglês** (variáveis, métodos, classes). Comentários inline em pt-BR.
- Strings de usuário e mensagens WhatsApp em **português brasileiro**.
- Tipagem estrita em todo PHP — sempre declarar tipos de retorno e parâmetros.
- `readonly` em DTOs e Value Objects.
- Preferir `match` a `switch`.
- Nunca usar `array` sem tipagem quando um DTO resolve melhor.

### Laravel

- Services para lógica de negócio — Controllers só orquestram.
- Jobs para tudo assíncrono — nunca processar na requisição HTTP.
- `firstOrCreate` / `updateOrCreate` para evitar duplicatas.
- Migrations sempre com `down()` implementado.
- Seeders apenas para dados iniciais fixos.
- Factories para testes — não para produção.
- Validação em Form Requests, nunca inline no Controller.
- Cache de configuração, rotas e views sempre em produção.

### Credenciais e segurança

- **Nunca** salvar CPF, senha ou API key no banco (texto plano).
- Credenciais das concessionárias vêm exclusivamente do `.env`.
- **Exceção — Mercado Pago (eventos):** `access_token` em `mercado_pago_accounts` com cast `encrypted` (uma conta por organizador, vinculada ao evento). Nunca expor na API pública `/api/v1/events`.
- Campo de credenciais em `utility_accounts` (se existir) é apenas referência (qual ENV var usar).
- JIDs de grupo (`WHATSAPP_GRUPO_CASA_JID`, `WHATSAPP_NOTAS_GRUPO_JID`) no `.env`, nunca hardcoded.

### Filas e Jobs

- `scraping`: Playwright (timeout longo, 120s).
- `notifications`: WhatsApp e digests leves (`NotificarVencimento`, `SendAlbumContributionDigestJob`).
- `media`: processamento pesado de álbuns (`ProcessAlbumPhotoJob`).
- `ai`: classificação IA (Threads + WhatsApp).
- `default`: o resto.
- Em dev/prod com Redis: rodar `php artisan horizon`. Scripts Composer: `composer dev:horizon` em terminal separado de `composer dev`.
- Scheduler: `php artisan schedule:work` em dev, cron com `schedule:run` em produção. Tasks novas em `bootstrap/app.php` (`withSchedule`).
- Sempre implementar `failed()` nos Jobs para logar erros.
- `$tries = 3` como padrão.

### Playwright (Node)

- Sempre `headless: true` com `--no-sandbox` nos args.
- Sempre fechar o browser no `finally` — nunca deixar vazar.
- Seletores em ordem de preferência: texto visível > placeholder > name > class.
- Logar cada step com timestamp para debug de seletores quebrados.
- Downloads em `/app/downloads/` com timestamp no nome.

---

## Integrações principais — resumo

Para detalhes, ler a SPEC do módulo. Aqui ficam só os ganchos críticos.

### Eventos — pagamento (Mercado Pago)

Checkout Pro via `MercadoPagoCheckoutService` (Preference API). Evento pago: guest `pending_payment` → redirect MP → webhook confirma → `GuestTicketMail` (sem e-mail de interesse). Return URL com polling: `GET /events/payment/return/{token}`, status JSON `GET /events/payment/status/{token}`. Webhook: `POST /webhooks/mercadopago` (CSRF exempt). Job `ExpireUnpaidEventGuestsJob` a cada 15 min. Config: `config/events.php` (`hub_public_url`, `payment_reservation_minutes`). Ver [docs/events/SPEC.md](docs/events/SPEC.md).

### Eventos — QR code

QR codes de check-in são servidos como PNG via rota pública `GET /events/qr/{guest}` (`GuestQrCodeController`). Usa `chillerlan/php-qrcode` (GD, sem Imagick). Emails custom referenciam `{{ $qrUrl }}`; PDFs (DomPDF) usam `{{ $qrDataUri }}` (data URI). Cache de 24h. Templates em `resources/views/{mail,pdf}/events/custom/{key}.blade.php`, resolvidos por `invite_template_key`.

### Evolution API

A Evolution roda na stack Docker do hub (serviço dedicado). URL base em `EVOLUTION_URL`, instância `raphael`, key `EVOLUTION_API_KEY`. Webhook do Laravel: `POST /webhook/whatsapp`. Detalhes (eventos habilitados, formato do body, grupo notas solo, auth do webhook) em [docs/whatsapp/SPEC.md](docs/whatsapp/SPEC.md).

**JIDs:**
- Número individual: `5511948863848@s.whatsapp.net`
- Grupo: `120363XXXXXXXX@g.us`
- `fromMe = true` significa mensagem enviada pelo próprio número da instância.

### MinIO

- Endpoint público: `https://files.raphael-martins.com`.
- Dentro do Compose o Laravel usa hostname do serviço (`AWS_ENDPOINT=http://minio:9000`).
- Bucket: `pessoal` (criar se não existir).
- `AWS_USE_PATH_STYLE_ENDPOINT=true` é **obrigatório**.
- Path padrão dos PDFs: `faturas/{embasa|coelba}/{referencia_sanitizada}_{timestamp}.pdf`.
- Laravel usa `Storage::disk('s3')`.

### NeuronAI

Pacote `neuron-core/neuron-ai`. Groq via `NeuronAI\Providers\OpenAILike`:

```php
use NeuronAI\Providers\OpenAILike;

new OpenAILike(
    baseUri: rtrim(config('services.groq.url'), '/'),
    key: (string) config('services.groq.api_key'),
    model: (string) config('services.groq.model'),
);
```

**Roteamento (`AiRouterService`):** Ollama (se habilitado e prompt curto) → Groq → Anthropic → OpenAI. Falha (HTTP, timeout, resposta vazia, JSON inválido com `expect_json=true`) tenta o próximo. Logs `ai.completion` / `ai.completion_failure`. Façade `NeuronAIService::complete(...)` — não duplicar SDKs fora dessa camada.

**Gateway `POST /iara`:** chamar a API de produção sem Ollama local. Header `X-Internal-Key` (mesmo valor que `IARA_INTERNAL_KEY` no servidor) obrigatório fora de `local`/`testing`. Detalhes em [docs/core/SPEC.md](docs/core/SPEC.md).

Variáveis como `AI_PRIMARY_PROVIDER` **não** são lidas pelo código — o roteamento é o `AiRouterService`.

### Docker

- `PUID=1003` / `PGID=1003` — usuário `deploy` na VPS.
- Todos os containers Laravel usam a imagem `raphael-hub:latest`.
- Network: `raphael-bridge` (isolada).
- Playwright em container separado `raphael-playwright` na porta interna `3001`. Laravel chama via `http://raphael-playwright:3001`.
- PostgreSQL containerizado — não usar o MySQL nativo do aaPanel.
- **Uploads (álbuns/multipart)**: imagem PHP carrega `docker/php/zz-uploads.ini`; Nginx do Compose usa `docker/nginx/default.conf` (`client_max_body_size` alinhado a `post_max_size`). Nginx do **host (aaPanel)** precisa do mesmo limite. Mudou `.ini` ou `default.conf` → rebuild da imagem app + `up -d` do `nginx`.
- Imagem PHP inclui `ffmpeg` e `zip`/`unzip` no SO (preparação para fase F de álbuns).

Detalhes operacionais completos em [docs/operations/SPEC.md](docs/operations/SPEC.md).

### Horizon

- Prefix Redis: `raphael_horizon:`.
- Acesso: `https://api.raphael-martins.com/horizon` (mesmo middleware do hub).
- Protegido por e-mail em `HORIZON_AUTH_EMAILS`.
- Publicar assets: `php artisan horizon:publish`.
- Filas em `config/horizon.php`.

---

## Pontos de atenção — bugs conhecidos a evitar

1. **Coelba é Angular SPA com hash routing (`#/`)**. Usar `waitForURL` com hash completo. `waitUntil: 'networkidle'` pode falhar em SPAs — preferir `waitForSelector` de elemento específico.
2. **reCAPTCHA Coelba**: site diz "protegido por reCAPTCHA" mas não há desafio visual. É v3 invisível com score. CapSolver resolve via `ReCaptchaV3TaskProxyLess`. Se falhar, tentar submeter sem token antes de lançar erro.
3. **Download de PDF no Playwright**: sempre `Promise.all([page.waitForEvent('download'), btn.click()])` — nunca clicar e aguardar separadamente.
4. **Permissões de storage**: volume `.:/var/www/html` monta com usuário do host. Rodar `chown -R deploy:deploy storage bootstrap/cache` no deploy antes do `docker compose up`.
5. **MinIO path style**: sem `AWS_USE_PATH_STYLE_ENDPOINT=true` as requests falham com 403.
6. **Evolution webhook**: payload em `data` com `key` + `message`; ignorar `status@broadcast` e itens sem `message`. Mídia "para si mesmo" no 1:1 pode não gerar evento — usar grupo notas solo (ver [docs/whatsapp/SPEC.md](docs/whatsapp/SPEC.md)).
7. **Postgres no Docker**: `DB_HOST` deve ser `raphael-postgres` dentro dos containers; com infra externa no host, usar `127.0.0.1` + porta mapeada.
8. **Embasa sem débitos na 2ª via**: pode mostrar *"não possui débitos"*; o scraper em `playwright/src/embasa-scraper.js` extrai então o carrossel **MINHAS CONTAS** na `/home`. Esperado: JSON de sucesso com `faturas` e sem `pdf_path` — **não** tratar como falha.
9. **Modais Embasa**: `section.blk-modal` pode interceptar clique na matrícula. Chamar `dismissEmbasaBlockingModals` antes e usar `force` no clique quando necessário.

---

## `.env.example` — fonte da verdade por ambiente

O `.env.example` está **calibrado para produção** (Docker VPS: `APP_URL=https://api.raphael-martins.com`, `DB_HOST=raphael-postgres`, `REDIS_HOST=raphael-redis`, `QUEUE_CONNECTION=redis`, `APP_DEBUG=false`). Cada bloco tem comentários com sobrescritos para dev local. Tabela completa de overrides em [docs/operations/SPEC.md](docs/operations/SPEC.md).

---

## Como rodar localmente

Resumo. Comandos completos no [README](README.md).

### Opção A — `docker compose` deste repositório

```bash
cp .env.example .env
# Ajustar APP_ENV=local, APP_DEBUG=true, APP_URL=http://localhost:8082,
# LOG_LEVEL=debug. Hosts DB_HOST=raphael-postgres / REDIS_HOST=raphael-redis
# já batem com o compose.
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

### Opção B — infra externa + vhost `hub.test`

Usar a tabela de sobrescritas em [docs/operations/SPEC.md](docs/operations/SPEC.md) para alinhar `APP_URL`, `DB_*`, `REDIS_*`, Mailpit e modo de fila. Rodar `composer install`, `php artisan key:generate`, `php artisan migrate` no host ou no container PHP que enxerga Postgres/Redis.
