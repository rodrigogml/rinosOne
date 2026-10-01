# Modelo de Dados — Serviço Indicadores Econômicos

## Entidade: `economicIndicatorSeries`

Define uma série global aprovada.

| Campo | Regra |
| --- | --- |
| `id` | BIGINT técnico. |
| `code` | Único e estável; identifica série ou cotação. |
| `kind` | `INDEX` ou `PTAX`. |
| `sourceKey` / `sourceSeriesCode` | Identifica fonte e recurso oficial. |
| `periodicity` / `unit` | Declaram frequência e semântica do valor. |
| `accumulationMode` | `COMPOUND_PUBLISHED_RATE`, `NO_GLOBAL_ACCUMULATION` ou `NOT_APPLICABLE`; impede que uma regra genérica seja inferida só pela periodicidade. |
| `firstReferenceDate` | Primeira data efetivamente retornada e persistida pela fonte; enquanto nula, a série ainda exige carga histórica inicial. |
| `active` | Controla disponibilidade para novos consumidores. |

## Entidade: `economicIndicatorObservation`

Valor oficial de um indicador em determinada data, preservando revisões.

| Campo | Regra |
| --- | --- |
| `idEconomicIndicatorSeries` | FK obrigatória para a série global. |
| `referenceDate` | Data econômica observada. |
| `value` | Decimal exato publicado. |
| `accumulatedValue` | Projeção materializada `DECIMAL(38,18)` somente para a revisão vigente de séries `COMPOUND_PUBLISHED_RATE`; nula para versões históricas e séries sem acumulado global. |
| `sourceIdentity` | Identidade idempotente da publicação. |
| `revision` / `current` | Uma revisão vigente por série e data. |
| `publishedAt` / `capturedAt` | Contexto temporal da fonte e da carga. |

## Entidade: `economicPtaxQuote`

Cotação PTAX de fechamento para USD ou EUR contra BRL.

| Campo | Regra |
| --- | --- |
| `idEconomicIndicatorSeries` | FK para a série PTAX da moeda. |
| `referenceDate` / `quotedAt` | Data e horário do boletim de fechamento. |
| `buyRate` / `sellRate` | Valores oficiais exatos. |
| `midRate` | Valor derivado, identificado como tal. |
| `sourceIdentity`, `revision`, `current` | Mesma política de rastreabilidade das observações. |

O histórico de execuções e os registros administrativos do Hub de Manutenções são a fonte de diagnóstico operacional. Não há uma tabela paralela de estado: a idempotência é determinada pela identidade da fonte, pela chave de vigência e pelas restrições das tabelas de valores.

## Detalhe de execução de manutenção

Cada histórico da rotina conserva, no payload técnico seguro, um resultado por série: `sourceKey`, intervalo `from`/`through`, contagens de criação, revisão e recomposição de acumulado. Se a fonte falhar antes da persistência, o mesmo detalhe identifica a série e o intervalo que falharam, sem reter resposta bruta, certificado ou credenciais.

## Invariantes

- Não existe referência global para entidades de tenant.
- Repetição da mesma identidade de origem não cria nova versão.
- Revisão substitui a vigência, não apaga a observação anterior.
- PTAX preserva compra e venda; mediana não substitui dado oficial.
- O acumulado é sempre derivado da sequência inteira de `value` das observações vigentes; ele nunca é uma entrada de recomposição.
