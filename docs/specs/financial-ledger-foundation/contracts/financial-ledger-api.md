# Contrato de API — Fundação de Razão Financeira

Base: `/api/v1/tenants/{tenantId}/financial`. Todos os payloads usam `camelCase`, IDs são números e comandos mutáveis exigem `Idempotency-Key`. O middleware resolve tenant, usuário e conexão antes de chamar o domínio.

## Capacidades

| Chave | Finalidade |
| --- | --- |
| `tenant.financial.read` | Consultar contas, saldo e extrato. |
| `tenant.financial.read-bank-identifiers` | Consultar identificadores bancários completos. |
| `tenant.financial.manage-accounts` | Criar e alterar ciclo de vida de contas. |
| `tenant.financial.record-facts` | Criar, alterar rascunho e confirmar fato manual. |
| `tenant.financial.reverse-facts` | Reverter fato confirmado. |
| `tenant.financial.close-period` | Fechar período. |
| `tenant.financial.reopen-period` | Reabrir período com justificativa. |
| `tenant.financial.audit.read` | Consultar auditoria financeira. |

## Contas

### Criar conta

**Method**: `POST /accounts`
**Auth**: `tenant.financial.manage-accounts`

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| `name` | string | sim | 1–120 caracteres. |
| `nature` | enum | sim | `BANK`, `CASH`, `CREDIT_CARD`, `OTHER`. |
| `currencyCode` | string | sim | `BRL`, `USD`, `EUR`. |
| `bankIdentity` | objeto | não | Instituição global válida e identificadores textuais. |

**Response 201**: `{ "account": { "id", "name", "nature", "currencyCode", "state", "version", "bankIdentity" } }`.

### Listar e mudar ciclo de vida

| Método | Autorização | Contrato resumido |
| --- | --- | --- |
| `GET /accounts` | `tenant.financial.read` | Lista contas do tenant; detalhes bancários completos exigem `tenant.financial.read-bank-identifiers`. |
| `PATCH /accounts/{accountId}` | `tenant.financial.manage-accounts` | Altera apenas metadados mutáveis com `expectedVersion`; não altera moeda nem saldo. |
| `POST /accounts/{accountId}/inactivation` | `tenant.financial.manage-accounts` | Inativa conta sem apagar seu histórico. |
| `POST /accounts/{accountId}/reactivation` | `tenant.financial.manage-accounts` | Reativa conta inativa com versão esperada. |
| `POST /accounts/{accountId}/closure` | `tenant.financial.manage-accounts` | Fecha conta; fatos posteriores são recusados. |

### Consultar saldo e extrato

**Methods**: `GET /accounts/{accountId}/balance?through=YYYY-MM-DD` e `GET /accounts/{accountId}/statement?from=&through=`
**Auth**: `tenant.financial.read`

Saldo retorna `currencyCode`, `through`, `openingAmount`, `inflowAmount`, `outflowAmount` e `balanceAmount`; todos os valores são strings decimais de duas casas. Extrato retorna fatos confirmados em ordem estável por data efetiva, confirmação e ID; rascunhos não aparecem.

## Fatos financeiros

### Registrar abertura

**Method**: `POST /accounts/{accountId}/opening`
**Auth**: `tenant.financial.record-facts`

Recebe `direction`, `amount`, `effectiveDate` e `description`; cria e confirma o único fato `OPENING` da conta na mesma transação. A conta não pode ter segunda abertura confirmada.

### Criar ou confirmar um fato

**Methods**: `POST /facts`, `PATCH /facts/{factId}` para rascunho, `POST /facts/{factId}/confirm`
**Auth**: `tenant.financial.record-facts`

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| `accountId` | number | sim | Conta ativa, do tenant e compatível. |
| `direction` | enum | sim | `INFLOW` ou `OUTFLOW`. |
| `amount` | string decimal | sim | Positivo, escala máxima 2. |
| `effectiveDate` | date | sim | Não protegido por fechamento. |
| `description` | string | sim | 1–500 caracteres. |
| `counterpartyPersonId` | number | não | Pessoa do mesmo tenant. |
| `expectedVersion` | number | em alteração/confirmação | Versão corrente do rascunho. |

**Response 200/201**: `{ "fact": { "id", "state", "factKind", "direction", "amount", "effectiveDate", "accountId", "version", "confirmedAt" } }`.

### Reverter

**Method**: `POST /facts/{factId}/reversal`
**Auth**: `tenant.financial.reverse-facts`

O comando cria uma reversão vinculada e não altera o original. A resposta inclui o fato original e a reversão; repetir a mesma chave de idempotência devolve a mesma resposta.

## Fechamento e auditoria

| Método | Autorização | Contrato resumido |
| --- | --- | --- |
| `GET /control` | `tenant.financial.read` | Data fechada corrente e versão. |
| `POST /closures` | `tenant.financial.close-period` | Recebe `closedThroughDate` e `expectedVersion`; só avança a proteção. |
| `POST /closures/reopen` | `tenant.financial.reopen-period` | Recebe nova data, `expectedVersion` e `justification`; registra auditoria. |
| `GET /audit-events` | `tenant.financial.audit.read` | Retorna eventos paginados e sem identificadores bancários. |

## Erros estáveis

| Status | Código | Condição |
| --- | --- | --- |
| 403 | `FINANCIAL_ACCESS_DENIED` | Capacidade ausente ou tenant não autorizado. |
| 404 | `FINANCIAL_ACCOUNT_NOT_FOUND` / `FINANCIAL_FACT_NOT_FOUND` | Recurso não pertence ao tenant. |
| 409 | `FINANCIAL_VERSION_CONFLICT` | `expectedVersion` está desatualizada. |
| 409 | `FINANCIAL_PERIOD_CLOSED` | Data efetiva está protegida. |
| 409 | `FINANCIAL_OPENING_ALREADY_EXISTS` / `FINANCIAL_FACT_ALREADY_REVERSED` | Invariante econômico impedido. |
| 422 | `FINANCIAL_INVALID_AMOUNT` / `FINANCIAL_INVALID_CURRENCY` | Valor, direção ou moeda inválidos. |
