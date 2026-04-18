# CLAUDE.md — Raphael Hub

Instruções para agentes de IA (Claude Code, Cursor, etc.) ao trabalhar neste projeto.
Leia este arquivo inteiro antes de escrever qualquer código.

---

## O que é este projeto

Sistema pessoal de automação doméstica do Raphael via WhatsApp + dashboard web.
Scraping de contas Embasa (água) e Coelba (luz), notificações para grupo familiar,
captura de lembretes pessoais, e base para futuros projetos pessoais integrados.

**Documentos de referência obrigatórios:**
- `PRD.md` — requisitos de produto
- `SPEC.md` — especificação técnica completa (leia antes de implementar qualquer coisa)
- `docs/v2.md` — backlog e decisões da evolução V2 (grupos, permissões, pipelines AI); não duplicar no SPEC além do schema acordado

---

## Stack e versões

Versões abaixo refletem o **ambiente de desenvolvimento local** atual (Node 24, PHP 8.4, Postgres 17). Produção pode fixar minors no Dockerfile/CI — manter compatível com Laravel 12 e drivers oficiais.

- **PHP**: 8.4 — usar features modernas (readonly, enums, fibers, match, named args)
- **Laravel**: 13 — sem versões anteriores de sintaxe
- **Livewire**: 4 — sem Livewire 3
- **Node**: 24 — runtime do serviço Playwright (local e container dedicado)
- **PostgreSQL**: 17 — banco deste app (**não** usar MySQL para o Raphael Hub)
- **NeuronAI**: pacote `neuron-core/neuron-ai` — não usar OpenAI SDK diretamente

---

## Regras de código

### Geral
- Todo código em **inglês** (variáveis, métodos, classes, apenas os comentários inline em pt-br)
- Strings de usuário e mensagens WhatsApp em **português brasileiro**
- Tipagem estrita em todo o PHP — sempre declarar tipos de retorno e parâmetros
- Usar `readonly` em DTOs e Value Objects quando aplicável
- Preferir `match` a `switch`
- Nunca usar `array` sem tipagem quando um DTO resolve melhor

### Laravel
- Services para lógica de negócio — Controllers só orquestram
- Jobs para tudo que pode ser assíncrono — nunca processar na requisição HTTP
- Usar `firstOrCreate` / `updateOrCreate` para evitar duplicatas
- Migrations sempre com `down()` implementado
- Seeders apenas para dados iniciais fixos (contas Embasa/Coelba, source 'self')
- Factories para testes — não para produção
- Validação nos Form Requests, nunca inline no Controller
- Cache de configuração, rotas e views sempre em produção

### Credenciais e segurança
- **Nunca** salvar CPF, senha ou API keys no banco de dados
- Credenciais das concessionárias vêm **exclusivamente do `.env`**
- O campo de credenciais na tabela `utility_accounts` (quando existir) é apenas referência (ex.: qual ENV var usar)
- WHATSAPP_GRUPO_CASA_JID deve estar no `.env`, nunca hardcoded

### Filas e Jobs
- Fila `scraping` para Jobs do Playwright (timeout longo: 120s)
- Fila `notifications` para Jobs de WhatsApp
- Fila `default` para o resto
- Produção/dev com Redis: rodar **`php artisan horizon`** (workers para `default`+`notifications` e supervisor dedicado para `scraping`). Scripts Composer: `composer dev:horizon` em um terminal separado do `composer dev`.
- Scheduler: **`php artisan schedule:work`** em dev (`composer dev:schedule`), ou cron em produção com `schedule:run` a cada minuto. Tasks novas ficam em `bootstrap/app.php` (`withSchedule`).
- Sempre implementar `failed()` nos Jobs para logar erros
- `$tries = 3` como padrão

### Playwright (Node)
- Sempre `headless: true` com `--no-sandbox` nos args
- Sempre fechar o browser no `finally` — nunca deixar vazar
- Seletores na ordem de preferência: texto visível > placeholder > name > class
- Logar cada step com timestamp para facilitar debug de seletores quebrados
- Downloads salvos em `/app/downloads/` com timestamp no nome

---

## Integração Evolution API

A Evolution API está rodando em `https://whats.dopacheck.com.br`.
A instância para este projeto chama-se `raphael` (número pessoal do Raphael).
**Não** é a mesma instância do DopaCheck.

O webhook da Evolution deve ser configurado para apontar para:
`https://api.raphael-martins.com/webhook/whatsapp`

