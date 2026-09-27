# Contrato: Decisão por Recurso

`check` usa `resource: { type, id }` opcional e `tenantId` obrigatório para TENANT. O servidor resolve esfera e pertencimento pelo adaptador; o cliente não informa esses valores como autoridade.

`checkBatch` preserva a mesma semântica para pares permission/recurso. Endpoints de listagem recebem filtros funcionais e retornam somente recursos já autorizados; recurso inacessível usa resposta segura indistinguível conforme a operação.

## HTTP — decisões em lote

`POST /api/v1/authorization/resource-checks` recebe até 100 pares, na ordem enviada. A `scope` é resolvida pela permission ativa no servidor; o cliente informa somente o contexto de tenant quando aplicável.

```json
{
  "checks": [
    {
      "permissionKey": "personal.folder.read",
      "resource": { "type": "personal.folder", "id": 42 }
    }
  ]
}
```

Resposta `200`:

```json
{
  "decisions": [
    { "allowed": true, "reasonCode": "RESOURCE_RELATION_APPLIES" }
  ]
}
```

Entrada inválida recebe `422` com envelope `VALIDATION_ERROR`. Permission, recurso ou contexto indisponível retornam uma decisão negada sem confirmar existência. A listagem protegida será exposta pelo adaptador de recurso na tarefa 2.2.2; este endpoint não aceita uma lista de IDs como substituto da consulta autorizada.

## HTTP — listagem pessoal autorizada

`GET /api/v1/authorization/personal-workspace/folders?page=1&perPage=50` retorna somente pastas ativas às quais a pessoa tem acesso de leitura por relation direta, grupo (inclusive ancestral) ou acesso-base ao próprio workspace. `perPage` é limitado a 100. A API não informa totais ou quantidade de itens ocultos.
