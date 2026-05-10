# Changelog

Todas as mudanças relevantes do **Raphael Personal Hub** são registradas aqui. Formato inspirado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

Entradas datadas até **2026-05-08** foram consolidadas a partir do antigo *changelog do documento* em [`docs/v2.md`](docs/v2.md) (removido de lá para manter `v2` só como roadmap e decisões de produto).

---

## [Unreleased]

### Pendente

- **Álbuns de mídia — Fase F:** upload ZIP, tags, watermark on-the-fly, thumbnail/transcode de vídeo (FFmpeg), download ZIP do álbum. Ver [docs/album/SPEC_media_albums.md](docs/album/SPEC_media_albums.md) §F.

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
