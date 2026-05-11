# Events — Briefing Técnico

> **Referência MVP (v1) no monólito Raphael Hub:** use **[SPEC.md](SPEC.md)** como fonte única para escopo implementável, contrato HTTP consolidado, fluxo de confirmação de e-mail, PDF/QR, rotas web de portaria e divisão v1/v2. Este briefing permanece como visão ampla, ADRs e material histórico.

---

> Sistema de gerenciamento de eventos privados com lista controlada, geração automática de landing page via IA, ingressos com QR Code e álbum colaborativo via WhatsApp.

**Domínio:** `events.raphael-martins.com`
**Autor:** Raphael Martins
**Versão do briefing:** 1.0
**Data:** Abril/2026

---

## 1. Visão Geral

### 1.1 Pitch

Plataforma para criação rápida de eventos privados com lista controlada. O organizador faz upload do flyer/convite, uma IA extrai dados e paleta de cores e gera automaticamente uma landing page personalizada. Convidados acessam por links tokenizados distribuídos por promoters, se cadastram com foto, e recebem um ingresso digital com QR Code por email. No dia do evento, o check-in é feito por um PWA offline-first via leitura de QR Code. Após o evento, fotos enviadas em um grupo do WhatsApp vinculado vão automaticamente para um álbum público na própria landing.

### 1.2 Posicionamento

Este é um **projeto pessoal de portfólio** com utilidade real. Não tem ambição comercial imediata, não pretende competir com Sympla/Even/Eventbrite. O objetivo é:

- Demonstrar competência técnica em Laravel + Livewire + IA + PWA + integração WhatsApp
- Resolver problema real de organização de eventos entre amigos
- Servir como vetor de marketing pessoal (rodapé "feito por Raphael Martins")
- Material concreto pra apresentar em entrevistas e propostas

### 1.3 Público-alvo

Organizadores de eventos privados de pequeno e médio porte: aniversários, formaturas, casamentos, eventos corporativos fechados, festas entre amigos. Foco inicial: rede pessoal e profissional do autor.

### 1.4 Diferenciais

1. **Geração automática de landing via IA** a partir do flyer (texto + paleta extraída)
2. **Foto do convidado no ingresso** (verificação visual no check-in, anti-fraude de QR vazado)
3. **Captura colaborativa de fotos via WhatsApp** (grupo vinculado → álbum público)
4. **Offline-first no check-in** (PWA funciona sem internet, sincroniza quando volta)
5. **Lista controlada por links tokenizados** sem fricção de cadastro de promoters

---

## 2. Atores do Sistema

### 2.1 Organizador (Owner)

- Cria conta na plataforma (OAuth Google ou Magic Link)
- Cria evento, faz upload do flyer
- Edita dados extraídos pela IA
- Gera links tokenizados de promoters
- Acessa dashboard com métricas e lista de convidados
- Gera link tokenizado de operador de check-in
- Vincula grupo do WhatsApp ao evento

### 2.2 Promoter

- **Não tem conta na plataforma** — recebe link tokenizado do organizador
- Distribui o link entre conhecidos
- Convidados que entram pelo seu link ficam atribuídos a ele (métrica)
- Token tem validade longa (default 60-90 dias, configurável pelo organizador)
- Token revogável a qualquer momento pelo organizador

### 2.3 Convidado

- Acessa landing via link de promoter
- Preenche formulário (nome, email, foto + campos opcionais)
- Confirma email via link enviado
- Recebe ingresso por email (PDF + imagem QR Code)
- Pode solicitar ingresso via WhatsApp (fluxo inverso, descrito adiante)

### 2.4 Operador de Check-in

- **Não tem conta** — recebe link tokenizado do organizador
- Link abre PWA já autenticado
- Lê QR Codes via câmera, valida ingresso, marca presença
- Funciona offline durante o evento

### 2.5 Bot do WhatsApp

- Não é "ator humano" mas tem comportamento próprio
- Centralizado no número "Raphael Personal Hub" (instância Evolution única)
- Responde a 1:1 (recuperação de QR Code) e em grupos vinculados (captura de fotos, comandos)

---

## 3. Fluxos Principais

### 3.1 Criação de Evento

