# Topologia de Dados Multi-Tenant

**Criado**: 2026-09-22  
**Status**: Aprovado

## Convenção de Schemas

| Escopo | Padrão | Exemplo | Finalidade |
| --- | --- | --- | --- |
| Principal | `rinosone` | `rinosone` | Identidade, acesso e metadados globais da plataforma. |
| Tenant | `rinosone_{tenantId}` | `rinosone_01j7m2x4p9k6v3q8r5s0t1u2w3` | Dados pertencentes a uma única organização e aos módulos contratados por ela. |

`tenantId` é um ULID estável, convertido para minúsculas. O nome da empresa não integra o schema: pode mudar sem renomear dados ou afetar integrações. O schema deve usar `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`.

## Limites de Responsabilidade

O schema `rinosone` é a autoridade de usuários, autenticação e, quando o escopo for aprovado, do cadastro de tenants e de suas associações. A primeira fase usa somente esse schema.

Cada schema de tenant contém exclusivamente seus dados de domínio. Não há chave estrangeira entre schemas nesta fase. Quando uma necessidade futura exigir referência do tenant ao principal, ela será avaliada como uma exceção explícita: a dependência poderá apontar somente do tenant para o principal, jamais no sentido inverso.

## Migrations

As migrations futuras serão separadas por escopo:

```text
database/migrations/core/    # executadas somente em rinosone
database/migrations/tenant/  # executadas uma vez para cada rinosone_{tenantId}
```

Cada schema conserva seu próprio controle de migrations. O provisionamento de um tenant, quando aprovado, deverá criar o schema, aplicar integralmente as migrations de tenant e somente então disponibilizá-lo para uso.

> [!IMPORTANT]
> Esta decisão não autoriza ainda a criação de tenants, schemas de tenant, módulos, tabelas de associação ou processos de provisionamento. Ela apenas estabelece a convenção que essas capacidades deverão seguir.

## Configuração por Ambiente

O modelo de configuração versionado deverá expor, sem valores sensíveis:

```dotenv
RINOS_CORE_DATABASE=rinosone
RINOS_TENANT_DATABASE_PREFIX=rinosone_
```

Credenciais de execução da aplicação e de provisionamento de schemas serão separadas quando o provisionamento for implementado. O usuário normal da aplicação não recebe permissão ampla para criar schemas.
