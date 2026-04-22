# Raphael HUB

> Seu número de WhatsApp como painel de controle da vida real.

## Visão Geral

**Raphael HUB** é uma plataforma pessoal de automação doméstica, produtividade e organização digital baseada em **WhatsApp + Dashboard Web**.

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
  n- mandar imagens
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

Classificação automática de mensagens, intenção e contexto.

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

### Storage

* MinIO (S3 compatível)

### Scraping

* Node.js 24
* Playwright

### WhatsApp

* Evolution API

### Infra

* Docker
* GitHub Actions
* VPS Linux
* Cloudflare Tunnel

---

## Camada de IA (Orquestrada)

O projeto utiliza **NeuronAI** (PHP) como camada de abstração entre modelos.

Isso permite trocar provedores sem reescrever prompts, tools ou fluxos.

### Estratégia atual

1. **Ollama local na VPS**

   * análises rápidas
   * sentimento
   * tarefas simples
   * custo zero por uso

2. **Groq**

   * alta velocidade
   * tarefas intermediárias

3. **Execução paralela / redundância opcional**

   * Ollama + Groq quando fizer sentido

4. **Fallback premium**

   * OpenAI
   * Anthropic Claude

Objetivo: custo baixo, velocidade alta e qualidade quando necessário.

---

## Ambientes

### Produção

`https://api.raphael-martins.com`

Deploy automatizado via GitHub Actions.

### Desenvolvimento Local

`http://hub.test`

Infra híbrida:

* app no host
* postgres / redis / nginx via docker

### Tunnel Dev

`https://dev.raphael-martins.com`

Para webhooks e testes externos.

---

## Roadmap

### Curto Prazo

* webhook estável
* scraping confiável
* lembretes automáticos
* painel útil no dia a dia

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
