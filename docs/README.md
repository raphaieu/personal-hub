# Documentação — Raphael Hub

Hierarquia desta pasta:

```
docs/
├── core/SPEC.md          # banco transversal, IA, dashboard, autenticação
├── whatsapp/SPEC.md      # webhook, message_logs, jobs de análise
├── utilities/SPEC.md     # Embasa/Coelba, scraping, schedule
├── threads/SPEC.md       # scraping, classificação, hub, /oportunidades
├── events/SPEC.md        # eventos privados, ingresso PDF/QR, portaria
├── album/SPEC.md         # álbuns, viewer público, contribuição externa
├── operations/SPEC.md    # Docker, deploy, Playwright, limites Nginx/PHP, dev local
└── roadmap/BACKLOG.md    # backlog de evolução (renomeado de v2.md)
```

## Onde escrever o quê

| Tipo de conteúdo | Lugar |
|------------------|-------|
| Contrato técnico estável (schema, endpoint, job) | SPEC do módulo correspondente |
| Schema transversal usado por vários módulos | `docs/core/SPEC.md` |
| Detalhe operacional efêmero (status de fase, IP, "estamos na fase 5.2") | **não escrever** — usar CHANGELOG na raiz |
| Intenção, prioridade, dependências de algo futuro | `docs/roadmap/BACKLOG.md` |
| Convenção de código para agentes de IA | `LLM.md` na raiz |
| Visão de produto, módulos atuais, métricas | `PRD.md` na raiz |
| Histórico datado de mudanças | `CHANGELOG.md` na raiz |
| Resumo do projeto + instruções de rodar local | `README.md` na raiz |

## Regra geral

- **SPEC.md** (raiz) = índice + stack + ponteiros. Não duplica conteúdo das SPECs por módulo.
- **SPECs por módulo** = contrato técnico canônico daquele domínio.
- **CHANGELOG** = histórico datado.
- **BACKLOG** = só o que ainda não foi feito.

Quando um item do backlog entra em produção: mover para a SPEC do módulo (contrato) e registrar no CHANGELOG (data). Apagar do BACKLOG.
