# SPEC — Core (banco transversal, IA, dashboard)

Cobre o que é compartilhado entre módulos: tabelas transversais (`users`, `analysis_profiles`, `monitored_sources`), a camada de IA (NeuronAI + roteamento + gateway Iara + análise profile-driven), o dashboard hub e autenticação.

Para integrações específicas, ver as SPECs por módulo:

- [WhatsApp](../whatsapp/SPEC.md) — usa `monitored_sources` e `analysis_profiles`.
- [Threads](../threads/SPEC.md) — também usa `analysis_profiles`.
- [Utilities](../utilities/SPEC.md), [Events](../events/SPEC.md), [Album](../album/SPEC.md) — domínios próprios.

---

## Banco — tabelas transversais

### `users` (extensão além do Breeze)

```
global_role: super_admin | member (default member)
```

Pivot V2 (multi-admin de grupo) — `monitored_source_user`, ver [Roadmap](../roadmap/BACKLOG.md).

Seed opcional via `SuperAdminUserSeeder` (`HUB_SEED_*` no `.env`; senha nunca no repositório).

### `analysis_profiles`

Perfis reutilizáveis para classificação/análise IA por canal.

```
id, slug (unique), name, description,
channel (nullable),                 -- whatsapp | threads | null (genérico)
analysis_type,
system_prompt,
output_schema (json nullable),
allowed_categories (json nullable),
score_threshold (decimal nullable),
settings (json nullable),
is_active (bool), timestamps
```

- Profile padrão **`threads-opportunities`** mantém o comportamento histórico de Threads (prompt, schema, categorias, threshold). Protegido na UI contra alteração de slug/status.
- `threads_sources`, `monitored_sources` e `threads_categories` podem apontar para `analysis_profile_id`.
- Hardening idempotente: `php artisan analysis:repair-profile-linkage` garante profile padrão e backfill de vínculos.

### `monitored_sources`

```
id, kind (self|contact|group), identifier (unique JID), label,
permissions (json nullable), is_active (bool), notes,
media_storage_prefix (nullable), analysis_profile_id (FK nullable), timestamps
```

Fontes monitoradas do WhatsApp (uso detalhado em [docs/whatsapp/SPEC.md](../whatsapp/SPEC.md)). `kind`:

- `self` — DM do Raphael consigo (`fromMe + @s.whatsapp.net`).
- `contact` — número de outra pessoa (ex.: pai).
- `group` — grupo monitorado (inclui o "notas solo" e o grupo da casa).

### `monitored_source_user` (pivot — backlog)

```
id, user_id (FK), monitored_source_id (FK),
role (group_admin|viewer), timestamps
UNIQUE: user_id + monitored_source_id
```

Reservado para permissões por grupo no V2 (ver [BACKLOG](../roadmap/BACKLOG.md)). MVP atual ignora — só o super admin existe.

---

## Camada de IA

### Pacote e providers

NeuronAI (`neuron-core/neuron-ai`) com providers oficiais (`Ollama`, `OpenAILike` para Groq, `Anthropic`, `OpenAI\OpenAI`).

| Classe | Papel |
|--------|-------|
| `OllamaService` | Instancia o provider Ollama (URL com sufixo `/api`, `OLLAMA_THINK`, timeout curto). |
| `AiRouterService` | Decide qual provider tentar. Regras: tarefa leve + prompt curto → Ollama; prompt longo (acima de `AI_PROMPT_LONG_THRESHOLD`) ou modo `chat_long` → pula Ollama. Cadeia: **Ollama (se habilitado) → Groq → Anthropic → OpenAI**. Resposta vazia ou JSON inválido com `expect_json` → próximo provider. Timeouts em `config/ai.php`. Logs `ai.completion` / `ai.completion_failure`. |
| `NeuronAIService` | Façade única: `complete(userPrompt, AiTask, ?system, expectJson)` → `AiCompletionResult` (texto, `provider`, `model`, `latency_ms`, `fallback_used`). |
| `App\Enums\AiTask` | Modos HTTP: `classification`, `classify`, `sentiment`, `summary_short`, `chat`, `chat_long`, etc. |

