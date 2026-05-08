# Changelog

Todas as mudanças relevantes do **Raphael Personal Hub** são registradas aqui. Formato inspirado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

Entradas datadas até **2026-05-08** foram consolidadas a partir do antigo *changelog do documento* em [`docs/v2.md`](docs/v2.md) (removido de lá para manter `v2` só como roadmap e decisões de produto).

---

## [Unreleased]

### Pendente

- **Álbuns de mídia — Fase F:** upload ZIP, tags, watermark on-the-fly, thumbnail/transcode de vídeo (FFmpeg), download ZIP do álbum. Ver [docs/album/SPEC_media_albums.md](docs/album/SPEC_media_albums.md) §F.

---

## 2026-05-08

### Added

- **Media Albums (fases A–E):** hub `/hub/albums`, viewer `/albums/{slug}`, persistência em MinIO/S3, `ProcessAlbumPhotoJob` (fila `media`, GD/WebP), tipos de acesso com lockout, contribuição externa com verificação por e-mail e digest ao administrador (e-mail + Evolution). Domínio: `albums`, `album_media`, `access_attempts`, `album_lockouts`, `contributors`. Testes em `tests/Feature/Albums/`.
- Documentação raiz alinhada: `README.md`, `PRD.md`, `SPEC.md`, `LLM.md`; SPEC canônica da feature: [`docs/album/SPEC_media_albums.md`](docs/album/SPEC_media_albums.md).

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