```
1. Owner faz login (Google OAuth ou Magic Link)
2. Clica "Criar evento"
3. Upload do flyer (imagem ou PDF)
4. IA processa em background:
   a) Extração de texto/dados via Claude Sonnet vision
   b) Extração de paleta via lib determinística (Vibrant.js ou equivalente PHP)
   c) Sugestão de template (festivo/elegante)
5. Owner revisa em tela de confirmação:
   - Edita campos extraídos
   - Reordena/remove blocos de conteúdo
   - Ajusta cores se necessário
   - Define slug (sugestão automática: nome-do-evento-dd-mm-yyyy)
   - Define capacidade (opcional)
   - Define quais campos do form de convidado coletar
6. Publica → landing fica acessível em events.raphael-martins.com/{slug}
7. Sistema gera código de pareamento WhatsApp (EVT-XXXX)
```

### 3.2 Distribuição via Promoters

```
1. Owner acessa "Promoters" no dashboard
2. Cria link nomeado (ex: "Lista do João")
3. Sistema gera URL: events.raphael-martins.com/{slug}?ref={token}
4. Owner copia URL e envia ao promoter por qualquer canal
5. Promoter distribui livremente
6. Métricas atualizadas em tempo real no dashboard
```

### 3.3 Cadastro do Convidado

```
1. Convidado acessa link com ?ref={token}
2. Sistema valida token (existe? não expirou? não foi revogado?)
3. Se inválido: mensagem "este evento é privado, procure um organizador"
4. Se válido: exibe form
5. Convidado preenche:
   - Nome (obrigatório)
   - Email (obrigatório)
   - Foto (selfie ou upload, obrigatório por default)
   - WhatsApp (opcional)
   - Outros campos configurados pelo organizador
   - Aceita termos (checkbox)
6. Submit + Turnstile + rate limit por IP+token
7. Sistema valida:
   - Email não duplicado no evento
   - Capacidade não estourada
8. Cria registro como pending_email
9. Envia email de confirmação com link tokenizado
10. Convidado clica → status muda para confirmed
11. Sistema gera ticket com QR Code (JWT assinado)
12. Envia email com ingresso (PDF + imagem)
```

### 3.4 Recuperação via WhatsApp (1:1)

```
Convidado envia qualquer mensagem ao bot
↓
Bot identifica número
↓
Caso A: número conhecido → busca tickets ativos → envia QR Code
Caso B: número desconhecido → pede email → faz matching
↓
Matching de telefones (algoritmo de similaridade):
- Score >= 80: atualiza número, envia QR
- Score 50-79: solicita validação adicional
- Score < 50: bloqueia, orienta procurar organizador
```

**Política de envio de QR:** uma vez por ticket. Pedidos subsequentes recebem mensagem orientando consultar histórico ou email.

### 3.5 Vinculação de Grupo do WhatsApp

```
1. Owner adiciona bot ao grupo
2. Bot envia mensagem inicial (instruções + dica de admin)
3. Qualquer membro envia: /vincular EVT-XXXX
4. Bot valida código → vincula whatsapp_chat_id ao event_id
5. Bot confirma no grupo + notifica owner por email
6. A partir desse momento:
   - Imagens enviadas no grupo passam por moderação automática
   - Fotos aprovadas vão pro álbum público da landing
   - Vídeos e áudios são salvos no S3 (não exibidos no álbum no MVP)
7. Comandos disponíveis: /info /album /lista /naoconfirmados (este se admin) /help
```

### 3.6 Check-in no Evento

```
1. Owner gera link de operador no dashboard
2. Operador abre link no celular → PWA carrega autenticado pelo token
3. PWA pede instalação ("Adicionar à tela inicial")
4. Sincronização inicial:
   - Baixa lista completa de tickets confirmados
   - Baixa fotos dos convidados (cache em IndexedDB)
   - Salva em local storage cifrado
5. Operador clica "Iniciar check-in"
6. Câmera abre, scanner ativo
7. QR Code lido → JWT validado offline (chave pública embarcada)
8. Sistema verifica:
   - Assinatura válida
   - Ticket existe na lista local
   - Ticket ainda não foi marcado localmente
9. Tela mostra:
   - Foto grande do convidado
   - Nome + promoter de origem
   - Botão grande "Confirmar entrada" / "Recusar"
10. Confirmação → marca local + adiciona à fila de sync
11. Sync com servidor a cada 30s quando online
12. Servidor é fonte da verdade — clientes sincronizam em ambas as direções
```

