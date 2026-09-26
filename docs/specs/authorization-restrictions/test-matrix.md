# Matriz de Testes — Restrições de Autorização

**Feature:** `authorization-restrictions`  
**Atualizada em:** 2026-09-26

| Requisito | Evidência automatizada | Tipo |
| --- | --- | --- |
| FR-AR-001 a 004 | `AuthorizationRestrictionServiceTest::test_it_persists_one_audited_tenant_restriction_for_an_eligible_subject`; `test_it_rejects_an_ambiguous_subject_or_a_subject_outside_the_tenant` | Feature/persistência |
| FR-AR-005 a 007 | `AuthorizationServiceTest::test_an_active_direct_restriction_denies_a_tenant_administrator_permission`; `test_an_active_group_restriction_denies_a_group_member_permission` | Feature |
| FR-AR-008 e 009 | `AuthorizationRestrictionServiceTest::test_it_applies_validity_with_an_inclusive_start_and_exclusive_end` | Feature |
| FR-AR-010 e 011 | `AuthorizationRestrictionServiceTest::test_it_audits_activation_deactivation_validity_changes_and_removal` | Feature/persistência |
| FR-AR-012 a 014 | `TenantApiTest::test_restriction_changes_capabilities_and_the_next_protected_request_without_revealing_its_details` | API |

| Critério | Evidência |
| --- | --- |
| SC-AR-001 | Restrictions diretas e de grupo negam grants, inclusive administrativos. |
| SC-AR-002 | O serviço exige sujeito e tenant compatíveis antes de persistir restriction TENANT. |
| SC-AR-003 | O endpoint e a capability são reavaliados após desativação, reativação e remoção. |
| SC-AR-004 | Os eventos `authorization.restriction.*` são persistidos pelo mesmo serviço transacional. |

## Evidências de execução

- `php artisan test --compact`: 200 aprovados, 2 ignorados, 753 assertions.
- `vendor/bin/pint --dirty`: aprovado.
- `git diff --check`: aprovado.

Não há job, credencial externa ou decisão persistida em sessão: a verificação ocorre no `AuthorizationService` a cada operação protegida.
