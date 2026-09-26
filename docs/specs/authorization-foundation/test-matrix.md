# Matriz de Testes — Fundação de Autorização

**Feature:** `authorization-foundation`  
**Atualizada em:** 2026-09-26

Esta matriz liga os requisitos funcionais e critérios de sucesso da fundação às provas automatizadas. Os nomes de testes abaixo são referências de manutenção; a decisão de autorização permanece no backend, e testes de interface validam somente o consumo do contrato de capabilities.

> [!IMPORTANT]
> Uma capability de UX nunca é prova suficiente de autorização. As ações protegidas são cobertas por testes de feature que exercitam a nova decisão no backend.

## Requisitos funcionais

| Requisito | Cobertura automatizada principal | Tipo |
| --- | --- | --- |
| FR-AF-001 — catálogo único de permissions | `AuthorizationPermissionCatalogTest::test_tenant_permission_is_added_to_the_system_administrator_role` e `AuthorizationRolePermissionServiceTest` | Feature/persistência |
| FR-AF-002 — esfera única por permission | `AuthorizationPermissionCatalogTest::test_existing_permission_cannot_be_registered_in_a_different_scope` | Feature/persistência |
| FR-AF-003 — default deny | `AuthorizationServiceTest::test_it_allows_a_platform_grant_without_tenant_context_and_defaults_to_deny_for_personal` | Feature |
| FR-AF-004 — PLATFORM sem tenant | `AuthorizationServiceTest::test_it_allows_a_platform_grant_without_tenant_context_and_defaults_to_deny_for_personal` | Feature |
| FR-AF-005 — PERSONAL sem tenant | `AuthorizationServiceTest::test_it_denies_tenant_context_for_a_personal_decision` | Feature |
| FR-AF-006 — TENANT explícito, ativo e com membership ativa | `AuthorizationServiceTest::test_it_allows_a_tenant_grant_only_in_its_active_tenant`; `TenantApiTest::test_context_is_available_only_for_an_active_membership_and_tenant` | Feature/API |
| FR-AF-007 — roles reutilizáveis por esfera | `AuthorizationRolePermissionServiceTest` | Feature/persistência |
| FR-AF-008 — compatibilidade entre role e permission | `AuthorizationRolePermissionServiceTest::test_it_rejects_a_permission_from_another_scope`; `AuthorizationRoleAssignmentServiceTest::test_it_rejects_a_tenant_owned_role_outside_its_tenant`; `AuthorizationGroupRoleAssignmentTest::test_it_rejects_a_tenant_owned_role_for_a_group_from_another_tenant` | Feature/persistência |
| FR-AF-009 — grants diretos e para grupos | `AuthorizationRoleAssignmentServiceTest::test_it_assigns_an_active_tenant_role_to_an_active_member`; `AuthorizationGroupRoleAssignmentTest` | Feature |
| FR-AF-010 — grupos compatíveis e sem ciclos | `AuthorizationGroupServiceTest`; `AuthorizationGroupHierarchyTest::test_it_rejects_direct_and_indirect_cycles` | Feature |
| FR-AF-011 — união de grants diretos e de grupos | `AuthorizationGroupRoleAssignmentTest::test_it_unions_active_group_grants_and_revokes_them_on_the_next_decision`; `AuthorizationGroupHierarchyTest::test_a_child_member_inherits_an_ancestors_grant_through_a_multi_level_chain` | Feature |
| FR-AF-012 — membership é elegibilidade, não grant | `TenantApiTest::test_active_member_without_administrator_permission_cannot_change_availability` | API |
| FR-AF-013 — último administrador direto e ativo | `AuthorizationRoleAssignmentServiceTest::test_it_rejects_removing_or_deactivating_the_last_tenant_administrator`; `AuthorizationRoleAssignmentServiceTest::test_it_transfers_administration_before_deactivating_the_previous_administrator`; `TenantMembershipServiceTest::test_it_rejects_deactivation_or_removal_of_the_last_active_direct_administrator` | Feature |
| FR-AF-014 — revogação na próxima operação | `TenantApiTest::test_removing_a_grant_denies_the_next_authenticated_request`; `AuthorizationGroupRoleAssignmentTest`; `AuthorizationGroupHierarchyTest::test_removing_a_hierarchy_link_revokes_the_ancestor_grant_on_the_next_decision`; `TenantMembershipServiceTest::test_it_revokes_direct_permissions_on_deactivation_and_restores_them_on_activation` | API/feature |
| FR-AF-015 — auditoria imutável e segura | `AuthorizationAuditEventTest`; `TenantMembershipServiceTest` | Feature/persistência |
| FR-AF-016 — capabilities mínimas, sem autoridade no cliente | `TenantApiTest::test_context_capabilities_are_projected_again_after_a_grant_is_revoked`; `TenantApiTest::test_tenant_payload_uses_numeric_ids_camel_case_keys_and_boolean_capabilities`; `tenantContextStore.spec.ts`; `TenantManagementDialog.spec.ts` | API/contrato/interface |
| FR-AF-017 — limites de escopo | Revisão de fronteiras em [spec.md](spec.md#requisitos) e [plan.md](plan.md#constitution-check); não há comportamento adicional desta feature a automatizar. | Arquitetura |
| FR-AF-018 — sem decisão persistida na sessão | `TenantApiTest::test_removing_a_grant_denies_the_next_authenticated_request` | API |
| FR-AF-019 — retenção configurável diária | `AuthorizationConfigurationTest`; `AuthorizationAuditRetentionTest` | Feature/comando |
| FR-AF-020 — `tenant.administrator` recebe toda permission TENANT nova | `AuthorizationPermissionCatalogTest::test_tenant_permission_is_added_to_the_system_administrator_role` | Feature/persistência |
| FR-AF-021 — sem promoção PLATFORM automática | `TenantCreationServiceTest::test_tenant_creation_does_not_assign_a_platform_role` | Feature |

## Critérios de sucesso

| Critério | Evidência automatizada |
| --- | --- |
| SC-AF-001 — negação sem concessão aplicável | `AuthorizationServiceTest` cobre ausência de grant; `TenantApiTest::test_active_member_without_administrator_permission_cannot_change_availability` confirma a operação HTTP negada. |
| SC-AF-002 — isolamento entre tenants | `AuthorizationServiceTest::test_it_denies_a_tenant_grant_in_another_tenant` e `AuthorizationGroupRoleAssignmentTest::test_a_tenant_group_grant_does_not_apply_in_another_tenant`. |
| SC-AF-003 — revogação sem novo login | `TenantApiTest::test_removing_a_grant_denies_the_next_authenticated_request`, `TenantApiTest::test_context_capabilities_are_projected_again_after_a_grant_is_revoked` e as revogações por grupo/hierarquia. |
| SC-AF-004 — preservação de administrador | `AuthorizationRoleAssignmentServiceTest` cobre recusa de remoção/inativação e transferência. |
| SC-AF-005 — trilha sem segredos | `AuthorizationAuditEventTest` cobre correlação, atomicidade, imutabilidade e remoção de campos sensíveis. |

## Evidências de execução

| Verificação | Resultado em 2026-09-26 |
| --- | --- |
| `php artisan test` | 193 aprovados, 2 ignorados (integração Mailpit sem `MAILPIT_API_URL`), 725 assertions. |
| `npm run test` | 25 arquivos e 95 testes aprovados. |
| `npm run type-check` | Aprovado. |
| `npm run build` | Aprovado. |
| `vendor/bin/pint --dirty` | Aprovado. |
| `git diff --check` | Aprovado. |
| `PLAYWRIGHT_BROWSER_CHANNEL=chrome npm run test:e2e` | 18 cenários aprovados no Google Chrome instalado localmente. |
| Migration `2026_09_26_000008_add_tenant_context_to_authorization_roles` | Aplicada no MySQL local de testes (`coreMigration`); `auth_role.idTenant`, índice e FK confirmados. |

O recorte de contrato de tenant também foi executado diretamente: `TenantApiTest` aprovou 9 testes e 63 assertions; os testes de store e diálogo aprovaram 11 testes.

## Evidência E2E de revogação

O cenário `revalidates a revoked tenant capability before the next browser operation` seleciona um tenant com capability administrativa, simula a revogação antes da operação seguinte e confirma a resposta `403` com o código seguro `TENANT_ADMINISTRATOR_REQUIRED`. O backend real é coberto em paralelo por `TenantApiTest::test_removing_a_grant_denies_the_next_authenticated_request`.
