# Contrato: Decisão por Recurso

`check` usa `resource: { type, id }` opcional e `tenantId` obrigatório para TENANT. O servidor resolve esfera e proprietário pelo adaptador; o cliente não informa esses valores como autoridade.

`checkBatch` preserva a mesma semântica para pares permission/recurso. Endpoints de listagem recebem filtros funcionais e retornam somente recursos já autorizados; recurso inacessível usa resposta segura indistinguível conforme a operação.
