# SPEC — Inbox WhatsApp

Webhook Evolution, roteamento por origem, persistência em `message_logs`, análise text-first profile-driven.

Tabelas transversais usadas (definidas em [core](../core/SPEC.md)): `monitored_sources`, `analysis_profiles`.

---

## Banco

### `message_logs`

Registro de mensagens processadas (grupo/DM).

```
id,
monitored_source_id (FK nullable),
chat_jid, sender_jid (nullable),
direction          -- inbound | outbound
message_type       -- text, audio, image, video, document, sticker,
                   -- location, contact, poll, reaction, url, unknown, other
body,
mentions (json),
quoted_evolution_message_id,

-- análise IA
intent, sentiment, confidence, category,
ai_pipeline_status,    -- classified | pending_media_processing |
                       -- pending_text_extraction | skipped_no_profile

-- mídia (preparado para V2)
transcription_text, transcription_provider, transcribed_at,
vision_summary, vision_provider,

metadata (json),
is_processed (bool),
evolution_message_id (unique nullable),
timestamps
```

Índices: `(monitored_source_id, created_at)`, `(chat_jid, created_at)`, `sender_jid`.

`message_type` é **string** (não ENUM SQL) para aceitar novos tipos sem migration. Valores novos vindos da Evolution chegam como `unknown` ou `other` até serem mapeados.

### `message_attachments`

Mídia/arquivos ligados a `message_logs`; objeto no MinIO usando `media_storage_prefix` da fonte quando aplicável.

```
id, message_log_id (FK),
kind, original_file_name, mime_type,
storage_path, file_bytes,
duration_seconds, width, height,
sha256, metadata (json), timestamps
```

### `reminders`

```
id,
kind (text|url|image|audio|document),
body, file_path,
url_title, url_description, url_image,
category, is_archived,
message_log_id (FK nullable),
timestamps
```

---

## Webhook Evolution

### Endpoint

`POST /webhook/whatsapp` → produção `https://hub.raphael-martins.com/webhook/whatsapp`.

Na instância Evolution, habilitar pelo menos `MESSAGES_UPSERT` (e opcionalmente `SEND_MESSAGE` para redundância). O backend normaliza nomes (`MESSAGES_UPSERT` → `messages.upsert`, `SEND_MESSAGE` → `send.message`) em `EvolutionWebhookPayloadNormalizer`.

### Formato do body

`data` é frequentemente um **objeto único** com `key` + `message` + `messageType` (sem array `messages[]`). O extrator suporta os dois formatos. Mensagens com wrappers Baileys (`ephemeralMessage`, etc.) são desembrulhadas no `EvolutionMessagesUpsertExtractor`.

### Auth

`EVOLUTION_WEBHOOK_SECRET` deve coincidir com o `apikey` no JSON do body e/ou nos headers `apikey`, `Authorization: Bearer`, `x-api-key`.

### Logs

Produção: webhook não loga payload completo. Com `APP_DEBUG=true`, `Log::debug` resume `correlation_id` e `routing`. Falhas de credencial geram `Log::warning`.

---

## Roteamento (`WebhookRouterService`)

Eventos HTTP tratados como mensagem: `messages.upsert` e `send.message`.

Regras de roteamento, **em ordem**:

1. `fromMe = true` + JID terminando em `@s.whatsapp.net` → `ProcessPersonalWhatsAppMessage`.
2. `chat_jid` igual a `config('services.whatsapp.notes_solo_group_jid')` (`WHATSAPP_NOTAS_GRUPO_JID`) → `ProcessPersonalWhatsAppMessage`. Grupo "só você" para notas/mídia (workaround para mídia 1:1 que não webhooka). `monitored_source_id` segue o registro `group` no banco se existir.
3. JID em `monitored_sources` com `kind = contact` → `ProcessContactWhatsAppMessage`.
4. JID em `monitored_sources` com `kind = group` (e não coberto pelo item 2) → `ProcessGroupWhatsAppMessage`.
5. Qualquer outro → persiste `message_logs` com rota ignorada e sem job.