Evento necessário habilitado na instância: `messages.upsert`

**Auth do webhook:** com `EVOLUTION_WEBHOOK_SECRET`, o Laravel compara com o campo **`apikey` no JSON do body** (a Evolution envia assim no `webhookData`) e, se existirem, com headers `apikey` / `Authorization: Bearer` / `x-api-key`. Use o **mesmo valor do token da instância** do dashboard em `EVOLUTION_WEBHOOK_SECRET` (ou o que vier no body em produção).

### JIDs Evolution
- Número individual: `5511948863848@s.whatsapp.net`
- Grupo: `120363XXXXXXXX@g.us`
- `fromMe = true` significa que a mensagem foi enviada pelo próprio número cadastrado

---

## Integração MinIO

- Endpoint: `https://files.dopacheck.com.br`
- Bucket: `pessoal` (criar se não existir)
- `AWS_USE_PATH_STYLE_ENDPOINT=true` — obrigatório para MinIO
- Path dos PDFs: `faturas/{embasa|coelba}/{referencia_sanitizada}_{timestamp}.pdf`
- O Laravel usa `Storage::disk('s3')` — configurado via variáveis AWS_* no `.env`

---

## Integração NeuronAI

Groq é acessado via `OpenAILike` — não existe provider `Groq` nativo, usar:

```php
use NeuronAI\Providers\OpenAILike\OpenAILike;

new OpenAILike(
    url: env('GROQ_URL', 'https://api.groq.com/openai/v1'),
    model: env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    apiKey: env('GROQ_API_KEY'),
)
```

Fallback para Anthropic Claude quando Groq falhar ou para tarefas pesadas.

---

## Docker

- `PUID=1003` / `PGID=1003` — usuário `deploy` na VPS
- Todos os containers Laravel usam a mesma imagem `raphael-hub:latest`
- Network: `raphael-bridge` — isolada dos projetos DopaCheck e Valuin
- Playwright roda em container separado `raphael-playwright` na porta interna `3001`
- O Laravel chama o Playwright via `http://raphael-playwright:3001` (nome do container na rede Docker)
- PostgreSQL é containerizado — não usar o MySQL nativo do aaPanel

---

## Horizon

- Prefix Redis: `raphael_horizon:`
- Acesso: `https://api.raphael-martins.com/horizon`
- Protegido por email no `.env` (`HORIZON_AUTH_EMAILS`)
- Publicar assets: `php artisan horizon:publish`
- Filas configuradas: `default`, `scraping`, `notifications`

---

## Pontos de atenção — bugs conhecidos a evitar

1. **Playwright no Coelba**: É Angular SPA com hash routing (`#/`). Usar `waitForURL` com o hash completo. `waitUntil: 'networkidle'` pode não funcionar bem em SPAs — preferir `waitForSelector` de elemento específico.

2. **reCAPTCHA Coelba**: O site diz "protegido por reCAPTCHA" mas o usuário real não vê desafio visual. É v3 invisível com score. CapSolver resolve via `ReCaptchaV3TaskProxyLess`. Se falhar, tentar submeter sem token antes de lançar erro.

3. **Download de PDF no Playwright**: Sempre usar `Promise.all([page.waitForEvent('download'), btn.click()])` — nunca clicar e aguardar separadamente.

4. **Permissões de storage**: O volume `.:/var/www/html` monta com o usuário do host. Rodar `chown -R deploy:deploy storage bootstrap/cache` no deploy antes do `docker compose up`.

5. **MinIO path style**: Sem `AWS_USE_PATH_STYLE_ENDPOINT=true` as requests falham com 403. Sempre incluir.

6. **Evolution webhook**: O payload vem em `data.message` para `messages.upsert`. Ignorar `status@broadcast` e mensagens sem `message` key para evitar erros de processamento.

7. **Postgres no Docker**: `DB_HOST` deve ser `raphael-postgres` (nome do container), não `127.0.0.1`, dentro dos containers. Com infra externa no host, usar `127.0.0.1` + porta mapeada.

---

## `.env.example` — fonte da verdade por ambiente

O arquivo [`.env.example`](.env.example) está **calibrado para produção** (Docker na VPS: `APP_URL=https://api.raphael-martins.com`, `DB_HOST=raphael-postgres`, `REDIS_HOST=raphael-redis`, `QUEUE_CONNECTION=redis`, `APP_DEBUG=false`, etc.).  
Cada bloco inclui **comentários** com os sobrescritos para desenvolvimento local (**hub.test**, Postgres/Redis/Mailpit em infra separada, ou `localhost:8082` com compose deste repo).

