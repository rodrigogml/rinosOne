# Validação e Rastreabilidade — Autorização por Recurso

## Requisitos funcionais

| Requisito | Evidência automatizada principal |
| --- | --- |
| FR-RA-001 | `ResourceAuthorizationApiTest`, `ResourceAuthorizationController` |
| FR-RA-002 / 012 | `AuthorizationResourceRelationServiceTest` |
| FR-RA-003 / 005 | `WorkspaceFolderAuthorizationResourceAdapterTest` |
| FR-RA-004 / 006 / 008 / 009 | `AuthorizationResourceRelationServiceTest` |
| FR-RA-007 | `AuthorizationServiceTest`, `TenantApiTest` |
| FR-RA-010 / 013 | `ResourceAuthorizationApiTest` |
| FR-RA-011 | `ResourceAuthorizationApiTest::test_it_returns_resource_decisions_in_the_submitted_order_without_exposing_an_unshared_folder` |
| FR-RA-014 / 015 | revisão de escopo em `spec.md`, sem endpoints de delegação, condições contextuais ou persistência de decisão |
| FR-RA-016 | `AuthorizationResourceRelationServiceTest::test_workspace_principal_has_baseline_access_without_a_relation` |

## Critérios de sucesso

| Critério | Evidência |
| --- | --- |
| SC-RA-001 | relações READ/EDIT, herança e isolamento em `AuthorizationResourceRelationServiceTest` |
| SC-RA-002 | contexto incompatível de tenant negado em `WorkspaceFolderAuthorizationResourceAdapterTest` |
| SC-RA-003 | desativação/remove da relation e rechecagem de UI em `AuthorizationResourceRelationServiceTest` e `application.spec.ts` |
| SC-RA-004 | consulta agregada, paginação e anti-IDOR em `ResourceAuthorizationApiTest` |
| SC-RA-005 | auditoria de criação, atualização, desativação e remoção em `AuthorizationResourceRelationServiceTest` |

## Interfaces

- `INT-WEB-RESOURCE-001`: parser TypeScript e estados em `authorizedFoldersApi.spec.ts` e `WorkspaceFoldersSurface.spec.ts`.
- `INT-WEB-RESOURCE-002`: rechecagem e revogação em `WorkspaceFoldersSurface.spec.ts`; E2E responsivo em `application.spec.ts`.

## Gates executados

- `php artisan test`: aprovado, 989 asserções; as integrações Mailpit permanecem ignoradas quando a configuração não está presente.
- `npm test`: 112 aprovados em 30 arquivos.
- `npm run type-check` e `npm run build`: aprovados.
- `php vendor/bin/pint --test`: aprovado em todo o repositório.
- `npm run test:e2e`, com Chrome local: 19 cenários aprovados. Os E2E entregam o bundle de produção por servidor estático e simulam explicitamente os contratos HTTP, evitando acoplamento ao bootstrap dinâmico local.

> [!IMPORTANT]
> O core usa `resourceId` como referência lógica. As FKs de `auth_resource_relation` apontam somente para catálogos globais (`auth_resource_type`, `user`, `auth_group` e `tenant`); não existe FK para tabela de schema específico de tenant.
