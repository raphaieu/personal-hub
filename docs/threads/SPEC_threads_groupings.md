# SPEC — Feature: Threads Groupings (pgvector + Semantic Clustering)

**Projeto:** raphael-hub  
**Stack base:** Laravel 13 + PHP 8.4 · Livewire 4 · PostgreSQL 17 + pgvector · Redis 7 · Horizon · NeuronAI  
**Data da última revisão:** 2026-05-11  
**Status:** planejamento — nenhuma fase iniciada  
**Localização:** `docs/threads/SPEC_threads_groupings.md`

**Índice na documentação do projeto:** esta SPEC é a fonte de verdade da feature de agrupamento semântico de comentários do Threads Hub. Nomenclatura: código e banco em inglês (`groupings`, `embeddings`, `approved`); labels e textos de UI em pt-BR ("Agrupados", "Aprovados", "Similares"). Visão geral do produto e stack: [README.md](../../README.md), [PRD.md](../../PRD.md), [SPEC.md](../../SPEC.md) (índice técnico), [docs/threads/SPEC.md](SPEC.md) (módulo atual), [LLM.md](../../LLM.md). Alterações: [CHANGELOG.md](../../CHANGELOG.md).

---

## 1. Objetivo

Adicionar ao pipeline existente de classificação de comentários do Threads Hub uma camada de **agrupamento semântico por similaridade**, eliminando ruído de duplicatas e comentários similares que poluem o Review e o feed público.

O fluxo atual termina em `is_public=true` (Publicados). Esta feature introduz:

- Estado intermediário `approved` — comentários curados pelo humano, prontos para embedding
- Pipeline de **embeddings vetoriais** armazenados no PostgreSQL via pgvector
- **Clustering matemático** por cosine similarity — sem enviar centenas de comentários de uma vez para a LLM
- Entidade `thread_groupings` — agrupamentos nomeados e resumidos pela IA a partir dos clusters
- Nova etapa de curadoria **Agrupados** antes da publicação definitiva

Princípios:

1. **Não quebrar o pipeline existente** — `pending_review → curadoria → is_public=true` continua funcionando; novos estados e tabelas são aditivos
2. **LLM apenas para nomeação** — a IA recebe só os `ai_summary` do grupo (poucos tokens) para gerar título e overview
3. **Local-first em produção** — Ollama (`nomic-embed-text`) para embeddings na VPS; OpenAI como fallback e padrão em dev
4. **Jobs assíncronos independentes** — cada etapa é um job separado nas filas existentes (`ai`, `default`), seguindo o padrão `ClassifyCommentsJob`

---

## 2. Contexto: pipeline completo após a feature

```
[Scraping]
  → threads_comments (status: pending_review | ignored)
  → [Curadoria humana — aba Review]          ← sem mudança
  → approved = true                          ← NOVO campo
  → [GenerateEmbeddingJob] (fila: ai)        ← NOVO
  → embedding populado em threads_comments
  → [GroupSimilarCommentsJob] (fila: default) ← NOVO
  → thread_groupings criados + comentários vinculados
  → [SummarizeGroupingJob] (fila: ai)        ← NOVO
  → [Curadoria humana — aba Agrupados]       ← NOVA aba
  → is_public = true (Publicados)            ← agora via agrupamento
```

**Tabs do Hub Threads antes e depois:**

| Antes | Depois |
|---|---|
| Sources / Review / Published | Sources / Review / **Aprovados** / **Agrupados** / Publicados |

---

## 3. Conceitos de pgvector (referência de aprendizado)

### 3.1 Embeddings

Texto transformado em vetor numérico (array de floats). Dois textos semanticamente parecidos ficam "próximos" no espaço vetorial — independente de usarem as mesmas palavras. "Vender brigadeiro" e "fazer doce em casa para vender" terão vetores próximos; "vaga de analista de licitações" ficará distante de ambos.

### 3.2 Cosine Similarity

Métrica padrão do pgvector. O operador `<=>` retorna distância coseno (0 = idênticos, 2 = opostos). Similaridade = `1 - (a <=> b)`, resultando em 0..1.

### 3.3 Clustering por threshold

Abordagem usada aqui — adequada para volumes de 50–500 comentários por sessão:

```sql
-- Vizinhos de um comentário seed com similaridade mínima
SELECT id, ai_summary,
       1 - (embedding <=> '[vetor_seed]'::vector) AS similarity
FROM threads_comments
WHERE approved = true
  AND thread_grouping_id IS NULL
  AND 1 - (embedding <=> '[vetor_seed]'::vector) >= 0.82
ORDER BY similarity DESC
LIMIT 50;
```

