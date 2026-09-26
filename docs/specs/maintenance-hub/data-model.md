# Modelo de Dados — Central de Manutenções

Todos os dados pertencem ao schema global `rinosone`. Não existe tabela de definição de rotinas: o conjunto de rotinas é conhecido pelo hub por implementação específica.

## Entidade: `maintenanceExecutionHistory`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, auto incremento | Identidade técnica. |
| `routineKey` | VARCHAR(100) | obrigatório, indexado | Identificador estável da integração específica. |
| `triggerType` | VARCHAR(32) | obrigatório | `SCHEDULED`, `MANUAL` ou outro valor definido pela integração. |
| `state` | VARCHAR(32) | obrigatório, indexado | Estado técnico exposto pela rotina. |
| `startedAt` | DATETIME(6) | obrigatório | Início observado pelo hub. |
| `completedAt` | DATETIME(6) | opcional | Fim observado, quando houver. |
| `summary` | VARCHAR(500) | opcional | Resultado seguro para visualização. |
| `details` | JSON | opcional | Contexto seguro e específico da rotina. |
| `expiresAt` | DATETIME(6) | obrigatório, indexado | Limite de retenção técnica calculado no registro. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatório | Auditoria temporal local. |

## Entidade: `maintenanceAdministrativeAudit`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, auto incremento | Identidade técnica. |
| `idPerformedByUser` | BIGINT UNSIGNED | FK obrigatória para `user` | Administrador que solicitou a ação. |
| `routineKey` | VARCHAR(100) | obrigatório, indexado | Rotina alvo. |
| `action` | VARCHAR(64) | obrigatório | Ação solicitada. |
| `outcome` | VARCHAR(32) | obrigatório | Resultado de aceitação, recusa ou falha de solicitação. |
| `parameters` | JSON | opcional | Apenas parâmetros seguros e permitidos. |
| `occurredAt` | DATETIME(6) | obrigatório | Instante da ação. |
| `expiresAt` | DATETIME(6) | obrigatório, indexado | Limite de retenção de auditoria calculado no registro. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatório | Auditoria temporal local. |

### Relações e retenção

- As duas entidades são globais. Nenhuma referencia dados de tenant.
- `maintenanceAdministrativeAudit.idPerformedByUser` aponta para `user.id` com `ON UPDATE CASCADE` e `ON DELETE CASCADE`.
- `routineKey` não possui FK, pois não haverá tabela genérica de rotinas.
- Os valores padrão de `expiresAt` derivam de configurações independentes para histórico técnico e auditoria; a remoção ocorre somente após a expiração aplicável.
- A auditoria administrativa é somente de inserção e retenção: não admite edição ou remoção manual.
- A primeira integração, `financial-institution-catalog`, não adiciona uma tabela própria de carga: seu resultado seguro será registrado no histórico técnico do hub.