**Conflito (single-device no MVP):** se operador fechar/reabrir e tentar marcar ticket já sincronizado como usado, servidor retorna 409 + timestamp existente, PWA exibe "já registrado às HH:MM".

### 3.7 Pós-Evento

```
1. Landing muda automaticamente após data_fim:
   - Form de cadastro desabilitado
   - Mensagem "evento encerrado"
   - Álbum de fotos passa a ser destaque
2. Fotos do grupo continuam sendo capturadas e moderadas
3. Owner pode adicionar fotos manualmente pelo dashboard
4. Dashboard exibe relatório final:
   - Total confirmados / total compareceram / no-show rate
   - Distribuição por promoter
   - Distribuição por gênero (se coletado)
   - Distribuição por faixa etária (se coletado)
5. Sem purge automático no MVP — dados preservados para feed contínuo
6. Convidados podem solicitar remoção individual via canal de suporte
```

---

## 4. Stack Técnica

### 4.1 Backend

- **Laravel 12** (PHP 8.3)
- **MySQL 8** (JSON columns para campos custom e content blocks)
- **Redis** (cache, queues, locks)
- **Laravel Horizon** (gestão de queues)
- **Laravel Reverb** (WebSocket — opcional v2 pra dashboard tempo real)

### 4.2 Frontend

- **Blade + Livewire 3** (dashboard, landing, forms)
- **Alpine.js** (interatividade leve)
- **TailwindCSS** (styling)
- **Vue 3 + Vite + Dexie.js + html5-qrcode** (PWA de check-in, isolado em `/checkin`)

### 4.3 IA e Processamento

- **Claude Sonnet 4.5 (vision)** — extração de dados do flyer + moderação de fotos
- **Lib de paleta determinística** — extração de cores dominantes
  - Opção PHP: `cerdic/css-tidy` + análise manual, ou
  - Opção Node sidecar: `node-vibrant` via job
  - Opção mais simples: chama Claude pra classificar cores extraídas

### 4.4 Storage e CDN

- **MinIO S3** (instância no Raphael Hub, bucket `events`)
- **Cloudflare** (DNS, CDN, SSL Full Strict, Turnstile)

### 4.5 Comunicação

- **Resend** (email transacional, subdomínio `mail.raphael-martins.com` ou `events.raphael-martins.com`)
- **Evolution API** (WhatsApp, instância Raphael Personal Hub)

### 4.6 Autenticação

- **OAuth Google** (Laravel Socialite)
- **Magic Link** (email com token, sem senha)

### 4.7 Anti-Abuso

- **Cloudflare Turnstile** (forms públicos)
- **Laravel rate limiting** (por IP, por token, por endpoint)

### 4.8 Infraestrutura

- **VPS Hostinger** (paulalucas, 77.37.68.36)
- **aaPanel + Nginx + PHP-FPM 8.3**
- **GitHub Actions** (CI/CD via `appleboy/ssh-action`, deploy ao usuário `deploy`)

---

## 5. Estrutura de Storage (MinIO)

```
events/
├── flyers/
│   └── {event_id}/original.{ext}              # privado, signed URL
├── albums/
│   └── {event_id}/{photo_id}.webp             # público via Cloudflare
├── albums-thumbs/
│   └── {event_id}/{photo_id}.webp             # público, 400x400
├── albums-rejected/
│   └── {event_id}/{photo_id}.webp             # privado, lifecycle 30d
├── guests/
│   └── {event_id}/{ticket_id}.webp            # privado, signed URL
├── tickets/
│   └── {event_id}/{ticket_id}.pdf             # privado, signed URL
└── media/
    └── {event_id}/{message_id}.{ext}          # vídeos/áudios, privado
```

**Lifecycle policies:**

| Path | Política |
|------|----------|
| `albums-rejected/` | Deleta após 30 dias |
| `flyers/` | Sem expiração |
| `albums/`, `albums-thumbs/` | Sem expiração (preserva álbum) |
| `guests/`, `tickets/` | Considerar lifecycle 60-90 dias após evento |
| `media/` | Considerar lifecycle 60 dias |