### 3.4 Índice HNSW

Para performance em volumes maiores (adicionado na migration desde o início):

```sql
CREATE INDEX ON threads_comments USING hnsw (embedding vector_cosine_ops);
```

### 3.5 Modelos de embedding suportados

| Modelo | Provider | Dimensões | Cenário |
|---|---|---|---|
| `text-embedding-3-small` | OpenAI | 1536 | Dev local + fallback produção |
| `nomic-embed-text` | Ollama (local VPS) | 768 | Produção — custo zero |

**Atenção:** trocar de modelo exige re-gerar todos os embeddings (vetores de modelos diferentes não são comparáveis). Variável `EMBEDDING_DIMENSIONS` deve ser consistente com o modelo ativo. Recomendado: começar com OpenAI (1536) em ambos os ambientes e migrar para Ollama quando houver volume real e modelo instalado na VPS.

---

## 4. Banco de Dados

### 4.1 Pré-requisito: extensão pgvector

A extensão `pgvector` **não está incluída** na imagem padrão `postgres:17`. É necessário trocar para `pgvector/pgvector:pg17` no `docker-compose.yml` de infra local e no `docker-compose.yml` de produção.

**Verificação:**
```bash
php artisan tinker
>>> DB::select("SELECT extname FROM pg_extension WHERE extname = 'vector'");
# deve retornar resultado; array vazio = extensão ausente
```

**Troca de imagem:**
```yaml
# docker-compose.yml (infra local e produção)
# de:
image: postgres:17
# para:
image: pgvector/pgvector:pg17
```

Após trocar: `docker compose pull raphael-postgres && docker compose up -d raphael-postgres`

### 4.2 Alterações em `threads_comments`

Migration: `add_grouping_fields_to_threads_comments`

```php
// Campos novos — todos nullable/default para não quebrar registros existentes
$table->boolean('approved')->default(false)->after('is_public');
$table->timestamp('approved_at')->nullable()->after('approved');
$table->boolean('is_grouping_highlight')->default(false)->after('approved_at');
$table->unsignedBigInteger('thread_grouping_id')->nullable()->after('is_grouping_highlight');
$table->foreign('thread_grouping_id')->references('id')->on('thread_groupings')->nullOnDelete();
$table->vector('embedding', config('services.embedding.dimensions', 1536))->nullable()->after('thread_grouping_id');
$table->string('embedding_model', 100)->nullable()->after('embedding');
$table->timestamp('embedded_at')->nullable()->after('embedding_model');

// Índices
$table->index(['approved', 'embedded_at']); // job de embedding varre pendentes
$table->index(['approved', 'thread_grouping_id']); // job de clustering

// Índice HNSW (raw — não tem helper no Blueprint padrão)
DB::statement('CREATE INDEX threads_comments_embedding_hnsw ON threads_comments USING hnsw (embedding vector_cosine_ops)');
```

### 4.3 Nova tabela `thread_groupings`

Migration: `create_thread_groupings_table`

```php
Schema::create('thread_groupings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('threads_source_id')->nullable()->constrained('threads_sources')->nullOnDelete();
    $table->foreignId('analysis_profile_id')->nullable()->constrained('analysis_profiles')->nullOnDelete();
    $table->string('title')->nullable();           // gerado pela IA
    $table->text('summary')->nullable();           // overview gerado pela IA
    $table->string('qualitative_label', 100)->nullable(); // ex: verificavel, especulativo
    $table->decimal('aggregated_relevance', 5, 2)->nullable(); // média dos ai_relevance_score
    $table->unsignedInteger('total_comments')->default(0);
    $table->string('status', 20)->default('draft'); // draft | published
    $table->jsonb('ai_meta')->nullable();          // provider, model, latency_ms, tokens
    $table->timestamps();

    $table->index(['threads_source_id', 'status']);
    $table->index(['status', 'created_at']);
});
```

**Nota de nomenclatura:** `thread_groupings` (inglês, prefixo `thread_*` consistente com o schema); UI exibe "Agrupamentos" / "Agrupados" em pt-BR. Não usar `groups` para evitar conflito semântico com outros domínios do hub.

---

## 5. Variáveis de Ambiente

Adicionar ao `.env.example`:

