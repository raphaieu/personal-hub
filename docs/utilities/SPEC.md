# SPEC — Faturas Embasa/Coelba

Scraping agendado das concessionárias (Embasa/Coelba), persistência idempotente em `invoices`, PDF no MinIO, lembretes WhatsApp para o grupo da casa.

---

## Banco

### `utility_accounts`

```
id, kind (embasa|coelba),
account_ref,                     -- matrícula (Embasa) ou código_cliente (Coelba)
label, due_day (int),
reminder_lead_days (int, default 5),
is_active (bool),
last_scraped_at (timestamp nullable),
timestamps
```

### `invoices`

```
id, utility_account_id (FK),
billing_reference,               -- ex.: "05/2026"
due_date,
amount_total, amount_water, amount_sewage, amount_service,
water_consumption_m3 (nullable),
status,                          -- pendente | pago | processando | a_vencer | vencida
payment_date (nullable),
pdf_path,
raw_payload (json),
scraped_at, last_notified_at,
timestamps

UNIQUE: utility_account_id + billing_reference
```

---

## Cliente Playwright

Contrato: `App\Contracts\UtilityScraperClientInterface`.

| Classe | Papel |
|--------|-------|
| `App\Services\Utilities\UtilityPlaywrightService` | HTTP real para o container Playwright (`POST /embasa/scrape`, `POST /coelba/scrape` em `services.playwright.url`, timeout `services.playwright.timeout`). |
| `App\Services\Utilities\FakeUtilityScraperClient` | Implementação fake para testes. |

Bind em `AppServiceProvider`. Jobs de domínio nunca acoplam à runtime Node.

### Endpoints do servidor Playwright

Servidor Node em `playwright/server.js`, container `raphael-playwright` na porta interna `3001`.

- `GET /health` → `{ status: 'ok', embasa_session_ready, embasa_session_path, coelba_session_ready, coelba_session_path }`.
- `POST /embasa/scrape` → executa scraper Embasa, retorna JSON.
- `POST /coelba/scrape` → executa scraper Coelba com CapSolver, retorna JSON.

### Envelope de resposta

```json
{
  "success": true,
  "mode": "embasa",
  "concessionaria": "embasa",
  "scraped_at": "ISO8601",
  "data": {
    "concessionaria": "embasa",
    "matricula": "...",
    "scraped_at": "ISO8601",
    "faturas": [
      {
        "referencia": "05/2026",
        "vencimento": "08/05/2026",
        "valor_total": "R$ 77,81",
        "status": "pendente|pago|processando|a_vencer|vencida",
        "data_pagamento": null
      }
    ],
    "pdf_path": "/app/downloads/embasa_1234567890.pdf"
  }
}
```

Coelba inclui em `data` opcionalmente `codigo_cliente`, `pix_code` e o mesmo arranjo de `faturas` / `pdf_path` quando extraídos na home.

### Sessão persistente

Cada scraper mantém `storageState` dedicado:

- `EMBASA_SESSION_PATH` (default `/app/storage/embasa-session.json`).
- `COELBA_SESSION_PATH` (default `/app/storage/coelba-session.json`).

Fluxo de execução:

1. Tenta scraping com sessão existente.
2. Se a sessão falhar/expirar, faz relogin automático.
3. Persiste novo `storageState` e repete o scraping.

**Coelba opera em modo login full-flow por execução** (sem reaproveitar sessão), privilegiando estabilidade na SPA Angular.

---

## Fluxo Embasa

```
URL login:   https://atendimentovirtual.embasa.ba.gov.br/login
Campos:      input CPF + input[type=password]
Botão:       button "Entrar"
Pós-login:   /home

Matrícula:   dropdown "Matrícula: Selecionar" no header
             modal "Minhas Matrículas"
             clica na matrícula
             "SELECIONAR MATRÍCULA"

2ª via:      /segunda-via?pay=true
             matrícula preenchida → "PRÓXIMO"
             tela "Débitos da Matrícula"

Dados:       Referência, Vencimento, Consumo m³, Valor Água,
             Valor Esgoto, Valor Serviço, Valor Total, Status
Status:      "Aguardando pagamento" → pendente
             "Conta Paga ✓"         → pago
             "em processamento bancário" → processando

PDF:         botão "BAIXAR 2ª VIA" em cada fatura
```

### Caso sem débitos

Quando a 2ª via exibe **"A Matrícula informada não possui débitos"**, o scraper navega para `/home`, reutiliza a seleção de matrícula e extrai do carrossel **MINHAS CONTAS** (`.card-minhas-contas .inner-card`): mês/ano → `referencia` `mm/aaaa`, vencimento, consumo, valor total, texto do botão de status.

`pdf_path` permanece `null` se não houver fatura pendente para download. O payload segue `success: true` com `faturas` preenchidas — **não tratar como falha**.

Fallback secundário: se a tabela da 2ª via existir mas o parse falhar, há fallback para o mesmo carrossel da home.

### Modais bloqueantes

Modais `section.blk-modal` (avisos/overlays) podem interceptar cliques. O script chama `dismissEmbasaBlockingModals` antes do fluxo e usa `span.matricula` com clique forçado quando necessário.

