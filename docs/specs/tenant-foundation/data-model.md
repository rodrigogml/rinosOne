# Modelo de Dados: Fundação de Tenants

Todos os registros deste documento pertencem ao schema principal `rinosone`. Os dados de domínio futuro pertencem ao schema isolado de cada tenant; nesta feature ele contém somente o catálogo independente de migrations.

## Entidade: `tenant`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identidade estável do tenant. |
| `displayName` | VARCHAR(120) | obrigatório | Nome visível, livre para alteração futura. |
| `state` | VARCHAR(16) | obrigatório | `PROVISIONING`, `ACTIVE`, `INACTIVE` ou `FAILED`. |
| `createdAt` | TIMESTAMP | obrigatório | Instante de criação. |
| `updatedAt` | TIMESTAMP | obrigatório | Última alteração global. |

O nome físico é derivado exclusivamente de `id`: `rinosone_{id}`. Ele não é persistido, não recebe entrada da interface e não pode ser reutilizado.

### Transições de Estado

```text
PROVISIONING -> ACTIVE
PROVISIONING -> FAILED
FAILED -> PROVISIONING       (nova tentativa autorizada)
ACTIVE -> INACTIVE           (desabilitação pelo proprietário)
INACTIVE -> ACTIVE           (reativação pelo proprietário)
```

Somente `ACTIVE` pode estabelecer novo contexto operacional.

## Entidade: `tenantMembership`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identidade do vínculo. |
| `idTenant` | BIGINT UNSIGNED | FK obrigatória para `tenant.id` | Tenant a que o vínculo pertence. |
| `idUser` | BIGINT UNSIGNED | FK obrigatória para `user.id` | Pessoa associada. |
| `role` | VARCHAR(16) | obrigatório, valor inicial `OWNER` | Reservado à evolução de acesso. |
| `state` | VARCHAR(16) | obrigatório, valor inicial `ACTIVE` | Impede uso contextual quando não ativo. |
| `lastContextSelectedAt` | TIMESTAMP | nulo, indexado com usuário e estado | Última seleção contextual concluída; ordena a lista pessoal de organizações, sem restaurar contexto em uma aba. |
| `createdAt` | TIMESTAMP | obrigatório | Instante de associação. |
| `updatedAt` | TIMESTAMP | obrigatório | Última alteração do vínculo. |

Há unicidade em `(idTenant, idUser)`. Nesta fase somente a associação `OWNER` do criador é inserida; a estrutura não autoriza tela, convite ou operação de gestão de membros.

## Entidade: `tenantProvisioning`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identidade da operação de preparação. |
| `idTenant` | BIGINT UNSIGNED | FK obrigatória, única | Um registro de preparação por tenant. |
| `idRequestedByUser` | BIGINT UNSIGNED | FK obrigatória para `user.id` | Criador que iniciou a operação. |
| `idempotencyKey` | CHAR(36) | obrigatório | UUID v4 da intenção fornecida pela interface; não é identidade da entidade. |
| `state` | VARCHAR(16) | obrigatório | `QUEUED`, `RUNNING`, `SUCCEEDED` ou `FAILED`. |
| `attemptCount` | UNSIGNED TINYINT | obrigatório, padrão 0, máximo configurável 3 | Tentativas iniciadas. |
| `lastFailureCode` | VARCHAR(100) | nulo | Código seguro, sem SQL, segredo ou host. |
| `completedAt` | TIMESTAMP | nulo | Preenchido ao sucesso terminal. |
| `createdAt` | TIMESTAMP | obrigatório | Instante do pedido. |
| `updatedAt` | TIMESTAMP | obrigatório | Última alteração operacional. |

Há unicidade em `(idRequestedByUser, idempotencyKey)`. Uma repetição devolve a mesma operação e o mesmo tenant, nunca cria novo schema.

### Transições de Estado

```text
QUEUED -> RUNNING -> SUCCEEDED
QUEUED -> RUNNING -> FAILED
FAILED -> QUEUED              (retentativa transitória controlada)
```

## Relacionamentos e limites

```text
user 1 -- N tenantMembership N -- 1 tenant 1 -- 1 tenantProvisioning
```

- `tenantMembership` e `tenantProvisioning` são consultados no schema global antes de qualquer acesso ao tenant.
- O schema de tenant não contém cópia de `user`, `tenant`, `tenantMembership` nem de credenciais.
- Uma tabela de tenant pode referenciar uma tabela global por `BIGINT UNSIGNED`; a direção inversa é proibida.
- FKs tenant para core usam `ON UPDATE CASCADE` e `ON DELETE CASCADE` para vínculos obrigatórios, ou `ON DELETE SET NULL` para vínculos opcionais. `RESTRICT` e `NO ACTION` são proibidos.
- Novos schemas usam `utf8mb4` e `utf8mb4_unicode_ci`; todas as tabelas futuras usam InnoDB.
- A política padrão permite três tentativas com intervalos de 1, 5 e 15 minutos; falhas não transitórias são terminais.