```bash
# ── Embeddings ──────────────────────────────────────────────────────────────
# Provider ativo: openai | ollama
EMBEDDING_PROVIDER=openai

# Modelo e dimensões — devem ser consistentes entre si
# OpenAI: text-embedding-3-small = 1536 dims
# Ollama: nomic-embed-text = 768 dims
EMBEDDING_OPENAI_MODEL=text-embedding-3-small
EMBEDDING_OLLAMA_MODEL=nomic-embed-text
EMBEDDING_DIMENSIONS=1536

# Espaçamento entre dispatches do job de embedding (segundos)
THREADS_EMBEDDING_DISPATCH_SPACING_SECONDS=2

# ── Clustering ──────────────────────────────────────────────────────────────
# Threshold de cosine similarity para agrupar (0.0 a 1.0)
# 0.82 = muito similares | 0.70 = mais abrangente
THREADS_CLUSTERING_SIMILARITY_THRESHOLD=0.82

# Tamanho mínimo para formar um agrupamento (singletons abaixo disso)
THREADS_CLUSTERING_MIN_GROUP_SIZE=2

# Tamanho máximo por agrupamento
THREADS_CLUSTERING_MAX_GROUP_SIZE=50

# ── Resumo por LLM ──────────────────────────────────────────────────────────
# Máximo de summaries enviados para a LLM nomear um agrupamento
THREADS_GROUPING_SUMMARY_MAX_ITEMS=15
```

---

## 6. Fases de Implementação

### Fase A — Infraestrutura pgvector + migrations

**Objetivo:** habilitar pgvector e adicionar schema sem alterar comportamento existente.

#### A.1 Checklist de infra

- [ ] Trocar imagem para `pgvector/pgvector:pg17` no compose de infra local
- [ ] Trocar imagem no `docker-compose.yml` de produção
- [ ] `docker compose pull` + `up -d` no postgres
- [ ] Confirmar: `DB::select("SELECT extname FROM pg_extension WHERE extname = 'vector'")` retorna resultado

#### A.2 Migrations (ordem)

1. `create_thread_groupings_table` — tabela de agrupamentos (sem FK ainda)
2. `add_grouping_fields_to_threads_comments` — campos `approved`, `embedded_at`, `embedding`, `thread_grouping_id` + FK + índices

**Atenção:** a migration do índice HNSW usa `DB::statement()` raw. O cast `vector` no Blueprint requer que a extensão já esteja ativa no momento da migration — confirmar extensão antes de rodar.

#### A.3 Models

**`ThreadsComment`** — novos casts e scopes:
```php
// Casts
'approved'               => 'boolean',
'approved_at'            => 'datetime',
'embedded_at'            => 'datetime',
'embedding'              => 'array',
'is_grouping_highlight'  => 'boolean',

// Relacionamento
public function grouping(): BelongsTo
{
    return $this->belongsTo(ThreadGrouping::class, 'thread_grouping_id');
}

// Scopes
public function scopeApproved(Builder $q): Builder
{
    return $q->where('approved', true);
}

public function scopePendingEmbedding(Builder $q): Builder
{
    return $q->where('approved', true)->whereNull('embedded_at');
}

public function scopePendingGrouping(Builder $q): Builder
{
    return $q->where('approved', true)
             ->whereNotNull('embedded_at')
             ->whereNull('thread_grouping_id');
}
```

**`ThreadGrouping`** (novo — `app/Models/ThreadGrouping.php`):
```php
class ThreadGrouping extends Model
{
    protected $fillable = [
        'threads_source_id', 'analysis_profile_id',
        'title', 'summary', 'qualitative_label',
        'aggregated_relevance', 'total_comments',
        'status', 'ai_meta',
    ];

    protected $casts = ['ai_meta' => 'array'];

    public function source(): BelongsTo { ... }
    public function profile(): BelongsTo { ... }
    public function comments(): HasMany
    {
        return $this->hasMany(ThreadsComment::class, 'thread_grouping_id');
    }
    public function highlight(): HasOne
    {
        return $this->hasOne(ThreadsComment::class, 'thread_grouping_id')
                    ->where('is_grouping_highlight', true);
    }
}
```

#### A.4 Critério de pronto

- [ ] `php artisan migrate` roda sem erros em dev e produção
- [ ] `SELECT extname FROM pg_extension WHERE extname = 'vector'` retorna resultado
- [ ] `ThreadsComment::pendingEmbedding()->count()` funciona sem erro
- [ ] Nenhum comportamento existente alterado (Review, Published intactos)

---

### Fase B — EmbeddingService

**Objetivo:** camada de geração de embeddings seguindo o padrão do `AiRouterService` — local-first, fallback automático, agnóstica de provider.

