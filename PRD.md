# PRD — Raphael Hub

## Visão

Plataforma pessoal de automação para o número WhatsApp do Raphael, com dashboard web próprio. O hub centraliza tarefas reais do dia a dia (faturas, lembretes, inbox pessoal) e cresce em módulos discretos conforme novas necessidades aparecem — eventos privados, álbuns de mídia, curadoria de oportunidades. WhatsApp é a interface natural; o dashboard é onde se opera e audita.

Princípios:

- **WhatsApp como entrada padrão.** Nada de “mais um app para abrir”.
- **IA híbrida.** Ollama no host para tarefas leves e frequentes; nuvem (Groq → Anthropic → OpenAI) quando precisa de capacidade.
- **Infra própria.** MinIO, Evolution e Postgres em containers dedicados ao hub. Sem depender de stack compartilhada com outros projetos.
- **Operação observável.** Horizon, schedule explícito, jobs idempotentes.

---

## Personas

| Quem | Como interage |
|------|---------------|
| Raphael | Dashboard web + mensagens para si mesmo no WhatsApp (notas, links, lembretes). |
| Ildacir (pai) | WhatsApp — pergunta valores de fatura, recebe lembretes. |
| Grupo da Casa | Recebe notificações automáticas de vencimento. |
| Convidados de eventos | Página pública de inscrição + e-mail de confirmação + ingresso PDF. |
| Contribuidores de álbum | Convite por link, verificação por e-mail, upload com TTL. |
| Público de Oportunidades | Feed em `/oportunidades`, voto anônimo. |

---

## Módulos atuais

Cada módulo tem SPEC dedicada com schema, contratos, endpoints e fluxos. Esta seção descreve o **propósito de produto** de cada um. Para implementação, seguir os links.

### Faturas Embasa / Coelba

Scraping agendado (Playwright), persistência idempotente, PDF no MinIO, lembrete WhatsApp para o grupo da casa antes do vencimento e diário enquanto não pago. Resposta direta a perguntas do pai via WhatsApp (sem scrape em tempo real — lê do banco).

→ [docs/utilities/SPEC.md](docs/utilities/SPEC.md)

### Inbox pessoal WhatsApp

Webhook Evolution com roteamento por origem: DM consigo (`fromMe`), grupo “notas solo” (workaround para mídia 1:1), contato monitorado, grupo monitorado. Persiste em `message_logs` e despacha jobs. Análise é **text-first** com profile-driven (`analysis_profiles`); mídia fica em estado pendente para extração futura.

→ [docs/whatsapp/SPEC.md](docs/whatsapp/SPEC.md)

### Threads e Oportunidades

Scraping autenticado do Threads (sessão persistida no Playwright), ingestão idempotente, classificação IA por comentário com threshold de relevância, curadoria no hub (`/hub/threads` — abas Sources/Review/Published), feed público `/oportunidades` com busca, filtros e votação anônima dedupada por fingerprint.

→ [docs/threads/SPEC.md](docs/threads/SPEC.md)

### Eventos privados

Hub `/hub/events` para criar evento (slug, capacidade, schema de formulário do convidado, link de referência). API pública para landing externa (`api/v1/events/{slug}/config|register`) com Turnstile e rate limit. Fluxo de dupla confirmação por e-mail: registro cria convidado `pending_email` → link de confirmação → ingresso PDF com QR (SVG) entregue por e-mail. Portaria mobile-first em `/events-checkin` com leitor de QR pela câmera.

→ [docs/events/SPEC.md](docs/events/SPEC.md)

### Álbuns de mídia

Hub `/hub/albums` (Livewire) para CRUD de álbuns hierárquicos (até 2 níveis), upload com processamento assíncrono (GD/WebP), tipos de acesso público/senha/token/one-time com lockout. Viewer público `/albums/{slug}` com lightbox. **Contribuição externa**: convite por link, verificação por e-mail, upload com TTL, digest consolidado ao dono (e-mail + WhatsApp).

→ [docs/album/SPEC.md](docs/album/SPEC.md)

### Dashboard do hub

Rota `/dashboard` com grade de cards (config em `hub_dashboard.php`) para cada módulo. Menu superior espelha os mesmos atalhos. Autenticação Breeze, sem registro público — login restrito por e-mail no `.env`. Horizon protegido pelo mesmo middleware.

→ [docs/core/SPEC.md](docs/core/SPEC.md) (seção *Dashboard*)

### Camada de IA

NeuronAI como abstração, `AiRouterService` decidindo provedor por tarefa (Ollama → Groq → Anthropic → OpenAI), gateway `POST /iara` para chamar a API de produção sem Ollama local, análise profile-driven (`analysis_profiles`) reutilizada por Threads e WhatsApp.

→ [docs/core/SPEC.md](docs/core/SPEC.md) (seção *IA*)

---

## Princípios não-funcionais

- **Stack em Docker** na VPS, rede `raphael-bridge` isolada. PostgreSQL, Redis, MinIO, Evolution dedicados ao hub. Detalhes em [docs/operations/SPEC.md](docs/operations/SPEC.md).
- **Credenciais sensíveis** (concessionárias, API keys) somente no `.env` — nunca no banco.
- **Filas Redis com Horizon**: `default`, `scraping`, `notifications`, `ai`, `media`.
- **Schedule Laravel** em worker dedicado em produção.
- **Timezone**: `America/Sao_Paulo` em todos os containers.
- **Deploy** automatizado via GitHub Actions; `deploy.sh` na VPS faz diff e rebuild seletivo.
- **Logs estruturados** para scraping (debug de seletores), IA (`ai.completion` / `ai.completion_failure`) e webhook (correlation_id em debug).

---

## Métricas de sucesso

- Pai pergunta valor de fatura no WhatsApp e recebe resposta correta com PDF.
- Grupo da casa recebe lembrete automático antes do vencimento sem intervenção manual.
- Dashboard exibe histórico de consumo dos últimos 12 meses por concessionária.
- Mensagens enviadas para si mesmo no WhatsApp aparecem categorizadas no hub.
- Eventos: convidados confirmam por e-mail e fazem check-in com QR na portaria sem fricção.
- Álbuns: contribuidor externo recebe convite, verifica e-mail, faz upload, dono recebe digest.
- `/oportunidades`: feed responsivo, votação anônima funcionando com dedupe diário.

---

## O que está por vir

Backlog consolidado em [docs/roadmap/BACKLOG.md](docs/roadmap/BACKLOG.md). Itens vivos hoje:

- Monitoramento profundo de grupos WhatsApp (transcrição, OCR, multimodal).
- Permissões por grupo (`monitored_source_user`).
- RAG sobre histórico (pgvector já ativo no banco; pipeline a definir).
- Álbuns Fase F (ZIP, watermark, FFmpeg para vídeo, tags).
- Resposta IA por WhatsApp para o pai (`buildInvoiceReply`, `classificarIntencaoContato`).

Histórico de entregas em [CHANGELOG.md](CHANGELOG.md).
