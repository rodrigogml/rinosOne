# Contratos de API: Tenants e Contexto

Todos os endpoints pertencem a `/api/v1`, exigem autenticação e usam payloads `camelCase`. Respostas de tenant nunca expõem nome de schema, credenciais, detalhes de migration ou falhas internas.

## Envelope de erro

Toda falha prevista usa o seguinte formato:

```json
{
  "error": {
    "code": "SAFE_ERROR_CODE",
    "message": "Mensagem segura para apresentação"
  }
}
```

Erros de validação podem acrescentar `fields`, mapeando nomes `camelCase` a mensagens seguras. O código é estável para consumo programático; a mensagem pode ser localizada. O envelope nunca contém nome de schema, SQL, host, segredo, chave de idempotência ou causa interna.

## Listar tenants disponíveis

**Método**: `GET /api/v1/tenants`

**Resposta (200)**

| Campo | Tipo | Descrição |
| --- | --- | --- |
| `tenants` | array | Tenants associados ao usuário autenticado. |
| `tenants[].id` | string | ULID público do tenant. |
| `tenants[].displayName` | string | Nome visível. |
| `tenants[].state` | string | Estado atual de disponibilidade. |
| `tenants[].selectable` | boolean | Indica se pode iniciar contexto operacional. |
| `tenants[].role` | string | `OWNER` nesta fase. |

## Criar tenant

**Método**: `POST /api/v1/tenants`

**Cabeçalho obrigatório**: `Idempotency-Key` com ULID gerado para uma única intenção de criação.

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| `displayName` | string | sim | Texto preenchido, máximo 120 caracteres. |

### Resposta (202)

| Campo | Tipo | Descrição |
| --- | --- | --- |
| `tenant` | object | Tenant criado ou recuperado pela mesma chave de idempotência. |
| `provisioning` | object | Estado seguro da preparação. |

### Erros

| Status | Código | Descrição |
| --- | --- | --- |
| 422 | `VALIDATION_ERROR` | Nome ou chave de idempotência inválidos. |
| 401 | `AUTHENTICATION_REQUIRED` | Não há usuário autenticado válido. |
| 409 | `TENANT_CREATION_CONFLICT` | A intenção não pode prosseguir em seu estado atual. |

## Validar e iniciar contexto na aba

**Método**: `POST /api/v1/tenants/{tenantId}/contexts`

O endpoint não grava o tenant ativo na sessão. Ele valida explicitamente a associação e devolve uma fotografia mínima para a aba que iniciou a seleção.

### Resposta (200)

| Campo | Tipo | Descrição |
| --- | --- | --- |
| `context.tenant.id` | string | Tenant validado. |
| `context.tenant.displayName` | string | Nome para identificação persistente na interface. |
| `context.membership.id` | string | Vínculo validado. |
| `context.membership.role` | string | `OWNER` nesta fase. |
| `context.availableModules` | array | Vazio enquanto não houver módulos aprovados. |

### Erros

| Status | Código | Descrição |
| --- | --- | --- |
| 404 | `TENANT_NOT_AVAILABLE` | Tenant inexistente, não associado ou não selecionável; não diferencia causas. |
| 401 | `AUTHENTICATION_REQUIRED` | Não há usuário autenticado válido. |

## Encerrar contexto da aba

**Método**: `DELETE /api/v1/tenants/{tenantId}/contexts`

**Resposta (204)**: a interface deve descartar seu contexto em memória. O endpoint registra o encerramento quando a identidade e o tenant forem reconhecidos, sem guardar uma seleção na sessão.

## Alterar disponibilidade do tenant

**Método**: `POST /api/v1/tenants/{tenantId}/availability`

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| `state` | string | sim | `ACTIVE` ou `INACTIVE`. |

### Resposta (200)

| Campo | Tipo | Descrição |
| --- | --- | --- |
| `tenant` | object | Tenant com a disponibilidade atualizada. |

### Erros

| Status | Código | Descrição |
| --- | --- | --- |
| 403 | `TENANT_OWNER_REQUIRED` | A pessoa não é proprietária do tenant. |
| 404 | `TENANT_NOT_AVAILABLE` | Tenant não encontrado ou não associado. |
| 409 | `TENANT_STATE_CONFLICT` | A transição solicitada não é válida. |

## Convenção para módulos futuros

Toda operação contextual futura usará o prefixo `POST|GET|PUT|DELETE /api/v1/tenants/{tenantId}/...`. O `tenantId` da rota é a única autoridade de escopo recebida pela operação; o servidor deve revalidar usuário, associação e estado do tenant antes de abrir seus dados.
