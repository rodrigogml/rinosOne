# Contrato: Políticas Avançadas

Operações versionadas em JSON gerenciam políticas, delegações, solicitações de acesso, decisões de aprovação, SoD e identidades de serviço. Payloads usam `camelCase`, IDs persistidos são inteiros positivos e erros usam envelope seguro. Todas as operações administrativas abaixo ficam no contexto autenticado `POST /api/v1/tenants/{tenantId}/authorization/advanced/...` e requerem administração do tenant; a revogação de uma solicitação também pode ser feita pelo seu próprio solicitante.

| Operação | Rota | Payload principal | Resposta |
| --- | --- | --- | --- |
| Publicar política | `/policies` | `key`, `definition` declarativa | Política e versão |
| Vincular política | `/policies/{policyId}/bindings` | `permissionId` | Vínculo criado |
| Declarar SoD | `/separation-rules` | `permissionId`, `incompatiblePermissionId` | Regra criada |
| Delegar acesso | `/delegations` | `delegatorUserId`, `recipientUserId`, `permissionId`, `originAssignmentId`, `startsAt`, `endsAt`, `limits?` | Delegação criada |
| Revogar delegação | `/delegations/{delegationId}/revocation` | — | Delegação revogada |
| Solicitar acesso temporário | `/access-requests` | `permissionId`, `startsAt`, `endsAt` | Solicitação pendente |
| Consultar solicitações | `GET /access-requests` | — | Próprias solicitações; administradores consultam a fila do tenant |
| Aprovar solicitação | `/access-requests/{requestId}/approval` | — | Solicitação aprovada |
| Revogar solicitação | `/access-requests/{requestId}/revocation` | — | Solicitação revogada |
| Criar identidade técnica | `/service-identities` | `ownerUserId`, `displayName`, `purpose`, vigência opcional | Identidade criada |
| Conceder permissão técnica | `/service-identities/{identityId}/permissions` | `permissionId` | Sem conteúdo |
| Emitir chave de API | `/service-identities/{identityId}/credentials` | `displayName`, `permissionKeys?`, `expiresAt?` | `credentialId` e `apiKey`, exibida uma única vez |
| Revogar chave de API | `/service-credentials/{credentialId}/revocation` | — | Sem conteúdo |

Erros usam `{ "error": { "code": "...", "message": "..." } }`; não revelam se um recurso de outro tenant existe. A política de decisão é invalidada sempre que uma alteração possa modificar a decisão efetiva.
