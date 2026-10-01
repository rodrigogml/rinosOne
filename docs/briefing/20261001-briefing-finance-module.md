# Briefing — Módulo Financeiro

**Data**: 2026-10-01
**Status**: Em especificação
**Fonte**: requisitos funcionais consolidados fornecidos para o módulo Financeiro

## Objetivo

Estabelecer o Financeiro como domínio nativo para controlar contas, obrigações, direitos, fatos monetários e evidências bancárias de cada organização. O módulo deve preservar a diferença entre previsão, documento e fato financeiro efetivado: apenas fatos confirmados compõem o saldo.

## Decisões de fronteira

- Dados financeiros operacionais pertencem ao tenant; o Serviço de Indicadores Econômicos e o catálogo de instituições financeiras pertencem ao core global autorizado.
- Pessoas existentes são contrapartes. Dados bancários de uma Pessoa não são contas financeiras da organização.
- Saldo é derivado de abertura e fatos confirmados; correções preservam a história econômica.
- O Financeiro reutiliza autorização TENANT, contratos de API, idempotência, arquivos privados e catálogo global de instituições já definidos pela plataforma.
- O documento-mestre define o MVP como um domínio coerente, mas a documentação e a entrega serão particionadas por capacidades independentes, com dependências explícitas.

## Mapa de SDDs proposto

| Ordem | Capacidade / SDD | Responsabilidade | Dependências principais |
| ---: | --- | --- | --- |
| 1 | `economic-indicators` | Serviço global de indicadores econômicos: SELIC, IPCA, IGP-M, INCC, remuneração da poupança e PTAX de USD e EUR. | Fundação multi-tenant e operação de catálogos. |
| 2 | `financial-ledger-foundation` | Contas, abertura, fatos monetários, reversões, fechamento, auditoria e controles monetários. | Pessoas, autorização, arquivos privados, instituições financeiras. |
| 3 | `financial-classification` | Categorias, dimensões e rateios usados pelos fatos e documentos. | Fundação financeira. |
| 4 | `finance-currency-and-valuation` | Operações entre moedas, seleção de cotação, arredondamento e diferenças cambiais. | Serviço de Indicadores Econômicos e fundação financeira. |
| 5 | `finance-payables` | Obrigações, parcelas, pagamentos e liquidações. | Fundação, classificação e moeda/câmbio quando aplicável. |
| 6 | `finance-receivables` | Direitos, parcelas, recebimentos e cobrança operacional. | Fundação, classificação e moeda/câmbio quando aplicável. |
| 7 | `finance-transfers-recurrences` | Transferências internas e previsões recorrentes. | Fundação; classificação e moeda/câmbio quando aplicável. |
| 8 | `finance-bank-reconciliation` | Extratos OFX/CSV, sugestões, regras e conciliação. | Fundação, arquivos privados e classificação. |
| 9 | `finance-payment-files` | Importação de títulos, lotes, remessas e retornos. | Contas a pagar, arquivos privados e fundação. |
| 10 | `finance-operational-insights` | Dashboard, fluxo de caixa e relatórios operacionais. | Capacidades que produzem os dados consultados. |

Cada SDD explicitará sua cobertura de interface, contrato e critérios de aceite. Nenhum SDD criará uma cópia de fundação já existente nem antecipará as capacidades corporativas futuras do documento-mestre.

## Decisões em aberto

1. **Arquivos bancários**: o núcleo CNAB será comum; BTG e Itaú terão especializações a partir dos layouts exatos que serão fornecidos. Outros bancos usam o núcleo comum enquanto não houver layout especializado aprovado.
2. **Cartão de crédito**: no MVP inicial é somente uma natureza de conta financeira. Fatura, fechamento, vencimento, pagamento e conciliação próprios são escopo de um futuro `finance-credit-card-cycle`.
3. **Câmbio**: conversão, cotação e diferença cambial dependem primeiro de `economic-indicators`. Essa capacidade será especificada antes do SDD de moeda/câmbio que estenderá a fundação financeira.

> [!IMPORTANT]
> `economic-indicators` é um serviço do core global. Ele disponibiliza indicadores e PTAX; não cria conversão monetária, reavaliação financeira ou integração bancária direta. Essas decisões pertencem a capacidades posteriores.
