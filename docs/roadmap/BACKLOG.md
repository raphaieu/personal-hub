# BACKLOG — Raphael Hub

> Renomeação do antigo `docs/v2.md`. Mantém apenas **intenção, escopo e dependências** de itens **não entregues**. Histórico do que já foi feito está no [CHANGELOG.md](../../CHANGELOG.md). Detalhes técnicos de cada módulo nas SPECs em `docs/<modulo>/SPEC.md`.

Regra: este arquivo lista o que **ainda não foi feito**. Quando um item entrar em produção, mover para a SPEC do módulo (se vira contrato) e para o CHANGELOG (data de entrega).

---

## Inbox WhatsApp — monitoramento profundo de grupos

Hoje o pipeline é **text-first**: mídia fica em `pending_media_processing` / `pending_text_extraction` sem extração. O alvo é persistir todo o tráfego relevante e enriquecer com transcrição/visão.

### Escopo

- Persistir **todo** o tráfego em grupos monitorados (remetente, citações, menções, reações, enquetes).
- Anexos no MinIO usando `media_storage_prefix` da fonte (já no schema).
- Tipos de mensagem extensíveis em string (já no schema); valores novos vindos da Evolution chegam como `unknown`/`other` até serem mapeados.

### Pipeline de enriquecimento

| Ativo | Etapa | Fallback |
|-------|-------|----------|
| Áudio | Transcrição Whisper (local) | NeuronAI provedor configurado |
| Imagem | LLM visão via NeuronAI | (sem OCR local confiável no início) |
| Texto | Classificação/categorias profile-driven | já implementado |

Campos dedicados em `message_logs` (`transcription_*`, `vision_*`) já estão no schema, permitem retries idempotentes e auditoria sem JSON opaco.

### Dependências

- Job dedicado de transcrição com fila própria.
- Política de privacidade para áudios (descartar áudio após transcrição? manter? configurável por source?).
- Avaliar custo: Whisper local vs Groq vs OpenAI.

### Métodos de domínio ainda não implementados

Estes existem na intenção do PRD desde o início:

- `classificarIntencaoPessoal(tipo, conteudo)` → estrutura `{intencao, sentimento, ...}` em cima de `complete`.
- `classificarIntencaoContato(conteudo, permissoes)` — usado na resposta ao pai.
- `buildInvoiceReply(Invoice $invoice)` — formata resposta com valor, vencimento, PDF.
- `EvolutionService::sendMedia` / `sendDocument` — atualmente só `sendText` está implementado.

---

## Inbox WhatsApp — lembretes de URL (Open Graph)

A tabela **`reminders`** e o modelo já existem; campos `url_title`, `url_description`, `url_image` estão no schema para metadados de página (ver [docs/whatsapp/SPEC.md](../whatsapp/SPEC.md)). O **fluxo de enriquecimento** ainda não foi ligado ao pipeline.

### Escopo

- Ao criar/persistir lembrete do tipo **URL** (a partir do fluxo WhatsApp ou hub), despachar um **job assíncrono** que busca título/descrição/imagem (Open Graph ou fallback HTML razoável), com timeout e tratamento de falha (site bloqueado, sem OG, etc.).
- Preencher `url_title` / `url_description` / `url_image` sem bloquear a requisição do webhook.
- Fila sugerida: `default` (ou fila leve dedicada se o volume crescer).

### Referência de implementação

- Nome usado no desenho original do índice técnico: **`EnriquecerUrlLembrete`** — ainda **não** há classe com esse nome no repositório; ao implementar, registrar o job real na [SPEC do WhatsApp](../whatsapp/SPEC.md) (tabela de jobs) e remover este bloco do BACKLOG.

---

## Permissões por grupo (dashboard multi-admin)

Hoje só o `super_admin` existe (Raphael). O alvo é permitir que o pai (ou outros) tenham acesso filtrado no dashboard.

### Personas

- **Dono global** (Raphael) — acesso ao pessoal + todos os grupos + configurações sensíveis.
- **Admin de grupo** — acesso apenas a dados ligados a uma ou mais fontes `group` específicas.

### Modelo de dados (já preparado)

- `users.global_role`: `super_admin` | `member`.
- `monitored_source_user` (pivot já no schema): N:N com `role` (`group_admin` | `viewer`).

