# Playwright Threads Service

Servidor HTTP Node.js para autenticar no Threads e executar scraping por URL ou keyword.

## Executar local (host-first)

```bash
cd playwright
npm install
npx playwright install chromium
npm run dev
```

Padrao de porta: `3001` (`PORT`).

## Variaveis de ambiente

- `THREADS_USERNAME` (obrigatoria para login)
- `THREADS_PASSWORD` (obrigatoria para login)
- `THREADS_SESSION_PATH` (default host-first: `playwright/storage/threads-session.json`)
- `THREADS_MAX_POSTS_PER_KEYWORD` (default: `20`)
- `THREADS_STEP_TIMEOUT_MS` (default: `30000`)
- `THREADS_RANDOM_DELAY_MIN_MS` (default: `250`)
- `THREADS_RANDOM_DELAY_MAX_MS` (default: `700`)
- `THREADS_MAX_SCROLL_ROUNDS` (default: `12`)
- `THREADS_DEBUG_DIR` (default: `/app/downloads/threads-debug`)

## Endpoints

### `GET /health`

Retorna estado do servidor e status da sessao persistida.

### `POST /threads/auth/login`

Body:

```json
{
  "force_relogin": false
}
```

### `POST /threads/scrape-url`

Body:

```json
{
  "url": "https://www.threads.net/..."
}
```

### `POST /threads/scrape-keyword`

Body:

```json
{
  "keyword": "php laravel remoto",
  "max_posts": 10,
  "include_comments": false,
  "only_new": true,
  "known_post_ids": ["DXaaS6-igb9", "DXATKvACX6e"],
  "known_streak_stop": 20
}
```

`include_comments` default: `false` (modo recomendado para descobrir posts de vagas/freelas sem ruído de comentários).
`only_new` default: `false`. Quando `true`, posts já conhecidos (por `known_post_ids`) são ignorados.
`known_streak_stop` default: `20`. Para a coleta mais cedo quando a busca começa a retornar só itens repetidos.

## Contrato de falha

Quando houver erro no scraping, a resposta inclui:

- `success: false`
- `error`
- `screenshot_path` (quando disponivel)
- `scraped_at`