Variável `AI_PRIMARY_PROVIDER` **não** é lida — o roteamento é o `AiRouterService`.

### Gateway `POST /iara`

Útil para chamar a API de produção sem Ollama local (ex.: do notebook). `IaraController`, body: `prompt`, `mode` opcional, `system` opcional, `expect_json` opcional.

- Fora de `local`/`testing`: header `X-Internal-Key` obrigatório (`IARA_INTERNAL_KEY`), opcional `IARA_ALLOWED_IPS`.
- Throttle dedicado, CSRF excetuado (`iara`).
- **Não** substitui o daemon Ollama na rede interna — na VPS o app chama `OLLAMA_BASE_URL` diretamente.

### Comunicação Docker ↔ Ollama

Containers Laravel não rodam no host network. Endpoint típico do Ollama para o app: **`http://172.23.0.1:11434`** (gateway da bridge Docker na VPS). Ollama escuta no host na porta 11434. Firewall deve restringir a porta à rede Docker — não expor publicamente.

### Camada genérica de análise por profile

| Classe | Papel |
|--------|-------|
| `AnalysisProfileResolver` | Resolve profile ativo por source (Threads/WhatsApp). Fallback para `threads-opportunities` no fluxo Threads. |
| `AnalysisExecutionService` | Executa classificação JSON via `NeuronAIService` com `system_prompt`, `output_schema` e `settings` do profile (foco em análise textual estruturada). |
| `ThreadsCommentToNormalizedContentAdapter` | Normaliza comentários Threads para contrato único. |
| `MessageLogToNormalizedContentAdapter` | Normaliza WhatsApp (`message_logs`) no mesmo contrato. |
| `ProcessMessageLogAnalysisService` | Orquestra análise WhatsApp e persiste status auditável. |

Reutilizada por Threads e WhatsApp. Para detalhes de uso, ver SPEC do módulo.

### Métodos de domínio ainda não implementados

Alvo no SPEC original (`PRD` F6):

- `classificarIntencaoPessoal(tipo, conteudo)` → `{intencao, sentimento, …}` em cima de `complete`.
- `classificarIntencaoContato(conteudo, permissoes)` — usado na resposta ao pai.
- `buildInvoiceReply(Invoice $invoice)` — formata resposta com valor, vencimento, PDF.

Ver [BACKLOG](../roadmap/BACKLOG.md).

### Variáveis de ambiente — IA

Bloco `OLLAMA_*`, `GROQ_*`, `ANTHROPIC_*`, `OPENAI_*`, `AI_*`, `IARA_*` no `.env.example`. Configs lidas em `config/services.php` (`ollama`, `iara`, `groq`, `anthropic`, `openai`) e `config/ai.php`.

```env
# Ollama no host
OLLAMA_ENABLED=true
OLLAMA_BASE_URL=http://172.23.0.1:11434
OLLAMA_MODEL=qwen3.5:4b
OLLAMA_THINK=false
OLLAMA_TIMEOUT=20

# Roteamento
AI_PROMPT_LONG_THRESHOLD=2000
AI_OLLAMA_TIMEOUT_SIMPLE=45
AI_GROQ_TIMEOUT=...

# Gateway Iara
IARA_INTERNAL_KEY=
IARA_ALLOWED_IPS=
IARA_GATEWAY_URL=     # só no cliente que chama a API remota

# Providers nuvem
GROQ_API_KEY=
GROQ_MODEL=llama-3.3-70b-versatile
GROQ_URL=https://api.groq.com/openai/v1
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-20250514
```

---

## Dashboard principal (hub)

