# SPEC — Álbuns de mídia

Hub `/hub/albums` (Livewire), viewer público `/albums/{slug}` com lockout, contribuição externa por convite com verificação por e-mail e digest ao dono.

Esta SPEC é a entrada canônica do módulo. Detalhamento histórico das fases A–E (campos completos das tabelas, decisões por fase, refinamentos pós-MVP) está em [IMPLEMENTATION.md](IMPLEMENTATION.md) — mantido como referência viva de implementação.

---

## Banco

### `albums`

```
id, slug (unique),
parent_id (FK nullable → albums), -- hierarquia, máximo 2 níveis
title, description,
cover_media_id (FK nullable → album_media),
cover_thumb_path, cover_blurhash,

access_type,                     -- public | password | token | one_time
password_hash,
token,                           -- para access_type=token
token_expires_at,
one_time_used_at,

download_enabled (bool),
is_locked (bool),                -- lockout temporário por força bruta

-- contribuição externa
contribution_invite_token,
contribution_upload_ttl_hours,

deleted_at, timestamps
```

### `album_media`

```
id, album_id (FK),
type,                            -- photo | video
original_path, thumb_path, medium_path,
display_name,                    -- legenda no viewer
processing_status,               -- pending | processing | done | failed
sort_position,
uploaded_by,                     -- admin | contributor
contributor_id (FK nullable → contributors),
metadata (json),
timestamps
```

### `contributors`

Convites para upload externo, por álbum.

```
id, album_id (FK),
email,
verify_token, verify_expires_at,
upload_token, upload_expires_at,
verified_at,
timestamps

UNIQUE: (album_id, email)
```

### `access_attempts` / `album_lockouts`

Proteção contra força bruta no viewer (gerenciado por `AlbumAccessService`).

---

## Rotas

| Contexto | Rota |
|----------|------|
| Hub (auth) | `GET /hub/albums`, `GET /hub/albums/{album}` |
| Viewer público | `GET /albums/{slug}`, `POST /albums/{slug}/auth` |
| Mídia pública | `GET /albums/{slug}/media/{media}/view` e `.../download` (URLs assinadas) |
| Convite contribuição | `GET /contribute/{album_uuid}/{token}` |
| Verificação | `POST /contribute/verify`, `GET /contribute/confirm/{verify_token}` |
| Upload | `GET|POST /contribute/{upload_token}/upload` |

---

## Serviços (`App\Services\Albums`)

- `AlbumService` — CRUD de álbum e sub-álbum.
- `AlbumMediaUploadService` — recebe arquivos do hub/contribuidor, ingestão via job.
- `AlbumMediaService` — listagem, ordenação, exclusão.
- `AlbumAccessService` — autenticação no viewer público, lockout.
- `AlbumContributionService` — convites, verificação, revogação.
- `AlbumContributionDigestService` — debounce e envio do digest ao dono.

Integração com `EvolutionService` para resumo de contribuição via WhatsApp.

---

## Jobs

| Job | Fila | Trigger |
|-----|------|---------|
| `ProcessAlbumPhotoJob` | `media` | Após upload de foto. Gera WebP thumb/medium (GD). |
| `SendAlbumContributionDigestJob` | `notifications` | Debounce após upload por contribuidor — e-mail admin + WhatsApp. |
| `IngestAlbumUploadBatchJob` | (depende do contexto) | Ingestão de lote de upload após resposta HTTP (não bloqueia request). |

---

## Hub `/hub/albums`

Componentes: `App\Livewire\Albums\HubPage` (listagem), `App\Livewire\Albums\AlbumDetailPage` (detalhe).

Funcionalidades:

- CRUD de álbuns hierárquicos.
- Upload em lote (arquivos para disco `local`, job `IngestAlbumUploadBatchJob` roda após resposta).
- **Reordenação** por arrastar e soltar (SortableJS via CDN, coluna com handle) e por **número de posição** (coluna "Nº"); métodos `reorderMedia`, `setMediaPosition`, `persistSortOrder`.
- **Exclusão em lote** (checkboxes + "Apagar selecionadas").
- **Apagar álbum inteiro** (remove objetos no S3, apaga mídias, soft delete; bloqueado se houver subálbum).
- **Galeria pública** em bloco destacado (URL absoluta, rota relativa, botão "Abrir galeria").
- Layout em **duas colunas** em `lg+`: "Envio de arquivos (hub)" e "Contribuição externa".

### Contribuição externa

- Geração de link de convite (`contribution_invite_token`).
- Fluxo do convidado: link → e-mail → verificação → upload com TTL.
- "Revogar uploads" zera `upload_token`/`upload_expires_at` dos contribuidores **e** limpa `albums.contribution_invite_token` (invalida o link público de convite).

### Limites de upload

`App\Support\AlbumUploadLimits` calcula o teto real:

```
min(ALBUMS_MAX_FILES_PER_BATCH, PHP max_file_uploads)
```

UI mostra texto de ajuda quando o limite do PHP for o gargalo.

Upload temporário do Livewire vai para disco `local` quando o app usa S3. Opcional: `LIVEWIRE_PAYLOAD_MAX_COMPONENTS` para limitar componentes por batch (default null = sem limite).

### Comando de manutenção

```bash
php artisan albums:prune-local-staging [--hours=48] [--dry-run]
```

Poda arquivos antigos em `album-ingest` e `livewire-tmp` sob `storage/app/private`. Agendado semanal (segunda 03:30) em `bootstrap/app.php`.

---

## Viewer público

Rota `GET /albums/{slug}` com lightbox.

- Listagem **sem teto artificial** de mídias (ordenadas por `sort_position`, `created_at`).
- Autenticação por `access_type` (público, senha, token, one-time).
- Lockout após tentativas falhas (`AlbumAccessService` + `access_attempts` + `album_lockouts`).
- URLs assinadas para `view` e `download`.

---

## Variáveis de ambiente

```env
ALBUMS_MAX_FILES_PER_BATCH=
ALBUMS_CONTRIBUTION_DIGEST_DEBOUNCE_SECONDS=
ALBUMS_DEFAULT_CONTRIBUTION_TTL_HOURS=

# Limites Docker (também em docker/php/zz-uploads.ini e docker/nginx/default.conf)
# - max_file_uploads
# - upload_max_filesize
# - post_max_size
# - memory_limit
# - client_max_body_size (Nginx do Compose + Nginx do host aaPanel)
```

---

## Fase F — backlog

Pendente (ver [BACKLOG](../roadmap/BACKLOG.md)):

- Upload via ZIP.
- Watermark on-the-fly.
- Thumbnail / transcode de vídeo (FFmpeg).
- Tags.
- Download ZIP do álbum.

A imagem PHP **já inclui** `ffmpeg` e `zip`/`unzip` no SO; o código ainda não usa.

---

## Testes

`tests/Feature/Albums/` — serviço, hub, viewer, upload, job de foto, contribuições, prune, batch ingestion.

- `AlbumHubMediaUploadTest` — bulk delete, delete album, bloqueio com subálbum.
- `AlbumContributionsTest` — revogação limpa `contribution_invite_token`.
- `IngestAlbumUploadBatchJobTest` — arquivo rejeitado removido, diretório do lote apagado.

---

## Documentos relacionados

- [IMPLEMENTATION.md](IMPLEMENTATION.md) — detalhamento histórico das fases A–E (schema completo por fase, decisões, refinamentos §3.1 e §5.4).
