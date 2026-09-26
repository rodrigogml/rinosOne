# Topologia de Dados Multi-Tenant

**Criado**: 2026-09-22  
**Status**: Aprovado

## Convenção de Schemas

| Escopo | Padrão | Exemplo | Finalidade |
| --- | --- | --- | --- |
| Principal | `rinosone` | `rinosone` | Identidade, acesso e metadados globais da plataforma. |
| Tenant | `rinosone_{tenantId}` | `rinosone_42` | Dados pertencentes a uma única organização e aos módulos contratados por ela. |

`tenantId` é a PK numérica `BIGINT UNSIGNED` estável do tenant. O nome da empresa não integra o schema: pode mudar sem renomear dados ou afetar integrações. O schema deve usar `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`.

## Limites de Responsabilidade

O schema `rinosone` é a autoridade de usuários, autenticação, cadastro de tenants, associações e estado de
provisionamento. A fundação de tenants é especificada em
[tenant-foundation](../specs/tenant-foundation/spec.md); seus dados de controle pertencem exclusivamente a este schema.

Cada schema de tenant contém exclusivamente seus dados de domínio. Referências entre schemas seguem uma direção única: uma tabela de tenant pode possuir FK para uma tabela do schema principal; o schema principal nunca possui FK para uma tabela de tenant. A FK deve usar `BIGINT UNSIGNED`, declarar `ON UPDATE CASCADE` e declarar `ON DELETE CASCADE` quando o filho não puder existir sem o pai, ou `ON DELETE SET NULL` quando o vínculo for opcional. `RESTRICT` e `NO ACTION` não são aceitos. Dados globais referenciados não devem ser excluídos fisicamente como operação ordinária; a inativação preserva histórico e evita cascatas não planejadas.

## Migrations

As migrations futuras serão separadas por escopo:

```text
database/migrations/core/    # executadas somente em rinosone
database/migrations/tenant/  # executadas uma vez para cada rinosone_{tenantId}
```

Cada schema conserva seu próprio controle de migrations. O worker de provisionamento cria o schema, aplica integralmente as migrations de tenant e confirma o histórico esperado antes de disponibilizá-lo para uso. O dispatch desse worker ocorre junto ao fluxo de criação do tenant.

> [!IMPORTANT]
> A fundação de tenants autoriza a criação do schema, sua preparação, a membership inicial e a atribuição administrativa inicial. Ela não
> autoriza módulos de negócio, convites, gestão de membros, papéis detalhados ou relações entre dados de domínio.

## Configuração por Ambiente

O modelo de configuração versionado deverá expor, sem valores sensíveis:

```dotenv
RINOS_CORE_DATABASE=rinosone
RINOS_TENANT_DATABASE_PREFIX=rinosone_
CORE_MIGRATION_HOST=127.0.0.1
CORE_MIGRATION_PORT=3306
CORE_MIGRATION_DATABASE=rinosone
CORE_MIGRATION_USERNAME=change-me
CORE_MIGRATION_PASSWORD=change-me
```

As credenciais de runtime global, migration global, runtime de tenant e provisionamento de schemas são distintas. O usuário normal da aplicação não recebe DDL; `migrate:global` usa exclusivamente a conexão `coreMigration`, limitada ao schema principal. Os privilégios mínimos de cada conexão estão em [tenant-provisioning.md](../operations/tenant-provisioning.md).