Implementação em `playwright/src/embasa-scraper.js`.

---

## Fluxo Coelba

```
URL:         https://agenciavirtual.neoenergia.com/#/login
CAPTCHA:     reCAPTCHA v3 invisível — CapSolver

Botão:       "LOGIN" no header/hero → abre modal
Campos:      input CPF/CNPJ + input[type=password]
Botão:       "ENTRAR"
Pós-login:   /#/home/selecionar-estado

Estado:      card "Bahia" → /#/home/meus-imoveis
Unidade:     código do cliente (ex.: 000030287096) → /#/home

Faturas:     "Faturas e 2ª via de faturas" (serviços rápidos)
             → /#/home/servicos/consultar-debitos

Dados:       Referência, Vencimento, Valor Fatura, Situação, Data Pagamento
Status:      "A Vencer" → a_vencer
             "Vencida"  → vencida
             "Pago"     → pago

PDF:         expande item → "BAIXAR"
```

### Estratégia atual do scraper

1. Login.
2. Selecionar estado Bahia.
3. Selecionar unidade consumidora.
4. Coletar dados principais no card **Última Fatura** da home (`valor`, `vencimento`, `situação`).
5. Abrir modal PIX para capturar código quando disponível.
6. Baixar PDF por `Mais opções` → `Opções de fatura` → `Download` (motivo `Não Recebi` → `Baixar`).

Implementação em `playwright/src/coelba-scraper-v2.js`.

### CapSolver

- Tipo: `ReCaptchaV3TaskProxyLess`.
- `websiteURL`: `https://agenciavirtual.neoenergia.com`.
- `pageAction`: `login`.
- `minScore`: `0.5`.
- Fallback: tenta submeter sem token se CapSolver falhar.

---

## Serviço de domínio — `InvoiceService`

`App\Services\InvoiceService` orquestra persistência a partir do payload normalizado do scraper.

### `processScrapeResult(array $payload, UtilityAccount $account)`

- `updateOrCreate` por (`utility_account_id`, `billing_reference`).
- Preenche valores Embasa/Coelba.
- Grava `raw_payload` com a linha da fatura (e `playwright_pdf_path` quando o PDF não pôde ser copiado).
- Associa PDF ao faturamento preferencial (pendente/vencida/a_vencer/processando, menor vencimento).

### `uploadPdf(string $localPath, UtilityAccount $account, string $billingReference)`

- Se o arquivo existir no filesystem **visível ao PHP**, grava em `utilities/invoices/{utility_account_id}/…` no disco `services.utilities.pdf_storage_disk` (`UTILITIES_INVOICE_PDF_DISK`).
  - Default: `local` (= `storage/app/private`).
  - Use `s3` + credenciais MinIO/AWS para objeto no bucket.
- Após `put` com sucesso, pode apagar o arquivo fonte (`UTILITIES_DELETE_SOURCE_PDF_AFTER_UPLOAD`; default automático: apaga fonte quando o disco é `s3`) — **só** se o path do Playwright existir no mesmo host/volume que o PHP (bind mount Docker).
- Caso contrário retorna `null` na cópia ou loga falha ao apagar.

### `notifyHomeGroup(Invoice $invoice): bool`

- Monta texto de lembrete e chama `EvolutionService::sendText` com `services.whatsapp.utilities_home_group_jid` (`WHATSAPP_UTILITIES_HOME_GROUP_JID`, fallback `WHATSAPP_GRUPO_CASA_JID`).
- Retorna `false` se JID ausente ou Evolution incompleta (sem atualizar `last_notified_at` no job).
- Retorna `true` após envio bem-sucedido.

---

## Jobs

| Job | Fila | Trigger |
|-----|------|---------|
| `ScrapeConta` | `scraping` | Schedule ou on-demand. |
| `VerificarStatusFaturas` | `default` | Schedule diário. |
| `NotificarVencimento` | `notifications` | Schedule diário. |

### `ScrapeConta` (`App\Jobs\ScrapeConta`)

Construtor: `kind` (`embasa`|`coelba`), opcional `ignoreScrapeWindow` (default `false`), opcional `force` (default `false`).

- `ignoreScrapeWindow = true` → ignora `UtilityScrapeWindow` (usado por `VerificarStatusFaturas`).
- `force = true` → sempre chama o Playwright (scrape manual via dashboard).

**Heurística sem `force` (`App\Support\UtilityAccountScrapeGate`):** para cada conta candidata, resolve a fatura de referência (mês atual `mm/aaaa` ou a mais recente por `due_date`). Se **todas** indicam que não precisa Playwright, encerra com log `utilities.scrape_conta.skipped_idempotent` **sem** chamar scraper. Não precisa de scrape quando:

- Não há fatura.
- Status `pago`.
- (`a_vencer` ou `pendente`) **e** hoje é **antes** do `due_date`.

Caso contrário (vence hoje ou já passou ainda não pago, ou `vencida`/`processando`/outros, ou sem linha) → executa scrape.

**Execução:**