---

## 6. Modelo de Dados (Esboço)

> **Nota:** modelo definitivo (com tipos, índices, FKs e migrations comentadas) será detalhado em documento separado quando a Sprint 1 começar.

### Tabelas Principais

**users** — organizadores
- `id`, `name`, `email` (unique), `email_verified_at`, `google_id` (nullable), `created_at`

**events**
- `id`, `owner_id` (FK users)
- `slug` (unique), `slug_locked` (bool — pra impedir mudança após publicação)
- `status` (enum: draft, published, ended, archived)
- `title`, `subtitle`, `description`
- `starts_at`, `ends_at`, `timezone`
- `location_json` (nome, endereço, cidade, estado, lat/lng, sala/setor)
- `capacity` (nullable — sem limite se null)
- `theme_json` (layout, cores, fontes)
- `content_blocks_json` (array de blocos editáveis: title, datetime, location, rules, custom)
- `guest_form_schema_json` (quais campos coletar do convidado)
- `whatsapp_pairing_code` (unique)
- `album_enabled` (bool, default true)
- `album_max_photos` (int, default 500)
- `album_moderation` (enum: auto, manual, off — default auto)
- `cover_image_path` (S3)
- `flyer_original_path` (S3)
- `created_at`, `updated_at`, `published_at`

**referral_links** — links tokenizados de promoters
- `id`, `event_id` (FK)
- `name` (ex: "Lista do João")
- `token` (unique, 32 chars)
- `expires_at` (default created_at + 90 dias)
- `max_uses` (nullable)
- `used_count` (denormalized counter)
- `revoked_at` (nullable)
- `created_at`

**guests**
- `id`, `event_id` (FK)
- `referral_link_id` (FK, nullable)
- `name`, `email`, `phone` (nullable)
- `photo_path` (S3, nullable)
- `birth_year` (int, nullable)
- `gender` (string, nullable, free-form)
- `custom_data_json` (campos extras)
- `status` (enum: pending_email, confirmed, cancelled, blocked)
- `email_confirmed_at` (nullable)
- `phone_verified_at` (nullable)
- `consent_terms_at`
- `created_at`

**tickets**
- `id`, `guest_id` (FK)
- `qr_token` (unique — JWT armazenado por referência)
- `issued_at`
- `checked_in_at` (nullable)
- `checked_in_by_token` (referência ao link de operador que fez)
- `checked_in_device_id` (nullable)
- `revoked_at` (nullable)

**operator_links** — links tokenizados de check-in
- `id`, `event_id` (FK)
- `token` (unique)
- `name` (ex: "Operador entrada principal")
- `expires_at` (default ends_at + 6h)
- `created_at`

**checkin_log** — auditoria de check-ins
- `id`, `ticket_id`
- `operator_token`
- `device_id`
- `scanned_at` (timestamp local do device)
- `synced_at` (timestamp servidor)
- `result` (enum: success, duplicate, invalid, expired)
- `client_ip`, `user_agent`

**whatsapp_groups**
- `id`, `event_id` (FK)
- `whatsapp_chat_id` (unique do Evolution)
- `role` (enum: public, staff)
- `vinculated_by_phone` (hash)
- `vinculated_at`
- `is_bot_admin` (bool — atualizado periodicamente)
- `active` (bool — pra desvinculação soft)

**album_photos**
- `id`, `event_id` (FK)
- `whatsapp_group_id` (FK, nullable — pode ser upload manual)
- `whatsapp_message_id` (nullable)
- `sender_phone_hash` (sha256 do número, anonimização)
- `file_path` (S3)
- `thumbnail_path` (S3)
- `taken_at` (do EXIF se disponível)
- `received_at`
- `moderation_status` (enum: pending, approved, rejected)
- `moderation_reason` (string, nullable)
- `moderation_response_json` (resposta bruta da IA pra audit)
- `perceptual_hash` (pra dedup)
- `published_at` (nullable)
- `removed_at` (nullable)
- `removed_reason` (nullable)

**phone_change_log** — auditoria de matching
- `id`, `guest_id`
- `old_phone_hash`, `new_phone_hash`
- `matched_via` (enum: email, similarity)
- `similarity_score`
- `changed_at`