Nos caminhos `personal`, `contact` e `group`, os jobs executam `ProcessMessageLogAnalysisService`, que aplica análise profile-driven e persiste resultado em `message_logs` (`intent`, `category`, `sentiment`, `confidence`, `ai_pipeline_status` e `metadata.analysis`).

---

## Pipeline text-first

Escopo atual: **classificação estruturada roda somente quando há texto disponível**. Para mensagens sem texto:

| Status | Semântica |
|--------|-----------|
| `classified` | Análise textual concluída com sucesso (`is_processed = true`). |
| `pending_media_processing` | Mídia/anexo (`audio`, `image`, `video`, `document`) ou metadados indicando conteúdo binário (`is_processed = false`). |
| `pending_text_extraction` | Sem texto útil, mas com potencial de enriquecimento futuro (`is_processed = false`). |
| `skipped_no_profile` | Não há profile aplicável (`is_processed = true`, aguardando ajuste operacional da source). |

Itens em estados `pending_*` **não** são finalizados (`is_processed = false`), preservando reprocessamento em fase futura de content extraction.

Observação: no payload de metadados, `metadata.analysis.status` pode aparecer como `completed` enquanto `ai_pipeline_status` fica `classified` — são camadas semânticas distintas.

---

## Jobs

| Job | Fila | Trigger |
|-----|------|---------|
| `ProcessPersonalWhatsAppMessage` | `ai` | Webhook `fromMe` ou grupo notas solo. |
| `ProcessContactWhatsAppMessage` | `ai` | Webhook contato monitorado. |
| `ProcessGroupWhatsAppMessage` | `ai` | Webhook grupo monitorado. |
| `ReprocessMessageLogAnalysisJob` | `ai` | Reprocessamento manual no hub de monitored-sources. |

Enriquecimento automático de lembretes de URL (Open Graph) via job dedicado **não está implementado** — tabela `reminders` e modelo existem; fluxo a definir. Intenção e escopo: [BACKLOG](../roadmap/BACKLOG.md) (secção *Inbox WhatsApp — lembretes de URL (Open Graph)*).

Padrão para todos: `$tries = 3`, `failed()` implementado, logs estruturados.

---

## Integração Evolution para envio

`App\Services\EvolutionService`:

- `sendText(string $number, string $text): void` — `POST /message/sendText/{instance}` com header `apikey` (`services.evolution.api_key`), body `{ number, text }`. URL base `services.evolution.url`, instância `services.evolution.instance`. Erros HTTP/conexão → `RuntimeException` para retry no Horizon.
- `isConfigured(): bool` — exige `EVOLUTION_URL`, `EVOLUTION_API_KEY`, `EVOLUTION_INSTANCE`.
- `sendMedia` / `sendDocument` — **ainda não implementados** (ver [BACKLOG](../roadmap/BACKLOG.md)).

---

## JIDs e variáveis

- Número individual: `5511948863848@s.whatsapp.net`.
- Grupo: `120363XXXXXXXX@g.us`.
- `fromMe = true` — mensagem enviada pela própria instância.

```env
EVOLUTION_URL=https://evo.raphael-martins.com
EVOLUTION_API_KEY=
EVOLUTION_INSTANCE=raphael
EVOLUTION_WEBHOOK_SECRET=

WHATSAPP_GRUPO_CASA_JID=
WHATSAPP_NOTAS_GRUPO_JID=
WHATSAPP_UTILITIES_HOME_GROUP_JID=   # fallback para WHATSAPP_GRUPO_CASA_JID
```

---

## V2 — monitoramento profundo (backlog)

Fora do escopo atual:

- Persistir **todo** tráfego relevante em grupos (remetente, citações, menções, mídia no MinIO com prefixo por fonte, metadados brutos suficientes para reconstruir contexto).
- Transcrição Whisper local + fallback NeuronAI.
- LLM visão via NeuronAI para imagens.
- Permissões por grupo (`monitored_source_user`).

Detalhes em [BACKLOG](../roadmap/BACKLOG.md).
