# Contrato: Administração contextual de acessos

## Convenções

- Base autenticada: `/api/v1`.
- JSON de entrada e saída usa `camelCase`; IDs persistidos são BIGINT positivos.
- A esfera é definida exclusivamente pela rota. O corpo de uma escrita não aceita `scope`, `tenantId`, `workspaceId` ou equivalente para determinar autorização.
- Todas as respostas de leitura e escrita passam pelo mesmo serviço de autorização usado por operações de negócio. Capabilities devolvidas são orientação de interface, nunca prova de autorização.
- Erros usam `{ "error": { "code", "message" } }`; recursos fora do contexto não revelam existência.
- `403` indica falta de autorização, `404` recurso indisponível no contexto autorizado, `409` conflito ou invariante, `422` validação e `412` versão/contexto desatualizado quando o comando exigir pré-condição.

## Famílias de contexto

| Esfera | Prefixo | Contexto derivado de |
|---|---|---|
| `TENANT` | `/tenants/{tenantId}/authorization` | `tenantId` da rota e membership/autorização do solicitante. |
| `PERSONAL` | `/authorization/personal` | Usuário autenticado e seu workspace pessoal. |
| `PLATFORM` | `/platform/authorization` | Permissões de plataforma do solicitante. |

As três famílias devolvem a mesma forma de contexto quando a operação for equivalente. `contextVersion` é um token opaco de pré-condição para comandos que o suportem:

```json
{
  "context": {
    "scope": "TENANT",
    "tenantId": 42,
    "displayName": "Empresa Exemplo",
    "workspaceKind": "TENANT",
    "capabilities": {
      "canReadAccess": true,
      "canManageRoles": true,
      "canManageSharing": false,
      "canUseAdvancedControls": true
    }
  },
  "contextVersion": "17"
}
```

## Consultas contextuais novas

Os caminhos abaixo são sufixados aos prefixos compatíveis. `PERSONAL` expõe somente operações aplicáveis ao workspace pessoal; `PLATFORM` não expõe compartilhamento de workspace sem um recurso de plataforma explicitamente suportado.

| Método e sufixo | Finalidade | Resposta principal |
|---|---|---|
| `GET /context` | Contexto, capabilities e entradas disponíveis | `{ context, sections }` |
| `GET /subjects?query=&page=&perPage=` | Pessoas e identidades administráveis no contexto | `{ subjects, pagination }` |
| `GET /roles?query=&includePermissions=&page=&perPage=` | Catálogo contextual de papéis | `{ roles, pagination }` |
| `GET /permissions?query=` | Catálogo de permissões em detalhe | `{ permissions }` |
| `GET /subjects/{subjectType}/{subjectId}/effective-access` | Resumo e fontes de acesso efetivo | `{ effectiveAccess }` |
| `POST /subjects/{subjectType}/{subjectId}/explain` | Explica uma capacidade em chave técnica autorizada | `{ explanation }` |
| `GET /audit-events/contextual?...` | Auditoria contextual filtrável no `TENANT` | `{ events, pagination }` |

`GET /effective-access/users/{userId}`, `POST /explain/users/{userId}` e `GET /audit-events` do contrato anterior permanecem compatíveis durante a migração. Por isso a auditoria contextual de `TENANT` usa o sufixo `/audit-events/contextual`; as famílias `PERSONAL` e `PLATFORM`, que não possuem rota legada nesse caminho, usam `/audit-events`. A interface nova utiliza a família `subjects` para atender pessoas e identidades de serviço sem duplicar tela.

### Projeções contextuais compartilhadas

As projeções novas são aditivas e usam os tipos abaixo, sempre em `camelCase`. Elas não alteram os envelopes, rotas ou campos dos contratos administrativos e avançados existentes durante a migração.

| Projeção | Campos mínimos |
|---|---|
| sujeito | `subjectId`, `subjectType` (`USER` ou `SERVICE_IDENTITY`), `displayName`, `accessSources`, `effectiveCapabilities`, `expiresAt` |
| fonte de acesso | `type`, `displayName`, `scope`, `expiresAt` |
| catálogo | `id`, `catalogType` (`ROLE` ou `PERMISSION`), `key`, `displayName`, `description`, `scope`, `systemManaged`, `active` |
| compartilhamento | `id`, `resourceType`, `resourceId`, `grantee`, `relation`, `origin`, `inheritedFrom` |
| auditoria segura | `id`, `occurredAt`, `actorUserId`, `operation`, `targetType`, `targetId` |

