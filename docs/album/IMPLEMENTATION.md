# Media Albums — Implementação histórica (fases A–F)

**Projeto:** raphael-hub  
**Stack base:** Laravel 13 + PHP 8.4 · Livewire 4 · PostgreSQL 17 · Redis 7 · Horizon · MinIO S3 (bucket padrão do Hub: `pessoal`)  
**Data da última revisão:** 2026-05-10  
**Status:** fases A–E implementadas; melhorias de hub/viewer/limites de upload/revogação de convite documentadas abaixo (§3.1); Fase F em backlog; Docker produção (PHP/Nginx uploads, `ffmpeg`/ZIP na imagem) alinhado ao [CHANGELOG](../../CHANGELOG.md) **2026-05-10**.

> **Entrada canônica:** [SPEC.md](SPEC.md) (mesma pasta) é o contrato compacto atual da feature. Este documento mantém o **detalhamento histórico das fases A–E** (campos completos por fase, decisões, refinamentos pós-MVP §3.1 e §5.4) como referência viva de implementação.

**Índice na documentação do projeto:** visão geral do produto e stack em [README.md](../../README.md), [PRD.md](../../PRD.md), [SPEC.md](../../SPEC.md) e [LLM.md](../../LLM.md). Alterações relevantes em [CHANGELOG.md](../../CHANGELOG.md).

---

## 1. Objetivo desta versão

Este documento substitui a visão "big bang" por uma implementação incremental no padrão já consolidado do projeto:

- entregas pequenas e testáveis por bloco;
- UI administrativa em rotas `hub` (não `/admin`);
- processamento assíncrono com jobs/fila quando houver custo de CPU/IO;
- hardening progressivo (segurança, lockout, token, contribuição) sem travar o MVP.

---

## 2. Princípios de implementação (alinhados ao projeto)

1. **Banco oficial:** PostgreSQL 17.
2. **Padrão de painel:** hubs Livewire autenticados (`/hub/*`).
3. **Storage:** MinIO/S3 no padrão do Hub (`Storage::disk('s3')`, bucket `pessoal` por padrão).
4. **Entrega por fases:** cada fase fecha com schema, fluxo funcional e testes mínimos.
5. **Sem acoplamento prematuro:** watermark, ZIP e contribuição entram depois do core estável.

---

## 3. Priorização do roadmap (2026)

Objetivo imediato: **MVP funcional de upload + armazenamento S3/MinIO + viewer público + processamento mínimo de fotos**, com fluxo ponta a ponta testável **antes** da fase de contribuição por terceiros.

Ordem efetiva de entrega:

| Prioridade | Bloco | Conteúdo |
|------------|------|----------|
| 1 | Domínio + hub CRUD | Fase A + parte listagem da Fase B (já consolidados). |
| 2 | **MVP mídia** | Upload admin no hub (`/hub/albums/{album}`), persistência em `albums/{album_id}/original/{uuid}.{ext}`, job `ProcessAlbumPhotoJob` (thumb/medium WebP), vídeo só original + `processing_status=done`. |
| 3 | Viewer público | Fase C — grid, URLs assinadas, fallback para original quando derivadas ainda não existem no disco. |
| 4 | Hardening de acesso | Fase D — token, lockout, etc. (pode coexistir com o MVP; não bloqueia upload). |
| 5 | Contribuição externa | **Fase E** — convite por token no álbum, verificação por e-mail, upload público com `upload_token`, digest ao admin (e-mail + Evolution). |
| 6 | Avançados | Fase F (ZIP, watermark, thumb de vídeo, …). |

As subseções abaixo mantêm a numeração histórica A–F para compatibilidade com PRs e commits anteriores.

### 3.1 Pós-MVP (hub, viewer, upload, contribuição) — 2026-05

Entregas que refinam A–E sem alterar a “fase F” de recursos avançados:

| Tema | Comportamento |
|------|----------------|
| **Listagem completa** | Viewer (`AlbumViewerController`) e hub carregam **todas** as mídias do álbum com `orderBy('sort_position')->orderBy('created_at')`. Não há `limit` de negócio no código. |
| **Livewire e muitas linhas** | O componente `AlbumDetailPage` serializa uma linha por mídia. O guard `payload.max_components` do Livewire (padrão antigo: 20) podia truncar a árvore; o projeto usa `max_components => null` por padrão, com `LIVEWIRE_PAYLOAD_MAX_COMPONENTS` no `.env` se quiser reativar um teto. |
| **Upload admin** | `uploadMedia` valida tamanho por arquivo e **quantidade** com `App\Support\AlbumUploadLimits::maxFilesPerHttpRequest()` (= mínimo entre `ALBUMS_MAX_FILES_PER_BATCH` e o PHP `max_file_uploads`). Arquivos são gravados no disco `local` e o job `IngestAlbumUploadBatchJob` processa após a resposta. O teto do PHP (muitas vezes 20 no padrão) explica avisos do tipo *Maximum number of allowable file uploads has been exceeded* — ajuste `max_file_uploads` no php.ini ou envie em vários lotes. |
| **Reordenação** | No hub, tabela com handle de arraste (SortableJS, CDN) chama `reorderMedia([ids...])`. Campo numérico “Nº” chama `setMediaPosition`. Persistência sequencial em `sort_position` (1…n) dentro de transação. |
| **Exclusão** | Por linha (`deleteMedia`), em lote (`deleteSelectedMedia` + checkboxes), ou álbum inteiro (`deleteAlbum`: remove objetos S3 via `AlbumMediaService::delete`, depois `album->delete()` soft delete; **bloqueado** se `children()->exists()`). |
| **Legenda no viewer** | Coluna `display_name` em `album_media` (quando preenchida no hub) substitui o nome de arquivo na legenda pública. |
| **Revogar contribuição** | `AlbumContributionService::revokeAllUploadTokens` zera tokens de upload dos contribuidores **e** define `albums.contribution_invite_token = null`, para o link de convite público deixar de funcionar. |
| **UI hub** | Destaque para URL da **galeria pública**; grid em duas colunas (envio admin × contribuição externa) em viewports grandes. |
| **Staging local** | Ver §5.4 — limpeza de `album-ingest` / `livewire-tmp`, comando `albums:prune-local-staging`. |

---

## Fase A — Fundação de domínio (schema + models)

### A.1 Tabelas iniciais

`albums`
- `id` (uuid pk)
- `parent_id` (uuid nullable fk albums.id) — permite hierarquia de 2 níveis
- `slug` (unique)
- `title`
- `description` (nullable)
- `cover_media_id` (uuid nullable fk album_media.id)
- `access_type` (`public|password`) default `public`
- `password_hash` (nullable)
- `download_enabled` (bool default false)
- `sort_order` (`date|manual`) default `date`
- `is_locked` (bool default false)
- `thumb_width` (int default 400)
- `thumb_height` (int nullable)
- `thumb_quality` (int default 80)
- timestamps + softDeletes

`album_media`
- `id` (uuid pk)
- `album_id` (uuid fk albums.id)
- `type` (`photo|video`)
- `original_path` (string)
- `thumb_path` (nullable)
- `medium_path` (nullable)
- `video_thumb_path` (nullable)
- `filename_original`
- `display_name` (nullable) — legenda opcional no viewer; editável no hub
- `mime_type`
- `size_bytes`
- `width` (nullable)
- `height` (nullable)
- `duration_seconds` (nullable)
- `sort_position` (int default 0)
- `processing_status` (`pending|processing|done|failed`) default `pending`
- `uploaded_by` (`admin|contributor`) default `admin`
- `contributor_id` (uuid nullable fk contributors.id) — usado na fase E
- `metadata` (jsonb nullable)
- timestamps + softDeletes

### A.2 Regras iniciais

