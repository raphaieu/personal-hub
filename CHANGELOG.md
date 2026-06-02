# Changelog

Todas as mudanças relevantes do **Raphael Personal Hub** são registradas aqui. Formato inspirado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

Entradas datadas até **2026-05-08** foram consolidadas a partir do antigo *changelog do documento* em `docs/v2.md` (substituído por [`docs/roadmap/BACKLOG.md`](docs/roadmap/BACKLOG.md) na refatoração de **2026-05-11**, que mantém apenas backlog).

---

## [Unreleased]

### Pendente

- **Álbuns de mídia — Fase F:** upload ZIP, tags, watermark on-the-fly, thumbnail/transcode de vídeo (FFmpeg), download ZIP do álbum. Ver [docs/album/SPEC.md](docs/album/SPEC.md) (seção *Fase F — backlog*) e [docs/roadmap/BACKLOG.md](docs/roadmap/BACKLOG.md).

---

## 2026-06-02

*Confirmação de convidados mais rápida e opção de pular e-mail de interesse.*

### Added

- **Eventos — pular confirmação por e-mail:** coluna `events.skip_email_confirmation` (default `false`); checkbox no hub *“Pular confirmação por e-mail (enviar ingresso na hora)”* (desabilitado quando o evento exige pagamento). API `GET /config` expõe `registration.requiresEmailConfirmation`; `POST /register` em evento grátis com skip cria guest `confirmed`, enfileira ingresso e responde `flow: ticket_sent`.
- **Eventos — job de ingresso:** `SendGuestTicketEmailJob` (fila `notifications`) gera o PDF (DomPDF) e envia `GuestTicketMail`; usado após confirmação por link, pagamento aprovado e registro com skip.

### Changed

- **Eventos — confirmação por link assíncrona:** `GET /events/guest/confirm/{token}` confirma o convidado no banco e responde a página HTML imediatamente; preparo do PDF e envio do e-mail passam para `SendGuestTicketEmailJob` (antes o `GuestTicketMail` ia para a fila no mesmo request, o que podia travar a página com fila `sync` ou PDF pesado). Texto de sucesso: “Estamos enviando o ingresso…”.
- **Eventos — `GuestTicketMail`:** deixa de implementar `ShouldQueue` (uma única fila via job dedicado).
- **Documentação:** [docs/events/SPEC.md](docs/events/SPEC.md) atualizado com `requiresEmailConfirmation`, `ticket_sent` e fluxo do job.

---

## 2026-05-28

*Commits diretos na `main` (domínio público do hub e ajustes no checkout de eventos).*

### Changed

- **Domínio público do hub:** referências versionadas de produção migradas de `api.raphael-martins.com` para `hub.raphael-martins.com` em `.env.example`, documentação, contrato da API de eventos, webhook Evolution, gateway Iara e mensagens/scripts operacionais.
- **Eventos — Mercado Pago:** item da Preference do Checkout Pro agora envia `items.description` montado no backend como `Ingresso para {nome do evento}`, sem exigir novo campo no frontend.

---

## 2026-05-24

*Commits diretos na `main` (pagamento de ingressos).*

### Added

- **Eventos — pagamento Mercado Pago (Checkout Pro):** contas MP por organizador (`mercado_pago_accounts`, access token criptografado); evento com `requires_payment`, `ticket_amount_cents` e conta vinculada; registro público retorna `flow: checkout` + `checkoutUrl` (PIX/cartão, sem boleto); webhook `POST /webhooks/mercadopago`; páginas `GET /events/payment/return/{token}` (polling + countdown 15 min) e `GET /events/payment/status/{token}`; job `ExpireUnpaidEventGuestsJob` a cada 15 min; status `pending_payment`; pacote `mercadopago/dx-php`. Admin: painel de contas MP e campos de pagamento no hub de eventos.

### Changed

- **Eventos — pagamento:** expiração da reserva de vaga só no Hub (`payment_expires_at` + job); a Preference do MP não envia `expiration_date` (evita link de checkout expirado no sandbox antes do job).

---

## 2026-05-21

*Commits diretos na `main` (QR PNG, templates, foto opcional).*

### Changed