**ai_extractions** — auditoria das chamadas de IA
- `id`, `event_id`
- `type` (enum: flyer_extraction, photo_moderation)
- `model` (string)
- `request_payload_json`
- `response_payload_json`
- `cost_usd_estimate` (decimal)
- `applied` (bool)
- `created_at`

**Tabelas adicionais previstas:** `personal_access_tokens` (Sanctum se precisar), `sessions`, `failed_jobs`, `jobs`, `password_resets` (mesmo com magic link, pra fallback).

---

## 7. Mapeamento de Rotas (Esboço)

### Públicas (sem autenticação)

```
GET  /                                # Landing pública do produto (portfólio)
GET  /termos                          # Termos de uso
GET  /privacidade                     # Política de privacidade

GET  /{slug}                          # Landing pública do evento
GET  /{slug}/album                    # Álbum de fotos do evento

POST /{slug}/cadastro                 # Submissão do form de convidado (com ?ref=token)
GET  /confirmar/{token}               # Confirmação de email do convidado
GET  /ingresso/{ticket_id}/{hash}     # Visualização pública do ingresso (compartilhável)
```

### Auth (organizadores)

```
GET  /login                           # Tela de login (Google OAuth + Magic Link)
GET  /auth/google/redirect            # Inicia OAuth Google
GET  /auth/google/callback            # Callback OAuth
POST /auth/magic                      # Solicita magic link
GET  /auth/magic/{token}              # Consome magic link
POST /logout
```

### Dashboard (autenticado)

```
GET  /dashboard                       # Listagem de eventos do owner
GET  /eventos/criar                   # Wizard de criação (upload de flyer)
GET  /eventos/{id}/editar             # Edição de evento (Livewire)
GET  /eventos/{id}                    # Visão geral do evento (métricas)
GET  /eventos/{id}/convidados         # Lista de convidados
GET  /eventos/{id}/promoters          # Gestão de links de promoter
GET  /eventos/{id}/operadores         # Gestão de links de operador
GET  /eventos/{id}/album              # Gestão do álbum (moderação manual)
GET  /eventos/{id}/relatorio          # Relatório pós-evento
```

### PWA Check-in

```
GET  /checkin/{token}                 # Carrega PWA autenticado
GET  /checkin/{token}/manifest.json
GET  /checkin/{token}/sw.js
POST /api/checkin/sync                # Sync de check-ins offline
GET  /api/checkin/event-data          # Pull inicial de tickets + fotos
```

### Webhooks

```
POST /webhooks/evolution              # Webhook de mensagens WhatsApp
POST /webhooks/resend                 # Webhook de eventos de email (bounce, complaint)
```

### API Interna (autenticada via Sanctum)

```
POST /api/events/{id}/extract-flyer   # Dispara job de extração
GET  /api/events/{id}/extraction      # Polling do status da extração
POST /api/events/{id}/photos          # Upload manual de foto pro álbum
DELETE /api/photos/{id}               # Remoção de foto do álbum
```

---

## 8. Componentes Livewire (Esboço)

### Dashboard

- `Events\EventsList` — listagem com filtros (ativos, finalizados)
- `Events\CreateEventWizard` — fluxo multi-step (upload, extração, confirmação)
- `Events\EditEvent` — edição completa com preview ao vivo
- `Events\ContentBlocksEditor` — drag-and-drop dos blocos (Sortable.js via Alpine)
- `Events\ThemeEditor` — paleta + tipografia + layout
- `Events\GuestList` — listagem com busca, filtros, exportação CSV
- `Events\PromoterLinks` — CRUD de links + métricas
- `Events\OperatorLinks` — CRUD de links de check-in
- `Events\AlbumModeration` — fila de fotos pendentes
- `Events\Reports` — gráficos (Chart.js via Alpine)

### Landing

- `Public\EventLanding` — renderização da landing
- `Public\GuestForm` — formulário de cadastro
- `Public\Album` — grid de fotos com lightbox

### Auth

- `Auth\Login` — Google + Magic Link
- `Auth\MagicLinkForm`

---

## 9. Prompts de IA (Esboço)

### 9.1 Extração de Flyer

**Modelo:** Claude Sonnet 4.5 (vision)

