# PRD — Raphael Hub

## Visão Geral

Sistema pessoal de automação doméstica e produtividade via WhatsApp, com dashboard web. O projeto centraliza monitoramento de contas de água e luz, lembretes pessoais, notificações para o grupo familiar, e serve como base evolutiva para outros projetos pessoais integrados ao número WhatsApp do Raphael.

---

## Problema

- O pai (Ildacir) precisa consultar valores de Embasa e Coelba periodicamente e não tem acesso fácil
- As contas vencem em datas fixas mas frequentemente são esquecidas
- Não existe um ponto central de notificação e histórico para a família
- Mensagens enviadas para si mesmo no WhatsApp (lembretes, URLs, imagens) se perdem sem categorização

---

## Solução

Um hub pessoal que:
1. Faz scraping automatizado das contas (Embasa e Coelba) via schedule
2. Notifica o grupo familiar antes do vencimento e cobra enquanto não pagar
3. Responde via WhatsApp quando alguém da família perguntar sobre as contas
4. Captura e categoriza tudo que Raphael manda para si mesmo no WhatsApp
5. Exibe histórico, métricas de consumo e status no dashboard web

---

## Usuários

| Usuário | Como interage |
|---|---|
| Raphael | Dashboard web + mensagens para si mesmo no WhatsApp |
| Ildacir (pai) | WhatsApp — pergunta valores, recebe lembretes |
| Grupo da Casa | Recebe notificações automáticas de vencimento |

---

## Funcionalidades — MVP

### F1 — Webhook WhatsApp (Evolution API)
- Recebe todos os eventos do número pessoal do Raphael
- Filtra por origem: `isFromMe`, contato monitorado, grupo monitorado, ignora o resto
- Loga todas as mensagens recebidas/processadas

### F2 — Mensagens pessoais (isFromMe)
- Texto simples → salva como lembrete, categoriza com AI
- URL → salva como lembrete, enriquece com Open Graph
- Imagem → salva no MinIO, categoriza com AI
- Confirma recebimento com emoji + categoria

### F3 — Scraping Embasa
- Fluxo: login CPF/senha → modal matrícula → `/segunda-via?pay=true` → extrai faturas
- Sem CAPTCHA — Playwright puro
- Extrai: referência, vencimento, consumo m³, valor água, valor esgoto, valor serviço, valor total, status
- Status mapeados: `Aguardando pagamento` → pendente | `Conta Paga ✓` → pago | `Pagamento em processamento bancário` → processando
- Baixa PDF da fatura pendente mais recente

### F4 — Scraping Coelba (Neoenergia)
- Fluxo: login → modal CPF/senha → reCAPTCHA v3 (CapSolver) → selecionar estado Bahia → selecionar unidade consumidora → `/home/servicos/consultar-debitos`
- Angular SPA com hash routing (`#/`)
- Extrai: referência, vencimento, valor fatura, situação, data pagamento
- Status mapeados: `A Vencer` → a_vencer | `Vencida` → vencida | `Pago` → pago
- Baixa PDF da fatura pendente mais recente

### F5 — Schedule de scraping
- Scrape completo: X dias antes do vencimento configurado por conta (padrão: 5 dias)
- Scrape leve de verificação: diário enquanto status != pago
- Ao detectar nova fatura: persiste no banco + faz upload do PDF no MinIO + notifica grupo da casa
- Ao detectar pagamento: atualiza status + para lembretes
- Lembrete recorrente: diário até pagamento, enviado para grupo da casa

### F6 — Respostas via WhatsApp
- Pai pergunta sobre conta → AI classifica intenção → busca no banco (sem scraping em tempo real) → responde com valor, vencimento e PDF
- Resposta formatada: valor em negrito, vencimento, status, link ou arquivo PDF

### F7 — Dashboard Web (Blade + Livewire)
- Autenticação padrão Laravel Breeze
- Contas: status atual, próximo vencimento, valor, histórico
- Gráfico de consumo histórico (Embasa: m³ | Coelba: kWh e R$)
- Lista de lembretes pessoais com filtro por categoria
- Log de mensagens recebidas/enviadas
- Fontes monitoradas: gerenciar números/grupos, permissões
- Horizon: acessível via `/horizon` com proteção por email/IP

---

## Funcionalidades — Pós-MVP

Roadmap detalhado de **monitoramento profundo de grupos**, **transcrição**, **armazenamento por fonte no MinIO** e **dashboard com permissões por grupo** está em [docs/v2.md](docs/v2.md). Atualize esse arquivo quando novas ideias surgirem no desenvolvimento da base.

- RAG sobre histórico de faturas e lembretes (pgvector)
- OCR em imagens recebidas via WhatsApp
- Código PIX copiável enviado junto com o lembrete de vencimento
- Ollama local para classificação sem custo de token
- Novos grupos/números monitorados com suas próprias regras
- Integração com outros projetos pessoais no mesmo número

---

## Requisitos Não Funcionais

- Todos os serviços em Docker na VPS (isolado de outros projetos)
- CI/CD via GitHub Actions com rsync + SSH
- Credenciais das concessionárias apenas no `.env`, nunca no banco
- PDFs armazenados no MinIO (bucket `pessoal`), referência de path no banco
- Logs de scraping detalhados para debug de seletores
- Horizon para monitoramento de filas
- Timezone: `America/Sao_Paulo` em todos os containers

---

## Métricas de Sucesso MVP

- Pai consegue perguntar "quanto ficou a conta de água?" e receber resposta correta
- Grupo da casa recebe lembrete automático antes do vencimento sem intervenção manual
- Dashboard exibe histórico de consumo dos últimos 12 meses
- Raphael consegue mandar URL/texto para si mesmo e encontrar categorizado no dashboard