---

## Desenvolvimento local com infra externa (hub.test)

Postgres **17**, nginx, Redis **7**, Mailpit e opcionalmente MySQL **8.4** costumam rodar num **Compose de infra**. O Raphael Hub usa **somente PostgreSQL** — MySQL na mesma stack é para **outros** projetos (`DB_CONNECTION` continua `pgsql`).

Checklist típico de sobrescrita em relação ao `.env.example` de produção:

| Variável | Produção | Local (hub.test / serviços no host ou rede compartilhada) |
|----------|----------|------------------------------------------------------------|
| `APP_ENV` | `production` | `local` |
| `APP_DEBUG` | `false` | `true` |
| `APP_URL` | `https://api.raphael-martins.com` | `http://hub.test` ou `https://hub.test` (igual ao browser / vhost nginx) |
| `LOG_LEVEL` | `error` ou `warning` | `debug` |
| `SESSION_ENCRYPT` | `true` | `false` em HTTP puro sem HTTPS local |
| `DB_HOST` | `raphael-postgres` | `127.0.0.1` se a porta Postgres estiver publicada no host |
| `DB_PORT` | `5432` | porta mapeada pelo compose de infra (se diferente de 5432) |
| `REDIS_HOST` | `raphael-redis` | `127.0.0.1` ou nome do serviço Redis na rede Docker que o PHP enxerga |
| `QUEUE_CONNECTION` | `redis` | `redis` (com Horizon/worker) ou `sync` só para depuração rápida |
| `MAIL_MAILER` | `log` ou SMTP real | `smtp` para Mailpit |
| `MAIL_HOST` / `MAIL_PORT` | conforme provedor | `127.0.0.1` + `1025` (SMTP Mailpit), `MAIL_ENCRYPTION=null` |

Mailpit: UI de inspeção em geral na porta exposta pelo compose (ex.: **8025**). Se o PHP não alcança `127.0.0.1` do host (processo dentro de container), usar o **hostname do serviço Mailpit** na rede Docker.

---

## Como rodar localmente

### Opção A — `docker compose` deste repositório

Copie `.env.example` → `.env` e ajuste para **desenvolvimento** neste compose: `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8082`, `LOG_LEVEL=debug`, `QUEUE_CONNECTION=sync` ou `redis` + workers, `SESSION_ENCRYPT=false` se não usar HTTPS. Os hosts `DB_HOST=raphael-postgres` e `REDIS_HOST=raphael-redis` já batem com o `.env.example` de produção.

```bash
# 1. Clonar e configurar
cp .env.example .env
# Preencher secrets e ajustar flags de dev locais (ver tabela acima)

# 2. Subir infra
docker compose up -d

# 3. Instalar dependências
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# 4. Acessar
# App: http://localhost:8082
# Horizon: http://localhost:8082/horizon

# 5. Para testar scraping localmente
curl -X POST http://localhost:3001/scrape/embasa
```

### Opção B — infra separada + vhost hub.test

Use a **tabela** da seção **Desenvolvimento local com infra externa** para alinhar `APP_URL`, `DB_*`, `REDIS_*`, Mailpit e modo de fila. Rode `composer install`, `php artisan key:generate`, `php artisan migrate` no host ou no container PHP que enxerga Postgres/Redis. App: **`http://hub.test`** — Horizon: **`http://hub.test/horizon`** (ou HTTPS se o nginx local terminar SSL).

---

## Ordem de implementação recomendada

1. Dockerfile + docker-compose.yml + nginx config
2. Migrations + Models + Seeders
3. `EvolutionService` — envio de mensagens
4. `WebhookController` + `WebhookRouterService` — roteamento
5. `ProcessarMensagemPessoal` Job — isFromMe
6. `NeuronAIService` — classificação de intenção
7. Playwright server.js + scraper Embasa
8. Playwright scraper Coelba + CapSolver
9. `PlaywrightService` + `FaturaService` — Laravel
10. `ScrapeConta` Job + Schedule
11. `NotificarVencimento` Job
12. `ProcessarMensagemContato` Job — resposta para o pai
13. Dashboard Blade + Livewire
14. CI/CD GitHub Actions
