# Contrato: Administração de Autorização

Rotas versionadas recebem `tenantId` numérico quando TENANT e DTOs camelCase. Escritas retornam a projeção alterada; violação de escopo/invariante retorna envelope seguro `{ error: { code, message } }` sem alteração parcial.

Grupos de endpoint: `roles`, `assignments`, `groups`, `memberships`, `restrictions`, `effective-access`, `explain` e `audit-events`. Todos verificam permission administrativa antes de consultar ou alterar dados.