V1 ignora o pivot; a existência da tabela não quebra nada.

### Autorização

- Gates/policies checando `global_role === super_admin` OU registro em `monitored_source_user` com papel adequado para a rota/recurso.
- UI de convites por grupo.
- Telas filtradas no dashboard.

---

## RAG sobre histórico (pgvector)

O Postgres já tem `pgvector` instalado. Falta o pipeline.

### Escopo

- Embeddings de `message_logs` (texto), `reminders`, `invoices` (notas).
- Query por similaridade no hub e via WhatsApp ("o que eu mandei sobre X no mês passado?").
- Estratégia de chunking conforme tipo do conteúdo.

### Dependências

- Decisão sobre modelo de embedding (Ollama local vs OpenAI vs Groq).
- Job de embedding em fila própria.
- Tabela específica para vetores ou colunas adicionadas às existentes.

---

## Álbuns — Fase F

Recursos avançados pendentes. As fases A–E estão em produção.

- **Upload via ZIP** — recebe arquivo único, descompacta server-side, distribui mídias.
- **Tags** — categorização por álbum/mídia.
- **Watermark on-the-fly** — marca d'água em URLs assinadas (configurável por álbum).
- **Thumb/transcode de vídeo** — FFmpeg para gerar thumbnail estática e versões otimizadas. A imagem PHP **já inclui** `ffmpeg`; falta o pipeline.
- **Download ZIP do álbum** — visitante baixa álbum inteiro em ZIP.

Ver SPEC do módulo: [docs/album/SPEC.md](../album/SPEC.md).

---

## Eventos — evoluções

Funcionalidades em produção: registro com confirmação por e-mail, ingresso PDF/QR, portaria mobile-first. Backlog:

- **Templates dinâmicos de e-mail/PDF** — hoje há um template fixo; permitir customização por evento.
- **Exportação CSV** de convidados confirmados/check-in.
- **Integração nativa com Álbuns** — associar `album_id` ao evento e abrir upload aos confirmados após o evento.
- **Status `ended`/`archived`** — automação de transição com base em `ends_at`.
- **Lista de espera** quando `capacity` atinge.

---

## Threads — evoluções

- **Schedule automatizado de scraping** por source (hoje é manual via hub ou via cron customizado).
- **Métricas no hub**: posts/dia, taxa de classificação, custo IA por source.
- **Profiles alternativos** para nichos (não só `threads-opportunities`).
- **Anti-abuso no feed público**: rate limit mais fino, captcha em votação, detecção de fingerprints suspeitos.
- **Notificações por categoria favorita** (e-mail diário/semanal opt-in).

---

## Outras automações pessoais

- **OCR em imagens** recebidas via WhatsApp (uso após transcrição/visão estarem maduros).
- **Código PIX copiável** enviado junto com o lembrete de vencimento.
- **Ampliar uso de Ollama**: políticas finas de custo/privacidade por tarefa, memória de contexto.
- **Novos grupos/números monitorados** com regras próprias (ex.: grupo de trabalho, outros familiares).
- **Integração com outros projetos pessoais** no mesmo número WhatsApp (DopaCheck, Trady, Mkit, etc.).
- **Resposta automática para o pai** (depende de `classificarIntencaoContato` + `buildInvoiceReply`).

---

## ERP da vida pessoal — longo prazo

Visão de plataforma operacional pessoal (do README/PRD original, mantido aqui como referência):

- Despesas pessoais (além das concessionárias).
- Tarefas familiares (lista compartilhada, atribuições).
- Documentos (storage + busca).
- Agenda doméstica (eventos recorrentes, manutenção, vacinas/exames).
- Automações amplas (regras tipo "if X then Y" via natural language).

---

## Como evoluir sem quebrar produção

Regras que se aplicam a qualquer item deste backlog:

1. **Novas colunas opcionais** com default / nullable são seguras.
2. **Novas tabelas** referenciando as existentes são seguras; código antigo não as usa.
3. **Evitar renomear colunas em uso**; se inevitável, migration de renomeação + janela de deploy.
4. **Feature flags** (`.env`) para ativar Whisper, LLM visão, RAG, UI de admin de grupo quando estiverem prontos.
5. Quando um item entrar em produção: **mover para SPEC do módulo** + entrada no CHANGELOG. Apagar deste arquivo.