- máximo 2 níveis (`album` raiz e `sub-album`);
- validação para impedir `parent_id` em cascata profunda;
- slug único global por ora;
- somente `public` e `password` na primeira versão.

### A.3 Entregáveis

- migrations + models + factories;
- serviço de domínio inicial (`AlbumService`);
- testes de relação e validações de hierarquia.

---

## Fase B — Hub de gestão + upload admin

### B.1 Rotas

- `GET /hub/albums` — listagem/gestão (`HubPage`)
- `GET /hub/albums/{album}` — upload múltiplo + lista de mídias (`AlbumDetailPage`)

### B.2 Funcionalidades

- CRUD de álbuns (incluindo sub-álbum);
- upload múltiplo de foto/vídeo por admin (validação de quantidade via `AlbumUploadLimits`; ingestão assíncrona `IngestAlbumUploadBatchJob`);
- persistência do original no S3 após o job de ingestão;
- enfileiramento de processamento para gerar thumbs/medium (`ProcessAlbumPhotoJob`);
- gestão no detalhe do álbum: ordem por drag-and-drop ou número; nome de exibição por mídia; exclusão unitária, em lote ou apagar o álbum (com restrições).

### B.3 Jobs

- `ProcessAlbumPhotoJob` — fila dedicada **`media`** (Horizon: `supervisor-media`, `timeout=300`, `memory=384`).
- `ProcessAlbumVideoJob` — adiado para fase F (transcode/thumbnail via FFmpeg).

### B.4 Testes mínimos

- feature do hub (criar/editar/excluir álbum);
- upload com fake disk + dispatch de jobs;
- atualização de `processing_status`.

---

## Fase C — Viewer público MVP (acesso real)

### C.1 Rotas públicas

- `GET /albums/{slug}` — viewer
- `POST /albums/{slug}/auth` — senha (quando `access_type=password`)
- `GET /albums/{slug}/media/{media}/view` — entrega protegida da mídia
- `GET /albums/{slug}/media/{media}/download` — apenas se `download_enabled=true`

### C.2 Segurança base

- signed URL curta (ex.: 15 min);
- nunca expor path bruto do S3 no front;
- para `download_enabled=false`, bloquear ação de download na UI e servir inline.

### C.3 UX MVP

- grid mobile-first;
- suporte foto e vídeo nativo;
- placeholder para mídia em processamento.

### C.4 Testes mínimos

- acesso público e por senha;
- bloqueio quando senha inválida;
- download liberado/bloqueado conforme álbum.

---

## Fase D — Hardening de acesso

### D.1 Ampliação de tipos

`albums.access_type` passa para:
- `public`
- `password`
- `token`
- `one_time`

Campos adicionados:
- `token` (nullable)
- `token_expires_at` (nullable)

### D.2 Proteção de brute force

`access_attempts`
- `album_id`, `ip`, `user_agent`, `attempted_at`, `succeeded`

`album_lockouts`
- `album_id`, `ip`, `locked_at`, `unlocked_at`, `unlocked_by`

Regras:
- lock por IP/álbum após N tentativas (configurável);
- desbloqueio manual via hub;
- notificação imediata por email ao bloquear (WhatsApp opcional).

### D.3 Testes mínimos

- expiração de token;
- invalidação de one-time após primeiro acesso;
- lockout/desbloqueio.

---

## Fase E — Contribuição externa

**Status:** entregue (schema, fluxo público, hub, digest, testes em `tests/Feature/Albums/AlbumContributionsTest.php`).

### E.1 Tabela

`contributors`
- `id` (uuid)
- `album_id` (fk)
- `email`
- `email_verified`
- `verify_token` + `verify_expires_at`
- `upload_token` + `upload_expires_at`
- timestamps

Índice único `(album_id, email)`.

**Álbum (`albums`):** `contribution_invite_token` (nullable, único) — segredo do link público de convite; `contribution_upload_ttl_hours` (nullable) — sobrescreve o TTL padrão do token de upload após a confirmação.