#### B.1 Estrutura

```
app/Services/Embedding/
  EmbeddingService.php
  EmbeddingResult.php
```

**`EmbeddingResult`:**
```php
readonly class EmbeddingResult {
    public function __construct(
        public array  $vector,
        public string $provider,  // openai | ollama
        public string $model,
        public int    $dimensions,
        public int    $latency_ms,
    ) {}
}
```

**`EmbeddingService::embed(string $text): EmbeddingResult`:**
- Tenta provider em `EMBEDDING_PROVIDER`
- Fallback para OpenAI se Ollama falhar ou `OLLAMA_ENABLED=false`
- Loga `embedding.generated` / `embedding.failure` (mesmo padrão de `ai.completion`)
- Lança `RuntimeException` se todos falharem (job faz retry via Horizon)

#### B.2 Chamadas HTTP

**OpenAI:**
```php
Http::withToken(config('services.openai.api_key'))
    ->post('https://api.openai.com/v1/embeddings', [
        'input' => $text,
        'model' => config('services.embedding.openai_model'),
    ])->json('data.0.embedding');
```

**Ollama:**
```php
Http::post(config('services.ollama.base_url') . '/api/embeddings', [
    'model'  => config('services.embedding.ollama_model'),
    'prompt' => $text,
])->json('embedding');
```

#### B.3 `config/services.php` — bloco embedding

```php
'embedding' => [
    'provider'         => env('EMBEDDING_PROVIDER', 'openai'),
    'dimensions'       => (int) env('EMBEDDING_DIMENSIONS', 1536),
    'openai_model'     => env('EMBEDDING_OPENAI_MODEL', 'text-embedding-3-small'),
    'ollama_model'     => env('EMBEDDING_OLLAMA_MODEL', 'nomic-embed-text'),
    'dispatch_spacing' => (int) env('THREADS_EMBEDDING_DISPATCH_SPACING_SECONDS', 2),
],
```

#### B.4 Critério de pronto

- [ ] `EmbeddingService::embed('texto teste')` retorna `EmbeddingResult` com vetor
- [ ] Fallback OpenAI funciona quando `OLLAMA_ENABLED=false`
- [ ] `EmbeddingServiceTest` cobre: sucesso OpenAI, fallback, exceção quando ambos falham
- [ ] Custo estimado logado para rastreabilidade futura

---

### Fase C — Job de Embedding

**Objetivo:** job assíncrono que gera e persiste o embedding de um comentário aprovado. Segue exatamente o padrão de `ClassifyCommentsJob` — 1 item por execução, espaçamento configurável.

#### C.1 `GenerateEmbeddingJob`

```
app/Jobs/Threads/GenerateEmbeddingJob.php
Fila: ai | $tries = 3 | $backoff = [30, 120, 300]
```

```php
public function __construct(
    public readonly int  $commentId,
    public readonly bool $force = false,
) {}

public function handle(EmbeddingService $service): void
{
    $comment = ThreadsComment::find($this->commentId);

    if (!$comment || (!$this->force && $comment->embedded_at !== null)) {
        return;
    }

    // Usa ai_summary se disponível (mais limpo), senão content bruto
    $text = $comment->ai_summary ?? $comment->content;

    if (blank($text)) {
        Log::warning('threads.embedding.skipped_blank', ['id' => $this->commentId]);
        return;
    }

    $result = $service->embed($text);

    $comment->update([
        'embedding'       => $result->vector,
        'embedding_model' => $result->model,
        'embedded_at'     => now(),
    ]);

    Log::info('threads.embedding.generated', [
        'comment_id' => $this->commentId,
        'provider'   => $result->provider,
        'model'      => $result->model,
        'latency_ms' => $result->latency_ms,
    ]);
}
```

#### C.2 `DispatchPendingEmbeddingsJob`

Análogo ao `DispatchPendingThreadsClassificationJob` existente. Varre `ThreadsComment::pendingEmbedding()` e despacha `GenerateEmbeddingJob` com espaçamento via `THREADS_EMBEDDING_DISPATCH_SPACING_SECONDS`.

```
app/Jobs/Threads/DispatchPendingEmbeddingsJob.php
Fila: default
```

#### C.3 Critério de pronto

- [ ] Comentário aprovado manualmente → `GenerateEmbeddingJob::dispatch($id)` → `embedded_at` preenchido
- [ ] `embedding` armazenado como array de floats no PostgreSQL
- [ ] Fallback OpenAI funciona quando `OLLAMA_ENABLED=false`
- [ ] Testes: job com fake `EmbeddingService`, skip quando já embedado, retry em falha de provider

