# Baseline Inicial de Custo — Fundação de Autorização

**Data:** 2026-09-26  
**Escopo:** decisão direta, por grupo e por hierarquia, sem cache distribuído.

## Cenários reproduzíveis

| Cenário | Teste | Resultado inicial |
| --- | --- | --- |
| Grant direto TENANT | `AuthorizationServiceTest::test_it_allows_a_tenant_grant_only_in_its_active_tenant` | Aprovado |
| Grant por grupo e revogação | `AuthorizationGroupRoleAssignmentTest::test_it_unions_active_group_grants_and_revokes_them_on_the_next_decision` | Aprovado |
| Grant por hierarquia de múltiplos níveis | `AuthorizationGroupHierarchyTest::test_a_child_member_inherits_an_ancestors_grant_through_a_multi_level_chain` | Aprovado |

Comando executado:

```powershell
php artisan test tests/Feature/AuthorizationServiceTest.php tests/Feature/AuthorizationGroupRoleAssignmentTest.php tests/Feature/AuthorizationGroupHierarchyTest.php
```

Evidência: 12 testes aprovados, 30 assertions, duração de 0,44 s. A suíte usa SQLite em memória, conforme `phpunit.xml`; portanto o número serve somente como guarda de regressão funcional, não como SLA de MySQL.

## Forma esperada das consultas

`AuthorizationService` restringe a decisão por pessoa, esfera, tenant quando aplicável, estado e permission. A relação direta possui o índice `idx_auth_role_assignment_user_tenant_state`; grupos começam por `auth_group_user.idUser`, e a CTE recursiva sobe pela chave indexada `auth_group_group.idChildGroup`. Não há iteração PHP sobre todos os usuários ou recursos.

## Planos MySQL verificados

As migrations de autorização foram aplicadas na conexão de teste `coreMigration` antes da inspeção. O `EXPLAIN` da decisão direta mostrou `Index lookup on auth_role_assignment using idx_auth_role_assignment_user_tenant_state (idUser, idTenant, state)`, seguido por buscas de linha única nas relações de role e permission.

O `EXPLAIN` da decisão por grupo/hierarquia mostrou:

- busca indexada de `auth_group_user` por `idUser`;
- CTE recursiva materializada e deduplicada, com busca por `idx_auth_group_group_child (idChildGroup)` em cada nível;
- busca indexada de `auth_group_role_assignment` por `idTenant` e busca de linha única no grupo elegível.

Os planos não apresentam varredura global de usuários nem loop por recurso. O schema de teste ainda está sem massa representativa; a futura spec `authorization-performance` deve repetir a medição com cardinalidade próxima à produção e avaliar seletividade adicional dos índices de grants coletivos.

## Próxima evolução

Esta baseline não estabelece cache, métricas de produção ou orçamento de latência. Esses itens pertencem à spec `authorization-performance`; ela deve usar o `EXPLAIN` real como ponto de partida, após preparar uma base MySQL com volume representativo.
