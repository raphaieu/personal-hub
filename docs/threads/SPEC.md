# SPEC — Threads e Oportunidades

Scraping autenticado do Threads (sessão persistida no Playwright), ingestão idempotente, classificação IA por comentário com threshold de relevância, curadoria no hub (`/hub/threads`), feed público `/oportunidades` com votação anônima.

Usa `analysis_profiles` (definido em [core](../core/SPEC.md)) — profile padrão `threads-opportunities`.

---

## Banco

### `threads_sources`

Fontes de scraping: keyword ou URL.

```
id, kind (keyword|url), label,
target,                        -- texto da keyword ou URL completa
config (json nullable),
is_active (bool),
last_scraped_at (timestamp nullable),
analysis_profile_id (FK nullable),
timestamps
```

### `threads_posts`

```
id, threads_source_id (FK nullable),
external_id (unique),          -- ex.: DXaaS6-igb9
author_handle, content,
metadata (json),
timestamps
```

### `threads_comments`

```
id, threads_post_id (FK),
external_id (unique),
author_handle, content,

-- classificação IA
threads_category_id (FK nullable),
ai_summary, ai_relevance_score, ai_meta (json),
status,                        -- pending_classification | pending_review |
                               -- ignored | published

-- publicação e votos
is_public (bool), is_featured (bool),
upvotes, downvotes, score_total,

timestamps
```

### `threads_categories`

Categorias de oportunidade (ex.: `freelance`, `vaga_clt`, `mentoria`). Seed inicial em `ThreadsCategorySeeder`.

```
id, slug (unique), name, description,
analysis_profile_id (FK nullable),
timestamps
```

### `threads_comment_votes`

```
id, threads_comment_id (FK),
session_fingerprint,           -- sha256(ip|user_agent|Y-m-d|salt)
vote,                          -- 1 (up) | -1 (down)
timestamps

UNIQUE: (threads_comment_id, session_fingerprint)
```

Dedupe diário por fingerprint para votação anônima.

---

## Container Playwright — endpoints

Servidor Node em `playwright/server.js`, container `raphael-playwright` na porta interna `3001`.

- `GET /health` → `status`, `session_ready`, `session_path`.
- `POST /threads/auth/login` — body: `{ "force_relogin": false }`. Login com `THREADS_USERNAME`/`THREADS_PASSWORD` e persiste `storageState`.
- `POST /threads/scrape-url` — body: `{ "url": "https://www.threads.com/@handle/post/..." }`. Retorna post + comentários do thread, com scroll adaptativo e acumulação incremental (mitiga virtualização de DOM).
- `POST /threads/scrape-keyword` — body mínimo: `{ "keyword": "...", "max_posts": 30 }`. Padrão `include_comments=false` (modo recomendado para descoberta). Suporta dedupe: `known_post_ids`, `only_new`, `known_streak_stop`.

### Contrato keyword

Request:

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

Response:

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

### Operação

- Em dev, recomendado `PLAYWRIGHT_HEADLESS=false` para debug de login/seletores.
- `keyword` opera em **posts-only** para reduzir ruído/custo. Comentários ficam para coletas por URL ou investigação pontual.
- Dedupe final é **obrigatória** no Laravel por `external_id`.

### Variáveis do Playwright Threads

```env
PLAYWRIGHT_SERVICE_URL=http://raphael-playwright:3001
PLAYWRIGHT_HTTP_TIMEOUT=
PLAYWRIGHT_HEADLESS=true

THREADS_USERNAME=
THREADS_PASSWORD=
THREADS_SESSION_PATH=/app/storage/threads-session.json
THREADS_MAX_POSTS_PER_KEYWORD=
THREADS_STEP_TIMEOUT_MS=
THREADS_RANDOM_DELAY_MIN_MS=
THREADS_RANDOM_DELAY_MAX_MS=
THREADS_MAX_SCROLL_ROUNDS=
THREADS_SCROLL_IDLE_ROUNDS=
THREADS_KNOWN_STREAK_STOP=
THREADS_DEBUG_DIR=
```

---

## Integração Laravel

### Contrato e clientes