---

### Fase D — Job de Clustering

**Objetivo:** agrupar comentários aprovados com embedding por cosine similarity, criando registros em `thread_groupings`.

#### D.1 `GroupSimilarCommentsJob`

```
app/Jobs/Threads/GroupSimilarCommentsJob.php
Fila: default | $tries = 3 | $timeout = 120
```

```php
public function __construct(
    public readonly ?int $sourceId = null, // null = todas as sources
) {}
```

**Algoritmo — greedy clustering por threshold:**

```
1. Buscar comentários: approved=true, embedded_at NOT NULL,
   thread_grouping_id IS NULL, filtrado por source_id se fornecido

2. Para cada comentário sem agrupamento (seed):
   a. SQL via DB::select com operador <=> para encontrar vizinhos
      com similarity >= THREADS_CLUSTERING_SIMILARITY_THRESHOLD
   b. Se vizinhos + seed >= THREADS_CLUSTERING_MIN_GROUP_SIZE:
      - Criar ThreadGrouping (status=draft)
      - Vincular todos via thread_grouping_id
      - Marcar is_grouping_highlight=true no de maior ai_relevance_score
      - Atualizar total_comments e aggregated_relevance
      - Dispatch SummarizeGroupingJob para este agrupamento
   c. Se não atingir min: comentário permanece como singleton

3. Log: threads.grouping.completed com stats
```

**Query de vizinhos:**
```sql
SELECT id, ai_summary, ai_relevance_score,
       1 - (embedding <=> :seed_vector::vector) AS similarity
FROM threads_comments
WHERE approved = true
  AND embedded_at IS NOT NULL
  AND thread_grouping_id IS NULL
  AND id != :seed_id
  AND 1 - (embedding <=> :seed_vector::vector) >= :threshold
ORDER BY similarity DESC
LIMIT :max_size
```

#### D.2 Singletons

Comentários que não formam grupo suficiente ficam com `thread_grouping_id = null`. A aba **Agrupados** exibe uma seção **"Sem similar"** para decisão manual: publicar individualmente ou ignorar.

#### D.3 Critério de pronto

- [ ] Job cria agrupamentos com 2+ comentários similares corretamente
- [ ] Comentários vinculados têm `thread_grouping_id` preenchido
- [ ] Destaque (`is_grouping_highlight`) marcado no de maior relevância
- [ ] Singletons permanecem com `thread_grouping_id = null`
- [ ] `SummarizeGroupingJob` disparado para cada agrupamento criado
- [ ] Testes: vetores fake, threshold respeitado, min_size respeitado

---

### Fase E — Job de Resumo via LLM

**Objetivo:** após clustering matemático, LLM recebe apenas os `ai_summary` do grupo e gera título, overview e label qualitativo.

#### E.1 `SummarizeGroupingJob`

```
app/Jobs/Threads/SummarizeGroupingJob.php
Fila: ai | $tries = 3
```

**Lógica:**
```php
$grouping = ThreadGrouping::with('comments')->find($this->groupingId);

$summaries = $grouping->comments()
    ->whereNotNull('ai_summary')
    ->orderByDesc('ai_relevance_score')
    ->limit(config('services.threads.grouping_summary_max_items', 15))
    ->pluck('ai_summary')
    ->toArray();

if (empty($summaries)) {
    Log::warning('threads.grouping.no_summaries', ['id' => $this->groupingId]);
    return;
}

$systemPrompt = <<<PROMPT
Você recebe resumos de comentários agrupados por similaridade semântica.
Gere em JSON válido (sem markdown):
- "title": string curta (máx 60 chars) descrevendo o tema central
- "summary": 2-3 frases resumindo o que o grupo representa
- "qualitative_label": exatamente um de:
  "verifiable" | "speculative" | "opinion" | "personal_experience" | "misinformation" | "other"
PROMPT;

$result = $neuronAI->complete(
    userPrompt: implode("\n---\n", $summaries),
    task: AiTask::Summary,
    system: $systemPrompt,
    expectJson: true,
);

$grouping->update([
    'title'              => $result['title'] ?? null,
    'summary'            => $result['summary'] ?? null,
    'qualitative_label'  => $result['qualitative_label'] ?? null,
    'ai_meta'            => [...],
]);
```

**Labels em pt-BR no front** (mapeados do valor em inglês do banco):