`album_media.contributor_id` → FK `contributors.id` (`nullOnDelete`).

### E.2 Fluxo

1. admin gera ou renova o token de convite no hub (`/hub/albums/{album}`) e copia a URL `/contribute/{album_uuid}/{token}`;
2. convidado informa o e-mail na página do convite;
3. recebe e-mail com link `GET /contribute/confirm/{verify_token}` (validade configurável, default 24 h);
4. após confirmar, recebe `upload_token` com `upload_expires_at` (default global 72 h ou por álbum);
5. `GET/POST /contribute/{upload_token}/upload` envia mídias; `AlbumMediaUploadService::store(..., Contributor)` grava S3, define `uploaded_by=contributor`, dispara `ProcessAlbumPhotoJob` nas fotos (fila `media`).
6. admin pode **revogar** todos os `upload_token` dos contribuidores do álbum **e** limpar `contribution_invite_token` (invalida o link de convite público), ou **gerar novo token de convite** (invalida URLs antigas do passo 1).

### E.3 Notificação

- Cada upload de contribuidor acumula uma linha em buffer (Laravel **Cache** com lock; em produção multi-processo recomenda-se `CACHE_STORE=redis` para equivalência ao debounce centralizado).
- Um único `SendAlbumContributionDigestJob` é agendado por janela (`ALBUMS_CONTRIBUTION_NOTIFY_DEBOUNCE_SECONDS`, default 600 s), fila **`notifications`** (mesmo padrão de jobs leves como `NotificarVencimento`).
- E-mail ao admin: `ALBUMS_CONTRIBUTION_NOTIFY_EMAIL` ou fallback `mail.from.address`; resumo em Markdown (`AlbumContributionDigestMail`).
- WhatsApp: `ALBUMS_CONTRIBUTIONS_WHATSAPP_JID` ou fallback `WHATSAPP_UTILITIES_HOME_GROUP_JID`, via `App\Services\EvolutionService::sendText` (telão alinhado a faturas).

### E.4 Testes mínimos

- verify token;
- upload autorizado por token válido;
- notificação consolidada por janela.

Cobertos: token de convite inválido; verificação expirada; link de confirmação já usado / inexistente; upload após revogação; múltiplos arquivos no mesmo POST geram um único dispatch do digest job; hub exige autenticação; geração de convite no Livewire.

### E.5 Configuração (`.env`)

Ver `.env.example`: `ALBUMS_CONTRIBUTION_*`, `ALBUMS_MAX_FILES_PER_BATCH` (respeitado até o teto do PHP `max_file_uploads`), opcional `ALBUMS_CONTRIBUTIONS_WHATSAPP_JID`, e opcional `LIVEWIRE_PAYLOAD_MAX_COMPONENTS` para limitar componentes Livewire por request.

---

## Fase F — Recursos avançados (pós-core)

- upload ZIP (`ProcessAlbumZipUploadJob`);
- tags (`tags` + `album_media_tag`);
- watermark on-the-fly (fotos);
- geração de thumb para vídeo via ffmpeg;
- download ZIP do álbum on-demand.

Entram somente após métricas de uso e estabilidade das fases C/D.

---

## 4. Endpoints-alvo (por padrão Hub)

### 4.1 Hub autenticado

```
GET    /hub/albums
GET    /hub/albums/{album}
POST   /hub/albums
PATCH  /hub/albums/{album}
DELETE /hub/albums/{album}
POST   /hub/albums/{album}/media
PATCH  /hub/albums/{album}/media/reorder
DELETE /hub/media/{media}
```

Nota: upload admin implementado em `GET /hub/albums/{album}` (Livewire, ação `uploadMedia` + `AlbumMediaUploadService`); as rotas `POST /hub/albums/{album}/media` permanecem como alvo de evolução (ex. API/reordenação) se necessário.

### 4.2 Público

```
GET    /albums/{slug}
POST   /albums/{slug}/auth
GET    /albums/{slug}/media/{media}/view
GET    /albums/{slug}/media/{media}/download
```