- **Foto do convidado opcional (admin):** checkbox "Exige foto do convidado" no hub ao lado de Turnstile/referral/inscrições. Coluna `requires_photo` (`boolean`, default `true`) em `events`. Quando desligado, `EventPublicConfigService::mergeFormFields()` seta `enabled: false` no campo photo do schema; API `/config` expõe `requiresPhoto`.
- **QR code agora é PNG servido por rota própria:** pacote `simplesoftwareio/simple-qrcode` removido, substituído por `chillerlan/php-qrcode` (gera PNG via GD, sem Imagick). `GET /events/qr/{guest}` (`GuestQrCodeController`) retorna a imagem PNG com cache de 24h. Emails custom usam `{{ $qrUrl }}` em vez de data URI; PDFs continuam com `{{ $qrDataUri }}` (DomPDF aceita data URI).

### Added

- **Páginas de confirmação de e-mail com tema escuro:** layout `layouts/events-confirm.blade.php` (fundo `#0a0a0f`, accent `#6c5ce7`, Inter/Outfit, orbs ambientais, glass card). 4 views refeitas: `email-confirmed-success`, `email-already-confirmed`, `email-confirm-invalid`, `email-confirm-capacity`. Renderizadas por `GuestEmailConfirmationController`.
- **Templates customizados de e-mail e PDF:** `mail/events/custom/liz1ano.blade.php` (tabela, tema Liz: rosa `#e4759a`, florais, borboletas, "Vem brincar no meu jardim!") e `pdf/events/custom/liz1ano.blade.php` (DomPDF, DejaVu Serif, mesmo tema). Resolução automática via `invite_template_key` em `EventGuestInvitePresentation` e `EventTicketPdfService`. Variáveis disponíveis: `$guest`, `$checkInUrl`, `$qrDataUri`, `$qrUrl`.
- **Rota pública de QR code:** `GET /events/qr/{guest}` (throttle 120/min), retorna `image/png` com cache de 24h.

### Fixed

- **QR code não renderizava no Gmail:** data URI PNG inline não é suportado pelo Gmail. Solução: QR code servido via rota HTTP pública como PNG real. `chillerlan/php-qrcode` (GD) substitui `simplesoftwareio/simple-qrcode` (Imagick). Templates `liz1ano` e `villa40` atualizados para `{{ $qrUrl }}`.

---

## 2026-05-11

*Commit na `main` (reorganização de documentação).*

### Changed

- **Documentação reorganizada:** raiz enxuta (`README.md`, `PRD.md`, `SPEC.md`, `LLM.md`) e SPECs por módulo em `docs/<modulo>/SPEC.md` (`core`, `whatsapp`, `utilities`, `threads`, `events`, `album`, `operations`). Backlog único em `docs/roadmap/BACKLOG.md` (renomeado de `docs/v2.md`). Mapa em `docs/README.md`. SPEC dos álbuns dividida em entrada canônica (`docs/album/SPEC.md`) + detalhamento histórico (`docs/album/IMPLEMENTATION.md`). SPEC de eventos consolidada em `docs/events/SPEC.md` (antigo `SPEC_events_v1.md` removido; `BRIEFING.md` e `API_Contract.md` mantidos como referência estendida).

---

## 2026-05-10

