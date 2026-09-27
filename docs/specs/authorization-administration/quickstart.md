# Cenários de Validação

1. Administrador do Tenant A cria role e concede a membro ativo → **esperado:** action real é permitida no A.
2. Tentar conceder no Tenant B ou a não membro → **esperado:** erro seguro e nenhuma escrita.
3. Consultar effective access e explain para pessoa autorizada → **esperado:** fatores próprios do contexto, sem dados de terceiros.
4. Roundtrip web: salvar role → API real → atualizar lista/capability → **esperado:** confirmação acessível e novo estado observado.

## Matriz de Evidências

| Requisito ou critério | Evidência automatizada |
| --- | --- |
| FR-AA-001 a 005 / SC-AA-001 e SC-AA-004 | `AuthorizationAdministrationAuthorizerTest`, `AuthorizationAdministrationApiTest` e `AuthorizationAdministrationFacade` exercitam escopo, compatibilidade, conflito e último administrador. |
| FR-AA-006 e 007 / SC-AA-002 | `EffectiveAccessQueryTest` e `AuthorizationAdministrationApiTest` cobrem fatores diretos/grupo/restriction, allow, deny e isolamento do tenant. |
| FR-AA-008 e 010 / SC-AA-003 | `AuthorizationAuditEventTest` e `AuthorizationAdministrationApiTest` cobrem imutabilidade, redaction, paginação, isolamento e eventos de invariantes recusadas. |
| FR-AA-009 | `AuthorizationAdministrationApiTest::test_revoked_role_is_recomputed_by_the_next_protected_operation_and_capability_projection` confirma a capability e a operação real após revogação. |
| INT-WEB-ADMIN-001 | `AuthorizationAdministrationSurface.spec.ts` cobre navegação por abas, chamadas tenant-scoped, confirmação, conflito e preservação do estado. |

> [!NOTE]
> A validação autenticada foi executada em 2026-09-27 com um administrador do tenant de homologação: criação de role, effective access, filtro de auditoria e confirmação de remoção. O teste incluiu viewport desktop e 390×844, navegação por teclado e inspeção da árvore de acessibilidade (roles, labels, status e diálogo).