| Classe | Papel |
|--------|-------|
| `App\Contracts\ThreadsScraperClientInterface` | Contrato mockável. |
| `App\Services\Threads\ThreadsPlaywrightService` | Implementação real (HTTP para `services.playwright.url`). |
| `App\Services\Threads\FakeThreadsScraperClient` | Fake para testes. |

Bind em `AppServiceProvider`. Configuração em `services.playwright.url` e `services.playwright.timeout` (`PLAYWRIGHT_HTTP_TIMEOUT`).

### Ingestão idempotente

`App\Services\Threads\ThreadsScrapeIngestionService`:

- Upsert em `threads_posts` e `threads_comments` por `external_id` (chave única).
- `threads_sources.last_scraped_at` atualizado quando o job recebe `threads_source_id`.

### Classificação IA

`App\Services\Threads\ThreadsClassificationService` usa `NeuronAIService::complete(..., expectJson: true)` com `AiTask::ThreadsOpportunityClassification`.

**JSON esperado do classificador:**

```json
{
  "category_slug": "freelance",
  "summary": "Vaga remota PHP/Laravel...",
  "relevance_score": 0.82
}
```

Mapeamento para `threads_comments`: `threads_category_id`, `ai_summary`, `ai_relevance_score`, `ai_meta`.

**Threshold de relevância:**

- Variável `THREADS_RELEVANCE_THRESHOLD` (em `services.threads.relevance_threshold`).
- `relevance_score` abaixo do corte → `status = ignored`.
- `relevance_score` no/acima do corte → `status = pending_review`.
- Normaliza escalas 0..1 e 0..100 automaticamente.

---

## Jobs

| Job | Fila | Trigger |
|-----|------|---------|
| `ScrapeThreadsUrlJob` | `scraping` | Ação no hub ou agendamento. |
| `ScrapeThreadsKeywordJob` | `scraping` | Ação no hub ou agendamento. |
| `ClassifyCommentsJob` | `ai` | Após ingestão de comentários (1 comentário por execução). |
| `DispatchPendingThreadsClassificationJob` | `ai` | Varre comentários pendentes (`ai_summary IS NULL`) e enfileira `ClassifyCommentsJob` com espaçamento. |
| `RecalculateCommentScoreJob` | `default` | Após voto em `/oportunidades`. |

### Robustez do pipeline IA

- `ClassifyCommentsJob` processa **1 comentário por execução** (`commentId`, opcional `force`) para reduzir travamentos em lote.
- `DispatchPendingThreadsClassificationJob` enfileira pendentes com cadência controlada por `THREADS_AI_DISPATCH_SPACING_SECONDS` (em `services.threads.ai_dispatch_spacing_seconds`).
- Horizon tem supervisor dedicado para fila `ai`.

---

## Hub `/hub/threads`

Rota autenticada: `GET /hub/threads` (`threads.hub`), componente Livewire `App\Livewire\Threads\HubPage`, layout `layouts.app`. Link na navegação principal.

Abas em query string (`?tab=`):

### Sources

- Listagem de `threads_sources` (tipo, label, alvo, status, último scrape, profile associado).
- Criação keyword/URL.
- Toggle `is_active`.
- Edição rápida de `analysis_profile_id` por source (com fallback para profile padrão quando sem vínculo explícito).
- Ação **scrape agora** enfileira `ScrapeThreadsKeywordJob` (`onlyNew=true`, `knownPostIds` da própria source) ou `ScrapeThreadsUrlJob`.

### Review

Curadoria de comentários com `status = pending_review`/`ignored`.

- Filtros: status, categoria, source, somente sem `ai_summary`.
- Ordenação: relevância IA (`ai_relevance_score`), mais novo (`created_at`), score (`score_total`).
- Ações individuais: `reclassifyComment` (força novo `ClassifyCommentsJob`), `moveCommentToPendingReview`, `ignoreComment`, `toggleCommentPublic`.
- **Curadoria em lote** (`selectedReviewCommentIds`):
  - `batchMoveSelectedToPendingReview`
  - `batchIgnoreSelected`
  - `batchPublishSelected`
  - `batchUnpublishSelected`
  - `batchReclassifySelected` (1 `ClassifyCommentsJob` com `force=true` por comentário)