*PR [#4](https://github.com/raphaieu/personal-hub/pull/4) `feature/album`, PR [#5](https://github.com/raphaieu/personal-hub/pull/5) `feature/events` — merges na `main`.*

### Added

- **Eventos (v1 — PR #5):** schema `events`, `referral_links`, `guests`; hub `GET /hub/events`, `GET /hub/events/{event}`; API `GET|POST /api/v1/events/{slug}/config|register`, CORS, rate limits, Turnstile (`EVENTS_*`). Fluxo `pending_email` → confirmação por e-mail → `GuestTicketMail` com PDF (DomPDF) e QR; `GuestInviteMail` no hub. Portaria `GET /events-checkin` (Livewire, **html5-qrcode**, manifest PWA). Pacotes: `barryvdh/laravel-dompdf`, `simplesoftwareio/simple-qrcode`, npm `html5-qrcode`. Documentação: [docs/events/SPEC.md](docs/events/SPEC.md). Evoluções de QR PNG e pagamento MP: ver **2026-05-21** e **2026-05-24**.
- **Dashboard hub (PR #4):** página `GET /dashboard` com **cards** (ícone SVG, título, descrição, link). Dados em **`config/hub_dashboard.php`**; ícones em **`resources/views/components/hub/dashboard-icon.blade.php`**. Teste: `tests/Feature/DashboardHubCardsTest.php`.
- **Docker (PHP-FPM):** `docker/php/zz-uploads.ini` copiado no `Dockerfile` — `max_file_uploads`, `upload_max_filesize`, `post_max_size`, `memory_limit`, tempos de execução alinhados a uploads em lote de álbuns. Requer **rebuild** da imagem `raphael-hub:latest` após alterar o arquivo.
- **Docker (Nginx do Compose):** `client_max_body_size` elevado em `docker/nginx/default.conf` para acompanhar `post_max_size` do PHP.
- **Docker (runtime fase F):** pacotes **`ffmpeg`** e **`zip`** (CLI) na imagem PHP; extensão PHP **`zip`** já existia — preparação para ZIP/FFmpeg em álbuns sem mudar o código da Fase F ainda.

### Changed

- **`.env.example`:** comentário no bloco `ALBUMS_*` apontando `docker/php/zz-uploads.ini`, `docker/nginx/default.conf`, rebuild da imagem e necessidade de alinhar **`client_max_body_size`** no Nginx do **host** (aaPanel), se aplicável.

### Changed

- Navegação: rótulo **Albums** → **Álbuns** no menu (desktop e responsivo) — PR #4.

### Fixed

- **Playwright Embasa (`playwright/src/embasa-scraper.js`):** quando a 2ª via exibe *“A Matrícula informada não possui débitos”* (sem contas em aberto), o scraper deixa de falhar e passa a extrair faturas do carrossel **MINHAS CONTAS** na **`/home`** (referência a partir do título mês/ano, vencimento, consumo, valor total, status). **`pdf_path`** permanece ausente se não houver fatura pendente para baixar — o Laravel continua atualizando `invoices` sem PDF.
- **Playwright Embasa — modais:** `section.blk-modal` que interceptavam o clique na matrícula — `dismissEmbasaBlockingModals` e clique com escopo/`force` quando necessário (PR #4).

### Operação

- **Produção:** após `git pull`, se mudou `Dockerfile`, `docker/php/*.ini` ou `docker/nginx/default.conf`: `docker compose build` dos serviços que usam a imagem app + **`docker compose up -d`** (inclui `nginx`). Conferir no vhost do aaPanel o mesmo limite de corpo da requisição que no container Nginx.
- **Embasa:** atualizar container **`raphael-playwright`** quando mudar `playwright/src/embasa-scraper.js` (rebuild `Dockerfile.playwright` / imagem do serviço `playwright`).

---

## 2026-05-09

### Added

- **Álbuns (hub):** exclusão de mídias em lote (checkboxes na tabela, “Apagar selecionadas”) e **apagar álbum inteiro** (remove objetos no S3, apaga mídias, soft delete do álbum; bloqueado se existir subálbum). Redirecionamento para `/hub/albums` com flash `albums_hub_notice`.
- **`App\Support\AlbumUploadLimits`:** teto real de arquivos por requisição HTTP = `min(ALBUMS_MAX_FILES_PER_BATCH, PHP max_file_uploads)`; usado na validação do envio no hub (`AlbumDetailPage::uploadMedia`) e no POST de contribuição (`AlbumContributionController::upload`). Texto de ajuda no hub e na página pública de upload de contribuição quando o limite do PHP for o gargalo.
- **`albums:prune-local-staging`:** comando Artisan para podar arquivos antigos em `album-ingest` e `livewire-tmp` sob `storage/app/private` (classe `App\Console\Commands\AlbumsPruneLocalStagingCommand`).
- **Testes:** `AlbumHubMediaUploadTest` (bulk delete, delete album, bloqueio com subálbum); `AlbumContributionsTest` (revogação limpa `contribution_invite_token` e invalida URL do convite); `IngestAlbumUploadBatchJobTest` (arquivo rejeitado removido, diretório do lote apagado).

### Changed

- **Hub detalhe do álbum (`AlbumDetailPage`):** reordenação por **arrastar e soltar** (SortableJS via CDN, coluna com handle) e por **número de posição** (coluna “Nº”); remoção do fluxo antigo por setas. Métodos Livewire `reorderMedia`, `setMediaPosition`, `persistSortOrder`.
- **Listagem de mídias sem teto artificial:** viewer público e admin carregam todas as mídias do álbum (ordenadas por `sort_position`, `created_at`). O limite de ~20 itens que aparecia no passado vinha do **payload Livewire** (`max_components`); o padrão passou a ser **sem limite** (`null` em `config/livewire.php`), com override opcional `LIVEWIRE_PAYLOAD_MAX_COMPONENTS` no `.env` (documentado no `.env.example`).
- **Upload admin em lote:** arquivos seguem para disco `local` e o job `IngestAlbumUploadBatchJob` roda após a resposta (fila padrão), alinhado a envios grandes sem estourar o request.
- **Contribuição externa — “Revogar uploads”:** além de zerar `upload_token` / `upload_expires_at` dos contribuidores, agora **limpa `albums.contribution_invite_token`**, invalidando o link público de convite; a UI do hub atualiza o estado (botão “Gerar link de contribuição” volta a aparecer).
- **UI hub detalhe do álbum:** bloco destacado **Galeria pública** (URL absoluta, rota relativa, botão “Abrir galeria”); **duas colunas** em `lg+` — “Envio de arquivos (hub)” e “Contribuição externa”.

### Fixed

- **Convite de contribuição ainda ativo após “Revogar uploads”** — o token de convite do álbum não era apagado; corrigido em `AlbumContributionService::revokeAllUploadTokens`.
- **Resíduos em `storage/app/private/album-ingest`:** validação de tipo/tamanho em `ingestFromStoredLocalPath` podia deixar arquivo local sem apagar (fora do `finally`). Agora o arquivo local é sempre removido no `finally` após tentativa de ingestão.
- **Pastas vazias em `album-ingest/`:** ao concluir `IngestAlbumUploadBatchJob`, o diretório do lote (`album-ingest/{album_id}/{uuid}/`) é removido.

### Operação

- Comando **`php artisan albums:prune-local-staging`** (`--hours=48` por padrão, `--dry-run` para simular): remove arquivos antigos em **`album-ingest`** e **`livewire-tmp`** no disco local. Agendamento **semanal** (segunda 03:30) em `bootstrap/app.php` quando o scheduler estiver ativo.

---

## 2026-05-08

### Added

- **Media Albums (fases A–E):** hub `/hub/albums`, viewer `/albums/{slug}`, persistência em MinIO/S3, `ProcessAlbumPhotoJob` (fila `media`, GD/WebP), tipos de acesso com lockout, contribuição externa com verificação por e-mail e digest ao administrador (e-mail + Evolution). Domínio: `albums`, `album_media`, `access_attempts`, `album_lockouts`, `contributors`. Testes em `tests/Feature/Albums/`.
- Documentação raiz alinhada: `README.md`, `PRD.md`, `SPEC.md`, `LLM.md`; SPEC canônica da feature: [`docs/album/SPEC.md`](docs/album/SPEC.md) (detalhamento histórico das fases A–E em [`docs/album/IMPLEMENTATION.md`](docs/album/IMPLEMENTATION.md)).

---

## 2026-04-27

### Changed

- Refatoração incremental da análise: `analysis_profiles` e vínculo opcional em `threads_sources`, `monitored_sources` e `threads_categories`; `ThreadsClassificationService` em modo profile-driven; jobs WhatsApp com `ProcessMessageLogAnalysisService` e persistência auditável em `message_logs`.
- Escopo mídia/documentos: pipeline **text-first** mantido; itens não textuais com `pending_media_processing` / `pending_text_extraction` e `is_processed=false`, preparando transcrição/OCR futuros sem multimodal no `NeuronAIService`.

### Added

- Hardening idempotente de profile linkage (`analysis:repair-profile-linkage` + migration de repair).
- Hub Threads com gestão de profile por source; hub `monitored-sources` para operação de fontes WhatsApp (status, reprocessamento manual mínimo).
- Hub `analysis-profiles` (CRUD mínimo, proteção do profile padrão); `monitored-sources` com criação/edição de fontes e validação de canal; Threads Hub em partials sem mudança funcional.

---

## 2026-04-24

### Added — Utilidades (Embasa / Coelba)

- Playwright: `POST /embasa/scrape`, `POST /coelba/scrape`; health com sessão por provider; `storageState` dedicado (`EMBASA_SESSION_PATH`, `COELBA_SESSION_PATH`) com relogin automático.

### Changed

- Coelba: fluxo determinístico sem reaproveitar sessão (login completo e passos até `consultar-debitos`).
- Coelba (home-first): extração do card **Última Fatura** na home; download da 2ª via via **Mais opções** + modal; captura opcional de PIX.
- Contrato `UtilityScraperClientInterface`, `UtilityPlaywrightService`, `FakeUtilityScraperClient`, testes em `tests/Feature/Utilities/UtilityScraperClientTest.php`.
- `InvoiceService`, job `ScrapeConta`, `UtilityScrapeWindow`, schedule em `bootstrap/app.php`; testes de ingestão e janela.
- `EvolutionService::sendText`, jobs `NotificarVencimento` e `VerificarStatusFaturas`, `ignoreScrapeWindow` em `ScrapeConta`, configs e testes.
- `UtilityAccountScrapeGate` e flag `force` em `ScrapeConta`; comando `php artisan utilities:scrape {kind} [--force] [--ignore-window]`.
- Hub `GET /hub/utilities` (`Utilities\HubPage`), download PDF (`UtilityInvoicePdfController`), ação **Scrape agora**; testes `UtilitiesHubPageTest`.

---

## 2026-04-23

### Added — Threads classificados

- Playwright autenticado: `/threads/auth/login`, `/threads/scrape-url`, `/threads/scrape-keyword`; modo keyword posts-only; dedupe opcional (`known_post_ids`, `only_new`, `known_streak_stop`). Documentado no `SPEC.md`.
- Schema `threads_*`, models, `ThreadsCategorySeeder`.
- Contrato `ThreadsScraperClientInterface`, `ThreadsPlaywrightService`, `FakeThreadsScraperClient`, testes `ThreadsScraperClientTest`.
- Jobs `ScrapeThreadsUrlJob`, `ScrapeThreadsKeywordJob` (fila `scraping`), `ThreadsScrapeIngestionService`, testes `ThreadsScrapeIngestionJobsTest`.
- Classificação IA: `ThreadsClassificationService`, `ClassifyCommentsJob` (fila `ai`), `THREADS_RELEVANCE_THRESHOLD`, supervisor `ai` no Horizon; testes `ThreadsClassificationServiceTest`.
- Livewire v4 nos layouts; smoke test `LivewireInstallationTest`.
- Dashboard `/hub/threads` (`Threads\HubPage`), abas Sources/Review/Published; `ThreadsHubPageTest`.
- Gestão de sources (criação keyword/url, toggle, scrape agora).
- `ClassifyCommentsJob` refatorado (1 comentário por execução); `DispatchPendingThreadsClassificationJob` com `THREADS_AI_DISPATCH_SPACING_SECONDS`.
- Aba Review: filtros, curadoria manual e em lote; aba Published; UX (contadores pendentes, flash batch, selecionar todos).
- Página pública `GET /oportunidades`; votação `POST /oportunidades/votos/{comment}`, `RecalculateCommentScoreJob`, `THREADS_VOTE_FINGERPRINT_SALT`.

### Added — IA base do Hub

- `AiRouterService`, `OllamaService`, `NeuronAIService`, pacote `neuron-core/neuron-ai`, gateway `POST /iara`, `config/ai.php`; README/SPEC/LLM atualizados. Foco seguinte: uso nos jobs e classificação persistente.

---

## 2026-04-22

### Changed

- Documentação de infra: MinIO e Evolution na VPS; Ollama no host com acesso restrito à bridge Docker (`README`, `SPEC`, `PRD`).

---

## 2026-04-17

### Added

- Primeira versão deste doc de evolução V2 alinhada a migrations estendidas.
- Produto: grupo WhatsApp “só você” como workaround para mídia quando a Evolution não webhooka o chat 1:1; mesmo JID tratado como rota pessoal (`SPEC` / `LLM`).