1. Lista `utility_accounts` ativas do `kind` (com filtro de janela, salvo se `ignoreScrapeWindow`).
2. Lista vazia → encerra.
3. Gate dispensa scrape para todas e `force = false` → encerra.
4. Executa **um** scrape (`scrapeEmbasa()` ou `scrapeCoelba()`).
5. Escolhe contas alvo: preferência por `account_ref = data.matricula` (Embasa) ou `data.codigo_cliente` (Coelba); se nenhuma bater e existir exatamente uma conta elegível, usa essa (modo single-tenant).
6. Para cada conta alvo: `InvoiceService::processScrapeResult($payload, $account)` e atualiza `utility_accounts.last_scraped_at`.

**CLI manual:**

```bash
php artisan utilities:scrape {embasa|coelba} [--force] [--ignore-window]
```

### `VerificarStatusFaturas`

1. Descobre `kind` distintos em `utility_accounts` ativas com pelo menos uma `invoice` `status != pago`.
2. Para cada `kind`, enfileira `ScrapeConta` com `ignoreScrapeWindow: true` e `force: false` (respeita o gate).

### `NotificarVencimento`

1. Seleciona `invoices` com `status != pago`, `last_notified_at` nulo ou não no dia corrente (timezone do app), vencimento **vencido** **ou** entre hoje e hoje + `services.utilities.notify_days_ahead` (`UTILITIES_NOTIFY_DAYS_AHEAD`, default 7).
2. Para cada candidata: `InvoiceService::notifyHomeGroup($invoice)`. Se `true`, define `last_notified_at = now()`.
3. Falhas HTTP por fatura logadas, não interrompem as demais.

---

## Schedule

```php
$schedule->job(new ScrapeConta('embasa'))->dailyAt('08:00');
$schedule->job(new ScrapeConta('coelba'))->dailyAt('08:05');
$schedule->job(new VerificarStatusFaturas())->dailyAt('09:00');
$schedule->job(new NotificarVencimento())->dailyAt('09:30');
```

---

## Hub `/hub/utilities`

- Rota: `GET /hub/utilities` (`utilities.hub`), componente Livewire `App\Livewire\Utilities\HubPage`, layout `layouts.app`. Link na navegação principal.
- Gestão de `utility_accounts`: criação e edição de `kind` (fixo após criação), `account_ref`, `label`, `due_day`, `reminder_lead_days`, `is_active`; toggle rápido ativo/inativo.
- Painel de faturas por conta: query string `?conta={id}` (`selectedAccountId`), listagem paginada de `invoices` (referência, vencimento, valor, status).
- PDF: link **Baixar PDF** só quando `invoices.pdf_path` existe no disco configurado (verificado via `App\Support\UtilityInvoiceDisk::exists` — trata falhas S3/MinIO sem 500 no Livewire). Download via `GET /hub/utilities/invoices/{invoice}/pdf` (`utilities.invoice.pdf`).
- Ação **Scrape agora** (por linha): enfileira `ScrapeConta` com `ignoreScrapeWindow: true` e `force: true` para o `kind`. Flash `utilities_hub_notice`.

Cobertura: `tests/Feature/Utilities/UtilitiesHubPageTest.php`.

---

## Variáveis de ambiente

```env
EMBASA_CPF=
EMBASA_PASSWORD=
EMBASA_MATRICULA=28367294
EMBASA_SESSION_PATH=/app/storage/embasa-session.json

COELBA_CPF=
COELBA_PASSWORD=
COELBA_CODIGO_CLIENTE=000030287096
COELBA_SESSION_PATH=/app/storage/coelba-session.json

CAPSOLVER_API_KEY=

UTILITIES_INVOICE_PDF_DISK=local      # ou s3 para MinIO/AWS
UTILITIES_DELETE_SOURCE_PDF_AFTER_UPLOAD=
UTILITIES_NOTIFY_DAYS_AHEAD=7

WHATSAPP_UTILITIES_HOME_GROUP_JID=     # fallback para WHATSAPP_GRUPO_CASA_JID
PLAYWRIGHT_SERVICE_URL=http://raphael-playwright:3001
PLAYWRIGHT_HTTP_TIMEOUT=
```

---

## Testes

- `tests/Feature/Utilities/UtilityScraperClientTest.php` — contrato + fake.
- `tests/Feature/Utilities/InvoiceServiceTest.php` — mapeamento, idempotência, upload local de PDF.
- `tests/Feature/Utilities/ScrapeContaJobTest.php` — job síncrono com `FakeUtilityScraperClient`.
- `tests/Unit/UtilityScrapeWindowTest.php` — limites da janela.
- `tests/Unit/UtilityAccountScrapeGateTest.php` — heurística do gate.
- `tests/Unit/EvolutionServiceTest.php`.
- `tests/Feature/Utilities/VerificarStatusFaturasJobTest.php`.
- `tests/Feature/Utilities/NotificarVencimentoJobTest.php`.
- `tests/Feature/Utilities/UtilitiesHubPageTest.php`.

## Comandos úteis

```bash
# Scrape manual
php artisan utilities:scrape embasa --force
php artisan utilities:scrape coelba --force --ignore-window
```