**System prompt (resumo):**
```
Você é um extrator de informações de flyers de eventos. Recebe imagem ou PDF
e retorna JSON estruturado com:
- title, subtitle
- starts_at (ISO 8601, timezone America/Bahia se não especificado)
- ends_at (ISO 8601, opcional)
- location: {name, address, city, state}
- description
- rules (array de strings)
- dress_code (string, opcional)
- additional_info (array de objetos {label, value})
- visual_style: classification ('festive', 'elegant', 'minimal', 'corporate')
- dominant_colors: array de 5 cores em hex
- font_style: classification ('sans_modern', 'serif_elegant', 'display_bold', 'script')

Retorna APENAS JSON válido. Sem texto adicional.
Se algum campo não estiver claramente identificável, retorna null.
```

**Schema de saída (JSON Schema):** documentado em arquivo separado quando implementar.

### 9.2 Moderação de Fotos

**Modelo:** Claude Sonnet 4.5 (vision)

**System prompt (resumo):**
```
Você é um moderador de conteúdo para álbum público de evento social privado.
Classifica imagens em:
- approved: foto social adequada (pessoas, ambiente, comida, decoração)
- rejected_explicit: nudez, conteúdo sexual
- rejected_violence: violência gráfica
- rejected_minors: crianças identificáveis (cautela elevada)
- rejected_documents: documentos pessoais visíveis (RG, CNH, cartões)
- rejected_other: outros motivos

Para cada classificação, retorna confidence (0-100).
Se confidence < 80, marca como pending_review.
Retorna JSON: {classification, confidence, reason}
```

---

## 10. Política de Privacidade e Termos (Esqueleto)

### 10.1 Posicionamento Legal

A plataforma se posiciona como **operadora** de dados (LGPD Art. 5º, VII). O **organizador** do evento atua como **controlador** dos dados de seus convidados.

### 10.2 Tópicos Obrigatórios

- Identificação da plataforma (CEMAR Consultoria)
- Quais dados são coletados e por qual base legal
- Compartilhamento (organizador, processadores: Resend, Evolution, MinIO)
- Direitos do titular (acesso, correção, eliminação, portabilidade)
- Canal de contato para exercer direitos
- Retenção de dados (sem purge automático, mas remoção sob solicitação em até 7 dias úteis)
- Cookies e tecnologias similares
- Transferência internacional (se Resend e Claude API forem fora do Brasil — sim)

### 10.3 Disclaimers Críticos

- Plataforma não revisa manualmente todo conteúdo
- Moderação automática é preventiva, não infalível
- Organizador é responsável pelo conteúdo do seu evento e do álbum
- Plataforma pode remover conteúdo a qualquer momento

### 10.4 Recomendação

Documento template inicial é aceitável pro MVP, mas vale revisão por advogado especializado em LGPD antes de divulgar amplamente. Custo estimado: R$300-500.

---

## 11. Roadmap

### Fase 1 — MVP Core (estimativa: 4-5 semanas)

- [ ] Setup do projeto (Laravel + Livewire + Tailwind + auth)
- [ ] OAuth Google + Magic Link
- [ ] CRUD de eventos (sem IA, manual)
- [ ] 2 templates de landing (festivo + elegante)
- [ ] Editor de content blocks (drag-and-drop)
- [ ] Editor de tema (cores + fontes)
- [ ] Sistema de promoters via link tokenizado
- [ ] Form de convidado com upload de foto
- [ ] Validação de email + geração de ticket
- [ ] Envio de email via Resend (template de ingresso)
- [ ] Geração de PDF do ingresso
- [ ] PWA de check-in single-device offline-first
- [ ] Dashboard básico (lista, contagem, filtros)
- [ ] Política de privacidade + termos

### Fase 2 — Camada IA (estimativa: 2 semanas)

- [ ] Upload de flyer com extração via Claude Vision
- [ ] Tela de confirmação/edição da extração
- [ ] Extração de paleta determinística
- [ ] Aplicação automática de tema baseado na extração
- [ ] Dashboard de auditoria das extrações

### Fase 3 — WhatsApp Integration (estimativa: 3 semanas)