### 4.3 Contribuição (fase E+)

Implementado (ordem de registro: `confirm` e `verify` antes das rotas parametrizadas):

```
GET    /contribute/confirm/{verify_token}
POST   /contribute/verify
GET    /contribute/{album_uuid}/{token}
GET    /contribute/{upload_token}/upload
POST   /contribute/{upload_token}/upload
```

`{album_uuid}` validado com `whereUuid('album')`. Throttle aplicado ao grupo e rotas sensíveis.

---

## 5. Storage (S3/MinIO)

### 5.1 Chaves de objeto

Estrutura recomendada de chave:

```
/albums/{album_id}/original/{media_uuid}.{ext}
/albums/{album_id}/thumbs/{media_uuid}.webp
/albums/{album_id}/medium/{media_uuid}.webp
/albums/{album_id}/video_thumbs/{media_uuid}.webp
/albums/watermark/logo.png
```

### 5.2 Configuração (Laravel)

- Credenciais e endpoint vêm de **`config/filesystems.php`** (`disks.s3`) + variáveis **`AWS_*`** no `.env` — sem valores fixos no código.
- **MinIO** (e endpoints compatíveis S3 self-hosted) costumam exigir **`AWS_USE_PATH_STYLE_ENDPOINT=true`**.
- O disco **padrão da aplicação** pode ser `s3` em produção; o **upload temporário do Livewire** deve usar disco **`local`** (ver `config/livewire.php` → `temporary_file_upload.disk`), senão o componente não aceita `multiple` no `<input type="file">` quando o driver temporário é S3.
- **Produção Docker:** limites reais de corpo da requisição vêm do PHP (`docker/php/zz-uploads.ini` na imagem) e do Nginx do Compose (`docker/nginx/default.conf` → `client_max_body_size`). O proxy do host (ex.: aaPanel) deve espelhar esse limite. Variáveis `ALBUMS_*` no `.env` e comentários em `.env.example`; changelog **2026-05-10**.

### 5.3 Dev local apontando para MinIO de produção (cuidado operacional)

Possível para validar integração real, **somente** com consciência de risco:

- Use credenciais com permissão **mínima** (idealmente um usuário/somente bucket de staging, não root MinIO).
- Conferir **`AWS_ENDPOINT`** acessível da máquina/container onde roda o PHP (URL interna vs pública).
- Evitar testes destrutivos em bucket compartilhado; preferir prefixo ou bucket dedicado a dev.
- Nunca commitar segredos; espelhar apenas nomes de variáveis no `.env.example`.

### 5.4 Disco local (`storage/app/private`) — `album-ingest` e `livewire-tmp`

O disco **`local`** aponta para `storage/app/private` (ver `config/filesystems.php`). Dois prefixos crescem durante o uso:

| Prefixo | Origem | Limpeza esperada |
|---------|--------|------------------|
| **`livewire-tmp/`** | Upload temporário do Livewire antes do `storeAs` para `album-ingest`. Com o mesmo disco `local`, o Livewire **move** o arquivo (não copia), então o tmp some após cada arquivo bem enviado ao hub. O trait `WithFileUploads` também remove uploads **mais velhos que 24 h** dentro do tmp quando `_finishUpload` roda (`temporary_file_upload.cleanup` em `config/livewire.php`). Uploads abandonados (usuário não submeteu o formulário) podem ficar até expirarem por idade ou até o comando abaixo. |
| **`album-ingest/`** | Cópias intermediárias antes do job enviar ao S3 (`IngestAlbumUploadBatchJob`). Após ingestão bem-sucedida, `AlbumMediaUploadService::ingestFromStoredLocalPath` **apaga o arquivo** no `finally` (incluindo quando o tipo MIME/extensão é rejeitado — corrigido para não deixar lixo). Ao terminar o batch, o job **remove o diretório do lote** (`album-ingest/{album_id}/{batch_uuid}/`). |