| Valor no banco | Exibição na UI |
|---|---|
| `verifiable` | Verificável |
| `speculative` | Especulativo |
| `opinion` | Opinião |
| `personal_experience` | Experiência pessoal |
| `misinformation` | Desinformação |
| `other` | Outro |

#### E.2 Critério de pronto

- [ ] Agrupamento recebe `title`, `summary` e `qualitative_label` após job
- [ ] Usa `NeuronAIService` existente (mesma cadeia de fallback da classificação)
- [ ] Log `threads.grouping.summarized` com provider e latência
- [ ] Testes: job com NeuronAI fake, skip gracioso sem summaries

---

### Fase F — Hub UI: aba Aprovados

**Objetivo:** introduzir o estado `approved` e nova aba "Aprovados" no `HubPage`.

#### F.1 Mudança no fluxo de Review

Na aba Review, o botão **"Publicar"** passa a ser **"Aprovar"**:

| Ação antes | Ação depois |
|---|---|
| `publishComment` → `is_public=true` | `approveComment` → `approved=true`, `approved_at=now()` |
| `batchPublishSelected` | `batchApproveSelected` + dispara `DispatchPendingEmbeddingsJob` |

Comentários já com `is_public=true` (publicados antes desta feature) permanecem intocados. Nenhuma retroativa necessária.

#### F.2 Nova aba "Aprovados"

```
Tab: approved (query string ?tab=approved)
Label na UI: "Aprovados"
Componente: App\Livewire\Threads\HubPage (mesma classe, nova aba)
```

**Filtros:** source, status de embedding (`embedded` / `pending_embedding`)  
**Ordenação:** relevância IA, data de aprovação  
**Colunas:** comentário, source, resumo IA, relevância, status embedding, ações

**Ações por linha:**
- `dispatchEmbedding(commentId)` — força `GenerateEmbeddingJob`
- `unapproveComment(commentId)` — volta para `pending_review`

**Ações globais:**
- `dispatchAllPendingEmbeddings` → enfileira `DispatchPendingEmbeddingsJob`
- `runGrouping(?sourceId)` → enfileira `GroupSimilarCommentsJob`

**Contador no cabeçalho:**
```
[N] aprovados · [M] com embedding · [K] aguardando embedding
```

#### F.3 Critério de pronto

- [ ] Aprovar na aba Review funciona (individual e em lote)
- [ ] Aba Aprovados lista com filtros e contadores
- [ ] Dispatch de embeddings pendentes funciona
- [ ] Dispatch de clustering funciona
- [ ] `ThreadsHubPageTest` cobre: approve, unapprove, dispatch actions

---

### Fase G — Hub UI: aba Agrupados

**Objetivo:** interface de curadoria dos agrupamentos gerados pela IA.

#### G.1 Nova aba "Agrupados"

```
Tab: groupings (query string ?tab=groupings)
Label na UI: "Agrupados"
```

**Layout de card expandível:**

```
┌──────────────────────────────────────────────────────────────────────┐
│ [label badge pt-BR]   Título do agrupamento              Score 95.0  │
│ Overview/summary gerado pela IA...                                   │
│                               [Publicar] [Editar] [Regenerar] [Descart]│
│ ▼ 12 comentários similares                                           │
│   ★ @usuario1 · "resumo do comentário destaque"      [↗ ver original]│
│     @usuario2 · "outro resumo"                       [↗ ver original]│
└──────────────────────────────────────────────────────────────────────┘
```

**Filtros:** source, status (`draft` / `published`), label qualitativo  
**Ordenação:** relevância agregada, data, total de comentários

**Seção Singletons (sem similar):**
- Comentários aprovados sem `thread_grouping_id`
- Ações: publicar individualmente, ignorar

#### G.2 Ações por agrupamento

| Ação Livewire | Comportamento |
|---|---|
| `publishGrouping(id)` | `status=published`, `is_public=true` em todos os comentários do grupo |
| `unpublishGrouping(id)` | `status=draft`, `is_public=false` em todos |
| `editGrouping(id)` | Edição inline de `title`, `summary`, `qualitative_label` |
| `saveGrouping(id)` | Persiste edição inline |
| `discardGrouping(id)` | Desvincula comentários (`thread_grouping_id=null`), deleta agrupamento |
| `regenerateSummary(id)` | Re-dispara `SummarizeGroupingJob` |
| `setHighlight(commentId)` | Troca `is_grouping_highlight` dentro do grupo |
| `removeFromGrouping(commentId)` | Desvincula 1 comentário, volta para singletons |