- [ ] Webhook Evolution → Laravel
- [ ] Bot 1:1 com lógica de matching de telefone
- [ ] Sistema de vinculação de grupo (`/vincular`)
- [ ] Captura de fotos do grupo público
- [ ] Moderação automática via Claude Vision
- [ ] Álbum público na landing
- [ ] Dashboard de moderação manual (fila pendente)
- [ ] Comandos básicos: `/info`, `/album`, `/lista`, `/help`

### Fase 4 — Refinamentos (sem prazo)

- [ ] Comandos avançados do bot (`/naoconfirmados` se admin, etc.)
- [ ] Métricas públicas na landing raiz (após 5+ eventos)
- [ ] Relatório pós-evento avançado (gráficos, exportação)
- [ ] Detecção de duplicatas via perceptual hash
- [ ] Webhook de email (Resend) para tracking de bounce/complaint
- [ ] Multi-evento por organizador com templates salvos

### v2 (Backlog para evolução futura)

- [ ] Limite de eventos por organizador (anti-abuso)
- [ ] Sistema de pagamento de ingresso (Mercado Pago)
- [ ] Acompanhantes (+1)
- [ ] Categorias de ingresso
- [ ] Múltiplos co-organizadores com login próprio
- [ ] Cota individual por promoter
- [ ] Sync entre múltiplos devices no check-in
- [ ] Notificações push pro organizador
- [ ] App nativo (se houver demanda real)
- [ ] Integração calendário (Google/Apple)
- [ ] Vídeos no álbum (com moderação humana via dashboard)
- [ ] Migrações pra número WhatsApp dedicado quando volume justificar
- [ ] Export de lista pra portaria de condomínio (CSV padrão)

---

## 12. Decisões Arquiteturais Registradas (ADRs)

### ADR-001: Blade + Livewire ao invés de SPA

**Contexto:** projeto pessoal/portfólio com necessidade de simplicidade de deploy.

**Decisão:** usar Blade + Livewire 3 + Alpine.js para todo o frontend exceto PWA de check-in.

**Consequências:**
- ✅ Deploy unificado, um único projeto Laravel
- ✅ SSR nativo, melhor SEO e preview no WhatsApp
- ✅ Menos peças móveis pra manter
- ❌ Menor flexibilidade de UI complexa (aceitável pro escopo)

### ADR-002: Slug com data ao invés de subdomínio dinâmico

**Contexto:** evitar complexidade de wildcard SSL e DNS dinâmico.

**Decisão:** `events.raphael-martins.com/{nome-do-evento-dd-mm-yyyy}`. Editável na criação. Sufixo numérico em caso de colisão.

**Consequências:**
- ✅ Simplicidade de DNS
- ✅ URLs legíveis e memoráveis
- ❌ Sem isolamento total entre eventos (cookies compartilhados — não problemático no escopo)

### ADR-003: Promoters sem conta

**Contexto:** simplificar adoção, evitar gestão de múltiplos usuários.

**Decisão:** promoters acessam funcionalidade via link tokenizado, sem login. Mesmo modelo para operadores de check-in.

**Consequências:**
- ✅ Zero fricção de onboarding
- ✅ Owner mantém controle total
- ❌ Tokens vazados expõem funcionalidade (mitigado por revogação fácil)

### ADR-004: Single-device check-in no MVP

**Contexto:** sync multi-device adiciona complexidade significativa (resolução de conflitos, WebSockets).

**Decisão:** assumir um operador por evento no MVP. Multi-device fica pra v2.

**Consequências:**
- ✅ Implementação muito mais simples
- ✅ Suficiente pra escopo de eventos pequenos/médios
- ❌ Eventos grandes precisam aguardar v2

### ADR-005: WhatsApp via Raphael Personal Hub

**Contexto:** infraestrutura Evolution já operacional no DopaCheck.

**Decisão:** reusar mesma instância Evolution + número pessoal pro MVP. Migrar pra número dedicado se ganhar tração.

**Consequências:**
- ✅ Custo zero adicional
- ✅ Implementação rápida
- ❌ Risco de ban afeta também outros projetos pessoais (mitigado: bot só responde, não dispara)

### ADR-006: Captura de fotos via WhatsApp ao invés de Instagram scraping

**Contexto:** Instagram tem ToS restritiva e API oficial limitada.

