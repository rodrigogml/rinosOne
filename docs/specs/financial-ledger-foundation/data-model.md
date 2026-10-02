# Modelo de Dados — Fundação de Razão Financeira

Todas as entidades abaixo pertencem ao schema do tenant ativo. PKs e FKs usam `BIGINT UNSIGNED`; tabelas usam `createdAt` e `updatedAt` quando houver estado mutável.

## Entidade: `financialAccount`

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade da conta no tenant. |
| `name` | VARCHAR(120) | obrigatório | Nome operacional. |
| `nature` | ENUM | `BANK`, `CASH`, `CREDIT_CARD`, `OTHER` | Cartão é apenas natureza nesta fundação. |
| `currencyCode` | CHAR(3) | `BRL`, `USD` ou `EUR` | Uma única moeda base; sem conversão. |
| `state` | ENUM | `ACTIVE`, `INACTIVE`, `CLOSED` | Controla novos fatos ordinários. |
| `notes` | VARCHAR(1.000) | opcional | Informação operacional não sensível. |
| `version` | INT UNSIGNED | obrigatório | Concorrência otimista para alterações mutáveis. |

Uma conta não possui coluna de saldo. Sua exclusão física é proibida quando houver fatos; transições de estado preservam a história.

### Transições

```text
ACTIVE -> INACTIVE -> ACTIVE
ACTIVE -> CLOSED
```

`CLOSED` não retorna a estado anterior nesta capacidade. Uma conta inativa ou fechada não aceita novos fatos manuais.

## Entidade: `financialAccountBankIdentity`

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade técnica. |
| `idFinancialAccount` | BIGINT UNSIGNED | único, FK | Relação 1:0..1 com a conta. |
| `idFinancialInstitution` | BIGINT UNSIGNED | FK core, opcional | Catálogo global; `ON DELETE SET NULL`. |
| `accountType` | ENUM | `CHECKING`, `SAVINGS`, `INVESTMENT`, `PAYMENT`, `OTHER` | Natureza bancária, distinta da natureza financeira. |
| `agency` / `agencyDigit` | VARCHAR(32) | opcionais | Mantidos como texto. |
| `accountNumber` / `accountDigit` | VARCHAR(64) | opcionais | Mantidos como texto, nunca número. |

Identificadores bancários só são projetados a atores com capacidade própria; listagens e auditoria usam forma mascarada ou não os expõem.

## Entidade: `financialFact`

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade do fato. |
| `idFinancialAccount` | BIGINT UNSIGNED | FK | Cada fato afeta uma única conta. |
| `idCounterpartyPerson` | BIGINT UNSIGNED | FK opcional | Pessoa do mesmo tenant; `ON DELETE SET NULL`. |
| `factKind` | ENUM | `OPENING`, `MANUAL`, `REVERSAL`, `ADJUSTMENT` | Semântica do efeito. |
| `state` | ENUM | `DRAFT`, `CONFIRMED`, `CANCELLED` | Apenas confirmado afeta saldo. |
| `direction` | ENUM | `INFLOW`, `OUTFLOW` | Valor é sempre absoluto e positivo. |
| `amount` | DECIMAL(19,2) | `> 0` | Escala aprovada para BRL, USD e EUR. |
| `effectiveDate` | DATE | obrigatório | Data econômica; diferente de criação/confirmação. |
| `description` | VARCHAR(500) | obrigatório | Explicação do lançamento. |
| `sourceType` / `sourceReference` | VARCHAR | tipo obrigatório; referência opcional | Origem de negócio ou `MANUAL`; sem FK antecipada. |
| `idReversalOfFinancialFact` | BIGINT UNSIGNED | FK opcional, único | Original compensado por esta reversão. |
| `idCorrectionOfFinancialFact` | BIGINT UNSIGNED | FK opcional | Fato cujo conteúdo econômico foi corrigido. |
| `confirmedAt` / `idConfirmedByUser` | DATETIME(6) / BIGINT | nulos até confirmação | Ator global com `ON DELETE SET NULL`. |
| `version` | INT UNSIGNED | obrigatório | Concorrência do rascunho. |

### Transições

```text
DRAFT -> CONFIRMED
DRAFT -> CANCELLED
CONFIRMED -> imutável
```

Uma reversão é novo fato confirmado de direção oposta e mesmo valor do original. Uma correção usa a sequência “reverter original, criar novo fato relacionado”; não há edição econômica in-place. A migration cria uma chave derivada única para garantir no máximo uma abertura confirmada por conta.

## Entidade: `financialControl`

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| `id` | TINYINT UNSIGNED | PK fixa `1` | Singleton por schema de tenant. |
| `closedThroughDate` | DATE | opcional | Maior data econômica protegida. |
| `version` | INT UNSIGNED | obrigatório | Concorrência nas mudanças de fechamento. |

O escritor bloqueia esta linha na mesma transação da confirmação/reversão e recusa efeito com `effectiveDate <= closedThroughDate`.

## Entidade: `financialAuditEvent`

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Evento imutável. |
| `targetType` / `targetId` | VARCHAR / BIGINT | obrigatórios | Conta, fato ou controle financeiro. |
| `action` | ENUM | obrigatório | `ACCOUNT_CREATED`, `FACT_CONFIRMED`, `FACT_REVERSED`, `PERIOD_CLOSED`, `PERIOD_REOPENED` e ações de ciclo de vida. |
| `origin` | ENUM | obrigatório | `WEB`, `API` ou integração futura autorizada. |
| `idActorUser` | BIGINT UNSIGNED | FK core opcional | `ON DELETE SET NULL`. |
| `occurredAt` | DATETIME(6) | obrigatório | Instante do evento. |
| `correlationId` | VARCHAR(128) | opcional, indexado | Relaciona comando, idempotência e logs. |
| `justification` | VARCHAR(500) | obrigatória apenas na reabertura | Não replica números de conta nem valores. |

## Relações e índices

- `financialAccount` 1:0..1 `financialAccountBankIdentity`.
- `financialAccount` 1:N `financialFact`.
- `Person` 1:N `financialFact` como contraparte opcional. O inspetor de uso de exclusão de Pessoas bloqueia a exclusão ordinária de contraparte referenciada; `ON DELETE SET NULL` protege a história em remoção excepcional.
- `financialFact` autorreferencia reversão e correção.
- `financialControl` 1:N `financialAuditEvent` por alvo lógico.
- `financialFact` terá índice de saldo por `idFinancialAccount`, `state`, `effectiveDate`, `id`; o extrato acrescenta `confirmedAt` na ordenação.
- `financialAuditEvent` terá índices por alvo, `occurredAt` e `correlationId`.