- Rota autenticada `GET /dashboard` (`dashboard`): view `resources/views/dashboard.blade.php` — grade responsiva de **cards** (link, ícone, título, descrição) para cada módulo.
- Conteúdo dos cards em **`config/hub_dashboard.php`** (chave `cards`: `route`, `title`, `description`, `icon`).
- Ícones em **`resources/views/components/hub/dashboard-icon.blade.php`**.
- Navegação global em **`resources/views/layouts/navigation.blade.php`** deve refletir as mesmas rotas.

Novas features autenticadas: atualizar `config/hub_dashboard.php` + `navigation.blade.php` + componente de ícone (se símbolo novo).

---

## Hubs operacionais — fontes monitoradas e profiles

Estes hubs servem para administrar a **camada de análise** sem precisar de SQL/tinker.

### Hub fontes monitoradas

- Rota: `GET /hub/monitored-sources` (`monitored-sources.hub`).
- Componente: `App\Livewire\MonitoredSources\HubPage`.
- CRUD mínimo de `monitored_sources` (`kind`, `identifier`, `label`, `notes`, `is_active`, `analysis_profile_id`).
- Validação de compatibilidade de canal para o profile selecionado (`channel = whatsapp|null`).
- Visão resumida do pipeline (`classified`, `pending_*`, `skipped_no_profile`) por source.
- Reprocessamento manual via `ReprocessMessageLogAnalysisJob` (fila `ai`) com bloqueio quando não há source vinculada.
- Cobertura: `tests/Feature/Analysis/MonitoredSourcesHubPageTest.php`.

### Hub analysis profiles

- Rota: `GET /hub/analysis-profiles` (`analysis-profiles.hub`).
- Componente: `App\Livewire\AnalysisProfiles\HubPage`.
- CRUD mínimo: `slug`, `name`, `description`, `channel`, `analysis_type`, `system_prompt`, `score_threshold`, `is_active`.
- Edição de campos JSON (`output_schema`, `allowed_categories`, `settings`) com validação.
- Profile padrão `threads-opportunities` protegido (slug + status) para evitar quebra acidental do fallback.
- Profiles continuam **banco-driven** — não mover para `.env`.
- Cobertura: `tests/Feature/Analysis/AnalysisProfilesHubPageTest.php`.

### Hub de revisão de análises

- Rota autenticada: `GET /hub/analyses` (`analyses.hub`); detalhe em
  `GET /hub/analyses/{messageLog}` (`analyses.show`).
- Componentes: `App\Livewire\Analyses\HubPage` e `App\Livewire\Analyses\DetailPage`.
- A listagem é paginada no banco, prioriza por padrão mensagens ligadas a
  `monitored_sources` e oferece filtros por profile persistido, source, período,
  estado do pipeline, categoria e busca no corpo/JSON da análise.
- O profile histórico exibido vem de `metadata.analysis.profile_slug` (com fallback
  para `provider_meta.profile_slug`), nunca do vínculo atual da source.
- O detalhe compara o corpo original com `metadata.analysis.raw_normalized`, trata
  `offers` apenas quando é uma lista válida e mantém o JSON completo recolhido por
  padrão. Conteúdo textual e JSON são sempre escapados.
- Reprocessamento reutiliza `ReprocessMessageLogAnalysisJob`, informa o profile atual
  e só enfileira quando source e profile estão ativos e o profile aceita WhatsApp.
- Cobertura: `tests/Feature/Analysis/AnalysesHubPageTest.php`.

---

## Autenticação

- Laravel Breeze (Blade stack), sem registro público.
- Login restrito por e-mail em `HORIZON_AUTH_EMAILS`.
- Horizon (`/horizon`) e hubs internos (`/hub/*`) protegidos pelo mesmo middleware.
- Sanctum disponível para APIs autenticadas (ex.: Events tem rotas públicas, mas o painel é autenticado).

---

## Migrations relevantes

```
..._add_hub_fields_to_users_table.php           # global_role
..._create_analysis_profiles_table.php
..._create_monitored_sources_table.php
..._create_monitored_source_user_table.php      # pivot V2
..._repair_default_analysis_profile_linkage.php # hardening
```
