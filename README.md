# Raphael HUB

> Seu número de WhatsApp como painel de controle da vida real.

## Visão Geral

**Raphael HUB** é uma plataforma pessoal de automação doméstica, produtividade e organização digital baseada em **WhatsApp + Dashboard Web**, com **IA híbrida** (processamento local na VPS quando faz sentido + provedores em nuvem como fallback) e **infraestrutura própria** (VPS + Docker).

O projeto centraliza tarefas reais do dia a dia:

* Monitoramento automático de contas (água / luz)
* Lembretes pessoais inteligentes
* Histórico de links, imagens e notas
* Alertas para família e grupos
* Dashboard web com métricas e controle
* Base para futuras automações pessoais com IA

A proposta é simples: usar o **WhatsApp como interface natural** e o sistema como cérebro operacional.

---

## Problemas que Resolve

### Casa / Família

* Contas vencem e ninguém lembra
* Familiares precisam perguntar valores
* Segunda via é burocrática
* Falta histórico centralizado

### Produtividade Pessoal

* Mensagens enviadas para si mesmo se perdem
* Links importantes somem
* Imagens úteis ficam esquecidas
* Ideias rápidas desaparecem

### Organização Digital

* Informação espalhada
* Zero automação
* Dependência de memória humana

---

## Solução

### WhatsApp como Interface

Você pode:

* mandar texto para si mesmo
* enviar links
* mandar imagens
* perguntar contas
* receber alertas
* interagir sem abrir outro app

### Dashboard Web

Painel com:

* contas atuais
* históricos
* consumo mensal
* lembretes
* logs
* métricas
* fontes monitoradas

### IA Aplicada

Classificação automática de mensagens, intenção e contexto — com preferência por **Ollama no host** para tarefas rápidas e frequentes; nuvem quando precisar de mais capacidade (ver secção **Camada de IA** abaixo).

---

## Casos de Uso

### Contas Domésticas

Pergunta no WhatsApp:

> Quanto ficou a conta de água?

Resposta automática com:

* valor
* vencimento
* status
* PDF da fatura

### Lembretes Pessoais

Mensagem:

> Comprar cabo HDMI amanhã

Sistema salva e categoriza.

### Links Importantes

Mensagem:

> [https://site.com/artigo](https://site.com/artigo)

Sistema salva com preview e busca futura.

### Alertas Familiares

Grupo recebe:

> Conta de luz vence em 3 dias.

Se não pagar, o sistema insiste.

---

## Arquitetura Técnica

### Backend

* PHP 8.4
* Laravel 13

### Frontend

* Blade
* Livewire 4

### Banco

* PostgreSQL 17

### Filas / Cache

* Redis 7
* Laravel Horizon
* Laravel Scheduler (worker dedicado em produção)

### Storage

* MinIO próprio (S3 compatível), atrás de proxy para o público

### Scraping

* Node.js 24
* Playwright

### WhatsApp

* Evolution API em stack Docker dedicada ao Hub (instância isolada)

### Infra

* Docker Compose na VPS
* Deploy via GitHub Actions + script na VPS (`deploy.sh`: diff inteligente, rebuild condicional, migrations quando há mudança em `database/migrations`, cache Laravel, health checks)
* VPS Linux
* Cloudflare Tunnel (ambiente dev / webhooks externos)

---

## Camada de IA (Orquestrada)

O projeto utiliza **NeuronAI** (PHP) como camada de abstração entre modelos.

Isso permite trocar provedores sem reescrever prompts, tools ou fluxos.

### Estratégia atual

1. **Ollama no host da VPS** (acesso dos containers via gateway da bridge Docker, ex.: `172.23.0.1:11434`)

   * análises rápidas
   * sentimento
   * tarefas simples
   * custo zero por uso

2. **Groq**

   * alta velocidade
   * tarefas intermediárias na nuvem

3. **Execução paralela / redundância opcional**

   * combinar Ollama + nuvem quando fizer sentido

4. **Fallback na nuvem**

   * OpenAI
   * Anthropic Claude

Objetivo: custo baixo, velocidade alta e qualidade quando necessário.

### Implementação no código

- **`AiRouterService`** — política de qual provider tentar primeiro e fallback (ver [SPEC.md](SPEC.md)).
- **`OllamaService`** — provider NeuronAI para o daemon no host (`OLLAMA_BASE_URL` com `/api`).
- **`NeuronAIService`** — ponto de entrada único (`complete` + `AiCompletionResult`).
- **`POST /iara`** — gateway JSON para debug e para **chamar a API de produção sem Ollama local** (header `X-Internal-Key` em produção). Config: `IARA_*` no `.env.example`.

### Comunicação Docker ↔ Ollama

Os containers do Laravel não rodam no host network: o endpoint típico de Ollama para o app é **`http://172.23.0.1:11434`** (gateway da rede Docker na VPS). No host, o serviço Ollama escuta na porta **11434**; o firewall deve restringir esse porto à rede Docker, não à internet aberta.

---

## Ambientes

### Produção

`https://api.raphael-martins.com`

Deploy automatizado via GitHub Actions; na VPS o `deploy.sh` aplica **pull**, detecta o que mudou e só **rebuilda/reinstala** o necessário (imagem do app, assets, Playwright opcional, migrations condicionais).

### Armazenamento público (MinIO)

`https://files.raphael-martins.com` — console/API MinIO atrás de proxy (portas mapeadas no host para operação local: API **19000**, console **19001**, conforme `docker-compose.yml`).

### Desenvolvimento Local

`http://hub.test`

Infra híbrida:

* app no host
* postgres / redis / nginx via docker

### Tunnel Dev

`https://dev.raphael-martins.com`

Para webhooks e testes externos (mesmo stack em termos de comportamento; URL pública via tunnel).

---

## Roadmap

### Curto Prazo

* webhook estável
* scraping confiável
* lembretes automáticos
* painel útil no dia a dia
* threads-classificados: Playwright autenticado + contrato HTTP (`/threads/auth/login`, `/threads/scrape-url`, `/threads/scrape-keyword`)

### Threads Classificados (status atual)

- Fase 0 concluída no serviço `playwright/`: login com sessão persistida (`storageState`), scrape por URL e por keyword.
- Modo keyword otimizado para descoberta de posts (`include_comments=false` por padrão), com dedupe opcional no scraper (`known_post_ids`, `only_new`, `known_streak_stop`).
- Fase 1 concluída no Laravel: schema `threads_*` (`threads_sources`, `threads_posts`, `threads_comments`, `threads_comment_votes`, `threads_categories`), models e seed inicial de categorias.
- Fase 2 concluída no Laravel: contrato mockavel `ThreadsScraperClientInterface` com implementação HTTP (`ThreadsPlaywrightService`) e fake (`FakeThreadsScraperClient`) para testes sem acoplamento ao container Node.
- Cobertura de regressao da integracao em `tests/Feature/Threads/ThreadsScraperClientTest.php`.
- Fase 3.1 concluida no Laravel: jobs base `ScrapeThreadsUrlJob` e `ScrapeThreadsKeywordJob` (fila `scraping`) com ingestao idempotente para `threads_posts`/`threads_comments` via dedupe por `external_id`.
- Proximo passo: Fase 3.2 (classificacao IA + threshold de relevancia + fila `ai`).

### Médio Prazo

* inbox pessoal inteligente
* OCR
* transcrição de áudio
* busca semântica
* RAG pessoal

### Longo Prazo

* ERP da vida pessoal
* despesas
* tarefas familiares
* documentos
* agenda doméstica
* automações amplas

---

## Filosofia

Este projeto resolve problemas reais enquanto serve como laboratório prático para:

* scraping real
* PostgreSQL avançado
* IA aplicada
* arquitetura moderna
* operação em produção

Hoje é um hub pessoal.

Amanhã pode ser um sistema operacional da vida real.

---

## Autor

Raphael Martins
Software Engineer / Builder / Automation First
