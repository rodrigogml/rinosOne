# Modelo de dados: Interface unificada de acessos e permissões

Esta feature não introduz uma nova fonte persistida para decisões de autorização. Os modelos abaixo são projeções de leitura e comandos sobre a fundação existente. Todos os identificadores persistidos permanecem **BIGINT positivo**.

## Entity: Contexto de administração de acesso

| Campo | Tipo | Restrições | Observações |
|---|---|---|---|
| scope | enum | `PERSONAL`, `TENANT` ou `PLATFORM`; obrigatório | Derivado da rota e da superfície ativa. |
| tenantId | BIGINT ou nulo | Obrigatório somente em `TENANT` | Nunca fornecido por campo livre em uma escrita. |
| displayName | string | Obrigatório | Nome humano do tenant, workspace pessoal ou plataforma. |
| workspaceKind | enum ou nulo | `PERSONAL`, `TENANT` ou nulo | Presente somente quando houver recurso de workspace. |
| capabilities | objeto | Somente flags permitidas | Ajuda a compor a UI; não substitui checagem do servidor. |

### Relacionamentos

- `TENANT` referencia um `tenant` existente por `tenantId`.
- `PERSONAL` é associado ao usuário autenticado e ao seu workspace, sem um identificador de proprietário por item.
- `PLATFORM` não referencia tenant.

### Transições de estado

Não se aplica. O contexto é derivado a cada requisição; mudança de contexto invalida qualquer formulário pendente.

## Entity: Projeção de sujeito e acesso efetivo

| Campo | Tipo | Restrições | Observações |
|---|---|---|---|
| subjectId | BIGINT | Obrigatório | Pessoa ou identidade de serviço visível no contexto. |
| subjectType | enum | `USER` ou `SERVICE_IDENTITY` | Extensível somente pelo domínio de autorização. |
| displayName | string | Obrigatório | Nome seguro para administração. |
| accessSources | lista | Pode ser vazia | Papéis, grupos, vínculos diretos, delegações ou compartilhamentos visíveis. |
| effectiveCapabilities | lista resumida | Filtrada por autorização de leitura | Não expõe fatores confidenciais. |
| expiresAt | datetime ou nulo | UTC na API | Aplicável a fontes temporárias. |

### Relacionamentos

- Lê `auth_role_assignment`, `auth_group_user`, `auth_group_role_assignment`, `auth_restriction`, `auth_delegation` e as relações de recurso pertinentes.
- Usa `auth_permission` e `auth_role` para nomes, descrições, escopo, estado e marcação `systemManaged`.

### Transições de estado

Não se aplica à projeção. A fonte original mantém seus próprios estados; revogações e expirações devem reconsultar a projeção.

## Entity: Papel e item de catálogo

| Campo | Tipo | Restrições | Observações |
|---|---|---|---|
| id | BIGINT | Chave existente | `auth_role` ou `auth_permission`. |
| key | string | Única na entidade de origem | Exibida em detalhe técnico autorizado, não é a ação primária do usuário. |
| displayName | string | Obrigatório | Rótulo de negócio. |
| description | string | Obrigatório | Finalidade legível. |
| scope | enum | Escopo compatível | Informa onde pode ser aplicada. |
| systemManaged | boolean | Obrigatório | Determina leitura sem edição. |
| active | boolean | Obrigatório | Itens inativos não recebem nova atribuição. |

### Relacionamentos

- Um papel possui permissões por `auth_role_permission`.
- Um papel pode ser concedido diretamente ou por grupo, conforme os vínculos existentes.

## Entity: Compartilhamento de recurso

| Campo | Tipo | Restrições | Observações |
|---|---|---|---|
| resourceType | string controlada | Obrigatório | Tipo lógico da fundação de recurso. |
| resourceId | BIGINT positivo | Obrigatório | Identificador persistido do recurso; nunca identifica acervo fora do contexto. |
| grantee | sujeito | Obrigatório | Destinatário da relação. |
| relation | string controlada | Obrigatório | Nível de leitura, escrita ou administração definido pela fundação. |
| origin | enum | `DIRECT` ou `INHERITED` | Herança é apenas informativa quando não pode ser editada no item. |
| inheritedFrom | recurso ou nulo | Exibido somente se autorizado | Origem da herança. |

### Relacionamentos

- Projeta `auth_resource_relation` e o adaptador do recurso de workspace.
- O responsável pelo workspace é obtido a partir do próprio workspace; não é campo deste registro.

### Transições de estado

```text
direct grant -> active -> revoked
inherited grant -> active (editável somente no recurso de origem) -> revoked na origem
```

## Entity: Registro de auditoria de acesso

| Campo | Tipo | Restrições | Observações |
|---|---|---|---|
| id | BIGINT | Chave existente | `auth_audit_event`. |
| occurredAt | datetime | Obrigatório | Retornado em ISO-8601 UTC. |
| actorUserId | BIGINT ou nulo | Filtrado por autorização | Autor da operação. |
| operation | string | Obrigatório | Evento de autorização normalizado. |
| targetType | string | Obrigatório | Tipo do alvo. |
| targetId | BIGINT ou nulo | Pode ser nulo | Alvo contextual. |
| before/after | objeto protegido | Não entregue integralmente | Apenas resumo seguro quando aplicável. |

### Transições de estado

Imutável. Retenção é responsabilidade do job existente, não da interface.