#### G.3 Critério de pronto

- [ ] Listagem de agrupamentos com expand/collapse dos comentários
- [ ] Publicar agrupamento publica todos os comentários do grupo
- [ ] Edição inline de título/summary funciona
- [ ] Seção singletons visível com ações
- [ ] Links `↗` para post original no Threads (via `threads_comments → threads_post → url`)
- [ ] `ThreadsHubPageTest` cobre: publish, discard, edit, singleton actions

---

### Fase H — Atualização de documentação

**Objetivo:** manter SPEC.md e CHANGELOG alinhados com a implementação.

Seções a adicionar/atualizar no `SPEC.md` principal:

- `## Threads Agrupados (pgvector)` — schema, jobs, variáveis, estrutura de diretórios
- Tabela de Jobs — 4 novos jobs
- Estrutura de Diretórios Laravel — novos paths

Registrar no `CHANGELOG.md` por fase concluída (padrão existente).

---

## 7. Tabela de Jobs

| Job | Fila | Trigger | Análogo existente |
|---|---|---|---|
| `GenerateEmbeddingJob` | `ai` | Aprovação / manual | `ClassifyCommentsJob` |
| `DispatchPendingEmbeddingsJob` | `default` | Manual via hub | `DispatchPendingThreadsClassificationJob` |
| `GroupSimilarCommentsJob` | `default` | Manual via hub | — |
| `SummarizeGroupingJob` | `ai` | Após `GroupSimilarCommentsJob` criar grupo | `ClassifyCommentsJob` |

---

## 8. Rotas e Componentes

Toda a UI é via Livewire actions no `HubPage` existente. **Nenhuma nova rota HTTP** é necessária para esta feature — apenas novas abas e ações no componente.

| Contexto | Componente |
|---|---|
| Hub Threads (ampliado) | `App\Livewire\Threads\HubPage` |
| Model agrupamento | `App\Models\ThreadGrouping` |

---

## 9. Decisões Arquiteturais (ADRs)

### ADR-001: pgvector ao invés de LLM em lote para clustering

**Contexto:** com 200–500 comentários aprovados, enviar tudo de uma vez para LLM seria ineficiente (custo, janela de contexto, coerência da resposta).

**Decisão:** clustering matemático via pgvector (cosine similarity). LLM entra apenas para nomear os grupos já formados, recebendo só os `ai_summary` (poucos tokens).

**Consequências:**
- ✅ Custo de embedding irrisório (~$0.007 para 500 comentários com OpenAI)
- ✅ Clustering agnóstico de idioma
- ✅ Escala para milhares de comentários sem custo proporcional
- ❌ Requer pgvector instalado (troca de imagem Docker)
- ❌ Trocar de modelo de embedding requer re-gerar todos os vetores

### ADR-002: FK direta ao invés de tabela pivot

**Contexto:** um comentário pertence a no máximo um agrupamento.

**Decisão:** `threads_comments.thread_grouping_id` FK direta, sem pivot. `is_grouping_highlight` boolean no próprio comentário.

**Consequências:**
- ✅ Queries mais simples
- ✅ Menor overhead de joins
- ❌ Comentário não pode aparecer em dois agrupamentos (comportamento correto para o domínio)

### ADR-003: Aprovados como estado intermediário, não novo status

**Contexto:** `threads_comments` já tem campo `status` com valores específicos do pipeline de classificação IA.

**Decisão:** campo booleano `approved` separado, não misturar com `status`. Mantém semântica clara: `status` = resultado da classificação IA; `approved` = decisão humana de curadoria.

**Consequências:**
- ✅ Não quebra lógica existente de `status`
- ✅ Filtros independentes no hub
- ❌ Dois campos para rastrear estado do comentário (aceitável pela separação de responsabilidades)

### ADR-004: Nomenclatura inglês no código, pt-BR no front

**Contexto:** padrão do projeto é código/banco em inglês.

**Decisão:** tabela `thread_groupings`, campo `qualitative_label`, valores do banco em inglês (`verifiable`, `speculative`). UI exibe em pt-BR via mapeamento no Blade/Livewire.

**Consequências:**
- ✅ Consistência com o restante do código
- ✅ Facilita uso do código em outros contextos futuros
- ❌ Mapeamento extra no front (trivial)

---