**Decisão:** usar grupos do WhatsApp como fonte primária de fotos do álbum.

**Consequências:**
- ✅ Zero risco legal/ToS
- ✅ Comportamento natural dos usuários (todo mundo cria grupo do evento)
- ✅ Diferencial real vs concorrentes
- ❌ Limitado a quem está no grupo (aceitável)

### ADR-007: Moderação automática obrigatória

**Contexto:** álbum público sem moderação é risco legal e de imagem.

**Decisão:** todas as fotos passam por Claude Vision antes de ir ao álbum público. Reprovações vão pra fila de revisão manual do organizador.

**Consequências:**
- ✅ Proteção contra conteúdo inadequado
- ✅ Custo desprezível (~$0.005/foto)
- ❌ Latência de alguns segundos entre upload e exibição (aceitável)

### ADR-008: Resend ao invés de Workspace SMTP

**Contexto:** Workspace free permite SMTP mas tem limitações para envio transacional.

**Decisão:** Resend com subdomínio dedicado, reply-to no Workspace.

**Consequências:**
- ✅ Entregabilidade superior
- ✅ Webhook de tracking nativo
- ✅ SPF/DKIM/DMARC isolados não afetam domínio principal
- ❌ Dependência externa adicional (aceitável)

### ADR-009: Sem purge automático no MVP

**Contexto:** desejo de manter álbuns acessíveis a longo prazo.

**Decisão:** dados não são purgados automaticamente. Remoção apenas sob solicitação individual.

**Consequências:**
- ✅ Memórias preservadas
- ✅ Permite feature de feed contínuo no futuro
- ❌ Storage cresce indefinidamente (mitigado: lifecycle em pastas específicas)
- ❌ Maior superfície de exposição LGPD (mitigado: política clara + canal de remoção)

---

## 13. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| Ban do número WhatsApp pessoal | Baixa | Alto | Manter padrão de uso responsivo; plano B com email |
| QR Code vazado em redes sociais | Alta | Médio | Foto do convidado no ingresso |
| Foto inadequada no álbum público | Média | Alto | Moderação automática + termos |
| IA extrai dados errados do flyer | Alta | Baixo | Tela de confirmação obrigatória |
| Slug colidindo | Baixa | Baixo | Sufixo numérico automático |
| Capacidade estourada | Baixa | Médio | Validação no submit + lista de espera (v2) |
| Bot WhatsApp não consegue enviar QR | Média | Médio | Email é canal primário, WhatsApp é conveniência |
| Pessoa muda de número | Alta | Baixo | Algoritmo de matching com email + score |
| Storage MinIO cheio | Baixa | Médio | Lifecycle policies + monitoramento |

---

## 14. Glossário

- **Owner / Organizador:** usuário que cria o evento e tem controle total
- **Promoter:** pessoa autorizada a distribuir links de inscrição via token
- **Operador:** pessoa autorizada a fazer check-in via PWA, identificada por token
- **Token:** string aleatória usada para autorizar acesso a funcionalidade sem login
- **Magic Link:** link tokenizado enviado por email para autenticação sem senha
- **Slug:** identificador da URL do evento (ex: `aniversario-maria-15-03-2026`)
- **Ticket:** ingresso digital com QR Code único
- **PWA:** Progressive Web App, aplicação web instalável e offline-capable
- **Pairing Code:** código `EVT-XXXX` usado pra vincular grupo do WhatsApp ao evento
- **Album:** galeria pública de fotos do evento, alimentada via WhatsApp ou upload manual

---

## 15. Próximos Passos

Briefing consolidado. As próximas etapas serão executadas conforme disponibilidade do autor, com auxílio de ferramentas de IA (Codex, Claude Code, IDE):

1. Setup inicial do projeto Laravel
2. Modelo de dados completo (migrations comentadas)
3. Esqueleto de rotas e Livewire components
4. Implementação iterativa por sprint conforme roadmap da Fase 1

**Repositório:** a definir
**Domínio de produção:** `events.raphael-martins.com`
**Domínio de desenvolvimento:** `events.test` (local) / `dev-events.raphael-martins.com` (Cloudflare Tunnel)

---

*Documento vivo. Atualizar conforme decisões evoluírem durante o desenvolvimento.*