- Controle **selecionar todos nesta página** (`toggleSelectAllReviewOnPage`, limite 100).
- Botão **dispatch pending classification** (enfileira `DispatchPendingThreadsClassificationJob` com batch configurável).
- Métricas: total pendente (`ai_summary IS NULL`), estimativa do próximo disparo, cadência da fila `ai`.

### Published

Listagem de comentários `is_public=true` (limite 100), com `post.source` e `category` para contexto.

- Query string: `pub_category`, `pub_source`, `pub_sort` (`score` | `newest` | `relevance`).
- Edição rápida por linha (`publishedForms[commentId]`): `ai_summary`, `threads_category_id`, `is_featured`.
- Ações: `savePublishedComment`, `unpublishPublishedComment`.
- Métricas: `upvotes`, `downvotes`, `score_total`.

Cobertura: `tests/Feature/Threads/ThreadsHubPageTest.php`.

---

## Página pública `/oportunidades`

Rota: `GET /oportunidades` (`threads.opportunities`), **sem autenticação**.

Controller invokável `App\Http\Controllers\ThreadsOpportunitiesController`: lista apenas `threads_comments` com `is_public=true`, com `category`, `post` e `post.source`.

### Query string

- `q` — busca em `ai_summary` e `content` (`LIKE`, compatível com SQLite em testes).
- `category` — id da categoria.
- `source` — id da `threads_sources` (via post).
- `sort` — `relevance` | `votes` | `newest`.

Paginação: 20 itens por página, ordenação padrão por `ai_relevance_score` descendente.

Views: `resources/views/threads/opportunities.blade.php` estendendo `layouts.public` (header mínimo, link Entrar/Dashboard conforme sessão).

Cobertura: `tests/Feature/Threads/ThreadsOpportunitiesPageTest.php`.

---

## Votação anônima

Rota: `POST /oportunidades/votos/{comment}` (`threads.opportunities.vote`), throttle `120,1`, sem autenticação.

`ThreadsCommentVoteController::store`:

- Aceita apenas comentários `is_public=true` (404 caso contrário).
- Body: `direction` = `up` | `down`.

`App\Services\Threads\ThreadsVoteFingerprintService`:

- `session_fingerprint = hash('sha256', ip|user_agent|Y-m-d|salt)` com `config('services.threads.vote_fingerprint_salt')` (`THREADS_VOTE_FINGERPRINT_SALT`).
- Dedupe diário: mesmo visitante no mesmo dia → mesmo fingerprint.

Persistência em `threads_comment_votes` com `updateOrCreate` por `(threads_comment_id, session_fingerprint)`; `vote ∈ {1, -1}`.

`App\Jobs\RecalculateCommentScoreJob` recalcula `upvotes`, `downvotes` e `score_total = upvotes - downvotes` no `threads_comments` correspondente.

UI: botões +1 / -1 com `@csrf` em `threads/opportunities.blade.php`.

Cobertura: `tests/Feature/Threads/ThreadsCommentVoteTest.php`.

---

## Variáveis de ambiente

```env
# Playwright Threads (já listadas acima)
PLAYWRIGHT_SERVICE_URL=
THREADS_USERNAME=
THREADS_PASSWORD=

# Classificação
THREADS_RELEVANCE_THRESHOLD=
THREADS_AI_DISPATCH_SPACING_SECONDS=

# Votação
THREADS_VOTE_FINGERPRINT_SALT=
```

---

## Testes

- `tests/Feature/Threads/ThreadsScraperClientTest.php` — contrato + fake.
- `tests/Feature/Threads/ThreadsScrapeIngestionJobsTest.php` — jobs e dedupe.
- `tests/Feature/Threads/ThreadsClassificationServiceTest.php` — threshold/status + job IA.
- `tests/Feature/Threads/ThreadsHubPageTest.php` — hub (sources, review batch, published).
- `tests/Feature/Threads/ThreadsOpportunitiesPageTest.php` — feed público.
- `tests/Feature/Threads/ThreadsCommentVoteTest.php` — votação.

---

## Features em planejamento

- [SPEC_threads_groupings.md](SPEC_threads_groupings.md) — agrupamento semântico por similaridade (pgvector + cosine + clustering), nova etapa `approved → grouped → published`. Nenhuma fase iniciada; documento de planejamento.