**Manutenção:** comando Artisan `php artisan albums:prune-local-staging {--hours=48} {--dry-run}` remove arquivos **mais antigos que N horas** em `album-ingest` e `livewire-tmp`. Agendamento semanal em `bootstrap/app.php` (segunda-feira 03:30). Em servidores sem `schedule:run`, executar manualmente ou via cron.

---

## 6. Filas e operação

- processamento de mídia é assíncrono na fila **`media`**;
- resumo de contribuições externas: `SendAlbumContributionDigestJob` na fila **`notifications`** (debounce por álbum antes do envio);
- jobs devem registrar falhas e manter `processing_status` consistente (`pending|processing|done|failed`);
- manter retries padrão (`$tries = 3`) e `failed()` com log estruturado;
- monitoramento no Horizon (`supervisor-media`); fallback `queue:work` do `docker-compose.yml` também consome `media`.

### 6.1 Dependências de runtime (containers)

- **PHP GD** com suporte a **JPEG/PNG/WebP/Freetype** — necessário para `ProcessAlbumPhotoJob` (`imagecreatefromstring`, `imagewebp`, `imagescale`). No `Dockerfile` está com `libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev` + `docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp` + `docker-php-ext-install gd`.
- **PHP `zip`** (`libzip-dev` + `docker-php-ext-install zip`) — `ZipArchive` para a fase F (ingestão ZIP, download ZIP). Pacotes **`zip`/`unzip`** no SO cobrem CLI quando um job preferir subprocesso.
- **FFmpeg**: a imagem Docker **já instala** `ffmpeg` para a fase F (thumbnail/transcode). O código atual **ainda não** invoca o binário — vídeos seguem como original no S3 com `processing_status=done` até existir job dedicado (ex. `ProcessAlbumVideoJob`).
- Para Horizon enxergar jobs em dev, **`QUEUE_CONNECTION=redis`** (com `sync`, jobs executam inline no request e não aparecem na UI).

---

## 7. Critério de pronto por fase

Uma fase só é considerada concluída quando houver:

1. schema/migrations finalizados para o escopo da fase;
2. fluxo principal funcional ponta a ponta;
3. testes automatizados mínimos cobrindo casos de sucesso e falha;
4. documentação atualizada (`README`/`SPEC`/`docs/roadmap/BACKLOG.md` quando aplicável).

---

## 8. Próximo passo recomendado

1. Operar contribuição externa em staging (e-mail real, Evolution, `CACHE_STORE=redis` se múltiplos workers). Em produção com Docker, `max_file_uploads` e demais limites de upload estão em `docker/php/zz-uploads.ini` (rebuild da imagem após ajuste).
2. **Fase F** quando fizer sentido: ZIP, watermark, thumb de vídeo (FFmpeg), tags, download ZIP do álbum — a imagem PHP do projeto **já inclui** `ffmpeg` e CLI `zip`/`unzip`; falta implementação de jobs/UI.

---

## 9. Referência rápida de classes e arquivos (atualização contínua)

| Área | Principais pontos no código |
|------|-----------------------------|
| Hub detalhe | `App\Livewire\Albums\AlbumDetailPage`, view `resources/views/livewire/albums/album-detail-page.blade.php` |
| Limites de upload | `App\Support\AlbumUploadLimits`, `config('services.albums.max_upload_bytes')`, `max_files_per_batch` |
| Contribuição | `App\Services\Albums\AlbumContributionService`, `App\Http\Controllers\Albums\AlbumContributionController` |
| Viewer | `App\Http\Controllers\Albums\AlbumViewerController`, `resources/views/albums/viewer.blade.php` |
| Payload Livewire | `config/livewire.php` → `payload.max_components` |
| Histórico de produto | [CHANGELOG.md](../../CHANGELOG.md) (ex.: 2026-05-09, 2026-05-10) |
| Limpeza disco local | `php artisan albums:prune-local-staging` (§5.4) |