## 10. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| pgvector não disponível em produção | Alta (imagem padrão não inclui) | Alto | Trocar imagem antes de qualquer migration |
| Trocar modelo de embedding com vetores existentes | Média | Médio | Documentado no ADR-001; re-gerar via comando artisan |
| Ollama sem `nomic-embed-text` na VPS | Alta (não instalado ainda) | Baixo | Usar OpenAI até instalar; custo negligível |
| Clustering de qualidade ruim (threshold incorreto) | Média | Médio | Threshold configurável via `.env`; ajustar por observação |
| Agrupamentos muito grandes (grupo genérico) | Baixa | Baixo | `MAX_GROUP_SIZE` limita; curadoria humana na aba Agrupados |

---

## 11. Instalação do `nomic-embed-text` na VPS (quando pronto para produção)

```bash
# Na VPS, como usuário que roda Ollama
ollama pull nomic-embed-text

# Verificar
ollama list | grep nomic

# Testar
curl http://localhost:11434/api/embeddings \
  -d '{"model": "nomic-embed-text", "prompt": "teste"}'
```

RAM necessária: ~500MB–1GB. Com 32GB disponíveis, sem impacto operacional.

Após instalação, atualizar `.env` de produção:
```bash
EMBEDDING_PROVIDER=ollama
EMBEDDING_OLLAMA_MODEL=nomic-embed-text
EMBEDDING_DIMENSIONS=768
```

**Atenção:** se já houver embeddings gerados com OpenAI (1536 dims), é necessário re-gerar todos. Recomendado: em produção, começar já com Ollama para evitar inconsistência de dimensões.

---

## 12. Glossário

| Termo (código) | Exibição na UI | Significado |
|---|---|---|
| `thread_groupings` | Agrupamentos | Tabela de agrupamentos semânticos |
| `approved` | Aprovado | Comentário curado e pronto para embedding |
| `embedding` | — | Vetor numérico do texto do comentário |
| `grouping` / `groupings` | Agrupamento / Agrupados | Cluster de comentários similares |
| `is_grouping_highlight` | Destaque | Comentário representante do agrupamento |
| `singleton` | Sem similar | Comentário aprovado que não formou grupo |
| `qualitative_label` | Classificação | Label gerado pela IA para o agrupamento |
| `cosine similarity` | Similaridade | Métrica de proximidade entre vetores (0..1) |
| `threshold` | Limiar | Similaridade mínima para agrupar |

---

## 13. Referência Rápida de Classes e Arquivos

| Área | Arquivo |
|---|---|
| Serviço de embedding | `app/Services/Embedding/EmbeddingService.php` |
| Resultado de embedding | `app/Services/Embedding/EmbeddingResult.php` |
| Job: gerar embedding | `app/Jobs/Threads/GenerateEmbeddingJob.php` |
| Job: dispatch pendentes | `app/Jobs/Threads/DispatchPendingEmbeddingsJob.php` |
| Job: clustering | `app/Jobs/Threads/GroupSimilarCommentsJob.php` |
| Job: resumo LLM | `app/Jobs/Threads/SummarizeGroupingJob.php` |
| Model: agrupamento | `app/Models/ThreadGrouping.php` |
| Hub Threads (ampliado) | `app/Livewire/Threads/HubPage.php` |
| Config embedding | `config/services.php` → bloco `embedding` |
| Variáveis de ambiente | `.env.example` → bloco `Embeddings / Clustering` |
| Testes: EmbeddingService | `tests/Feature/Threads/EmbeddingServiceTest.php` |
| Testes: jobs | `tests/Feature/Threads/ThreadsGroupingsJobsTest.php` |
| Testes: hub | `tests/Feature/Threads/ThreadsHubPageTest.php` (expandido) |
| Histórico | `CHANGELOG.md` |

---

## 14. Próximos Passos

Ordem recomendada de execução:

1. **Trocar imagem Docker** para `pgvector/pgvector:pg17` (local + produção) — pré-requisito de tudo
2. **Fase A** — migrations e models (verificar extensão antes)
3. **Fase B** — `EmbeddingService` com testes
4. **Fase C** — `GenerateEmbeddingJob` (já dá para testar geração de embeddings de verdade)
5. **Fase F** — aba Aprovados no hub (permite aprovar comentários e acionar embedding)
6. **Fase D** — `GroupSimilarCommentsJob`
7. **Fase E** — `SummarizeGroupingJob`
8. **Fase G** — aba Agrupados no hub
9. **Fase H** — atualização de documentação

O caminho crítico para validação end-to-end é **A → B → C → F** — com essas 4 fases já é possível ver embeddings sendo gerados para comentários aprovados e validar qualidade antes de investir no clustering.
