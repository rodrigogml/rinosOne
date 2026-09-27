# Contrato: Administração de Autorização

Rotas versionadas recebem `tenantId` numérico quando TENANT e DTOs camelCase. Escritas retornam a projeção alterada; violação de escopo/invariante retorna envelope seguro `{ error: { code, message } }` sem alteração parcial.

Grupos de endpoint: `roles`, `assignments`, `groups`, `memberships`, `restrictions`, `effective-access`, `explain` e `audit-events`. Todos verificam permission administrativa antes de consultar ou alterar dados.

## Convenções

- Base: `/api/v1/tenants/{tenantId}/authorization`.
- `tenantId` e todo identificador de recurso usam BIGINT positivo.
- Escritas exigem `tenant.authorization.manage` ou `platform.authorization.tenant.manage`; leituras exigem a permission `read` correspondente.
- Uma resposta de erro não revela se o alvo pertence a outro tenant: `{ "error": { "code": "AUTHORIZATION_ADMINISTRATION_DENIED", "message": "Ação não permitida para este tenant." } }` com `403`.
- Validação usa `422`; invariantes ou estado incompatível usam `409`; recurso ausente no contexto autorizado usa `404`.

## Escritas da primeira entrega

| Método e rota | Corpo camelCase | Resposta |
| --- | --- | --- |
| `POST /roles` | `key`, `displayName`, `description` | `201 { role }` |
| `POST /roles/{roleId}/permissions` | `permissionId` | `204` |
| `POST /roles/{roleId}/assignments` | `userId` | `201 { assignment }` |
| `DELETE /roles/{roleId}/assignments/{userId}` | — | `204` |
| `POST /groups` | `displayName` | `201 { group }` |
| `POST /groups/{groupId}/members` | `userId` | `204` |
| `DELETE /groups/{groupId}/members/{userId}` | — | `204` |
| `POST /groups/{groupId}/roles` | `roleId` | `204` |
| `DELETE /groups/{groupId}/roles/{roleId}` | — | `204` |
| `POST /restrictions` | `permissionId`, exatamente um de `userId` ou `groupId`, `startsAt?`, `endsAt?` | `201 { restriction }` |
| `POST /restrictions/{restrictionId}/deactivation` | — | `204` |
| `DELETE /memberships/{membershipId}` | — | `204` |

## Consultas de acesso efetivo

| Método e rota | Corpo camelCase | Resposta |
| --- | --- | --- |
| `GET /effective-access/users/{userId}` | — | `200 { effectiveAccess }` |
| `POST /explain/users/{userId}` | `permissionKey` | `200 { explanation }` |

As duas rotas exigem permission administrativa de leitura antes de resolver o sujeito. `effectiveAccess` contém somente identificadores e metadados de autorização no tenant da rota: membership, roles diretas, grupos, roles de grupo, restrictions e relações de recurso. `explanation` é calculada pelo mesmo motor de autorização da operação protegida e retorna `allowed`, `reasonCode` e os fatores visíveis.

## Auditoria

`GET /audit-events` aceita os filtros opcionais `operation`, `targetType`, `targetId` e `perPage` (1 a 50). A resposta é `{ events, pagination }`, em ordem decrescente de ocorrência, e cada evento contém apenas `id`, `occurredAt`, `operation`, `targetType`, `targetId` e `actorUserId`. Snapshots `before`/`after` e correlação não são expostos. A consulta é sempre restringida ao tenant da rota; eventos recusados por invariantes usam a operação `authorization.administration.rejected`.

A API nunca aceita scope ou tenant no corpo: ambos são derivados da rota e da operação administrativa.