`origin` é `DIRECT` ou `INHERITED`; somente o segundo apresenta `inheritedFrom`. A projeção de auditoria não contém snapshots `before`, `after`, segredo ou credencial. Os DTOs de frontend rejeitam identificadores fora do intervalo inteiro seguro de JavaScript, enumerações não declaradas e campos obrigatórios ausentes.

Consultas paginadas usam `page` a partir de `1`, `perPage` padrão `25` e máximo `50`. A resposta contém `pagination.page`, `pagination.perPage`, `pagination.total` e `pagination.lastPage`.

## Comandos de papel e grupo

Os comandos existentes de `roles`, `groups`, assignments, memberships e restrictions permanecem a fonte de escrita. A nova UI deve consumir os retornos contextuais e manter os contratos de `administration-api.md` compatíveis. Novos comandos somente são adicionados quando a operação não existir, seguindo os padrões abaixo.

| Método e sufixo | Corpo | Resposta |
|---|---|---|
| `POST /roles/{roleId}/assignments` | `{ "subjectId": 123, "expectedContextVersion": "..." }` | `201 { assignment, contextVersion }` |
| `DELETE /roles/{roleId}/assignments/{subjectId}` | cabeçalho ou query de pré-condição | `204` |
| `POST /groups/{groupId}/members` | `{ "subjectId": 123, "expectedContextVersion": "..." }` | `204` |

Enquanto o endpoint compatível aceitar `userId`, o adaptador de API da UI converte apenas para sujeitos `USER`. Nas novas rotas de detalhe, `subjectType` (`USER` ou `SERVICE_IDENTITY`) é obrigatório junto a `subjectId`: ambos os repositórios usam BIGINTs independentes e o ID isolado seria ambíguo. Identidades de serviço não usam atribuição de papel de usuário sem um contrato específico aprovado.

## Compartilhamento de recursos de workspace

| Método e rota | Corpo | Resposta |
|---|---|---|
| `GET /authorization/personal/resources/{resourceType}/{resourceId}/shares` | — | `{ context, resource, workspaceResponsible, shares }` |
| `GET /tenants/{tenantId}/authorization/resources/{resourceType}/{resourceId}/shares` | — | `{ context, resource, workspaceResponsible, shares }` |
| `POST .../shares` | `subjectId`, `relation`, `expectedContextVersion` | `201 { share, contextVersion }` |
| `PATCH .../shares/{shareId}` | `relation`, `expectedContextVersion` | `200 { share, contextVersion }` |
| `DELETE .../shares/{shareId}` | pré-condição opcional | `204` |

Uma resposta de compartilhamento declara `origin` como `DIRECT` ou `INHERITED`. Vínculos herdados expõem `inheritedFrom` somente quando a pessoa pode consultar o recurso de origem e não aceitam alteração no recurso descendente. `workspaceResponsible` é informativo e não é proprietário de arquivo ou pasta.

## Controles avançados

As operações em `advanced-policies-api.md` continuam sob `/advanced`; a Central não cria uma segunda API para políticas, delegações, solicitações, SoD ou identidades de serviço. A resposta de `GET /context` inclui a seção `advanced` somente quando `canUseAdvancedControls` for verdadeiro, mas nunca inclui lista ou detalhe de uma área que o solicitante não pode ler. Credenciais continuam exibidas uma única vez somente no comando de emissão; `apiKey`, hash, segredo, prefixo de chave e material equivalente não entram em consultas de contexto, auditoria, telemetria ou cache de UI.

## Cache e concorrência

- Leituras podem usar `contextVersion` como ETag/opaco de consistência, nunca como dado de autorização local.
- Mutação que alterar decisão invalida a versão de política existente e devolve ou força reconsulta da projeção contextual.
- Em `409` por último administrador ou em `412` por versão desatualizada, a UI preserva entradas não sensíveis, recarrega o contexto e exige nova confirmação.
