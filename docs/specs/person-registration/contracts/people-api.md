# Contrato de API — Cadastro de Pessoas por Organização

Todos os endpoints requerem autenticação, contexto ativo da organização indicada e a permissão específica da operação. Os identificadores são numéricos e os corpos JSON usam `camelCase`. Campos de CPF, CNPJ, conta e Pix não são mascarados, conforme a política de apresentação aprovada.

## Operações

| Método e caminho | Operação | Permissão prevista |
| --- | --- | --- |
| `GET /api/v1/tenants/{tenantId}/people` | Listar e buscar Pessoas | `tenant.people.read` |
| `POST /api/v1/tenants/{tenantId}/people` | Criar Pessoa | `tenant.people.create` |
| `GET /api/v1/tenants/{tenantId}/people/{personId}` | Consultar detalhe | `tenant.people.read` |
| `PUT /api/v1/tenants/{tenantId}/people/{personId}` | Atualizar dados e coleções | `tenant.people.update` |
| `POST /api/v1/tenants/{tenantId}/people/{personId}/duplicate` | Duplicar Pessoa | `tenant.people.duplicate` |
| `POST /api/v1/tenants/{tenantId}/people/{personId}/inactivate` | Inativar | `tenant.people.inactivate` |
| `POST /api/v1/tenants/{tenantId}/people/{personId}/reactivate` | Reativar | `tenant.people.reactivate` |
| `DELETE /api/v1/tenants/{tenantId}/people/{personId}` | Excluir fisicamente | `tenant.people.delete` |

As chaves de permissão são o contrato pretendido para integração com a fundação de autorização e serão registradas junto à implementação.

## Políticas gerais de operação

- `GET /people` aceita `page` (mínimo 1, padrão configurável 1) e `perPage` (padrão configurável 50, máximo configurável 200). A resposta inclui `pagination.page`, `pagination.perPage`, `pagination.total` e `pagination.lastPage`.
- A ordenação padrão é `displayName` crescente seguida de `id` crescente. Futuros campos de ordenação precisam ser explicitamente adicionados ao contrato; não há ordenação livre por entrada do cliente.
- Toda mutação exige cabeçalho `Idempotency-Key` UUID v4. A mesma chave, usuário, organização e operação devolve a resposta já concluída por 24 horas configuráveis, sem executar novamente a intenção.
- A API autenticada aplica limite geral configurável de 120 requisições por minuto por usuário e organização. Excesso devolve `429 API_RATE_LIMITED` e tempo seguro para nova tentativa.
- Corpos JSON obedecem a limite geral configurável de 1 MiB.
- O detalhe devolve `version`. Atualização, duplicação, inativação, reativação e exclusão recebem a versão lida; divergência devolve `409 PERSON_VERSION_CONFLICT` sem aplicar alteração.

## Pessoa resumida

Usada em listagem e seleção.

| Campo | Tipo | Descrição |
| --- | --- | --- |
| `id` | number | Identificador da Pessoa. |
| `personType` | string | `PF` ou `PJ`. |
| `displayName` | string | Nome calculado para apresentação. |
| `document` | string ou null | CPF ou CNPJ apresentado quando houver. |
| `status` | string | `ACTIVE` ou `INACTIVE`. |

## Criar ou atualizar Pessoa

O corpo de criação e atualização representa o agregado completo. Coleções enviadas substituem somente a coleção correspondente; ausência da coleção preserva os itens existentes em atualização parcial.

| Campo | Tipo | Obrigatório na criação | Validação principal |
| --- | --- | --- | --- |
| `personType` | string | sim | `PF` ou `PJ`. |
| `name` | string | sim | 2 a 255 caracteres. |
| `alias` | string ou null | não | até 255 caracteres. |
| `cpf` / `cnpj` | string ou null | não | Compatível com tipo, válido e único na organização quando informado. |
| `rg`, `rgIssuer`, `pisNis` | string ou null | não | Aplicáveis à PF. |
| `passportNumber`, `foreignDocumentNumber` | string ou null | não | Até o limite do modelo. |
| `birthDate` / `foundationDate` | string ou null | não | Data não futura e compatível com tipo. |
| `notes` | string ou null | não | Conteúdo livre dentro do limite do modelo. |
| `addresses` | array | não | Itens válidos de endereço. |
| `contacts` | array | não | Itens válidos de contato. |
| `bankAccounts` | array | não | Itens válidos de conta. |
| `pixKeys` | array | não | Itens válidos de chave Pix. |
| `relationships` | array | não | Itens válidos de relacionamento. |

Um endereço exige `idCountry`; se o país for Brasil, exige `idBrazilState` e `idBrazilMunicipality`. Rua é textual e opcionalmente pode trazer `idLocalityReference`. Nenhuma coleção recebe limite específico nesta feature além do limite geral de corpo JSON.

## Detalhe de Pessoa

Além da Pessoa resumida, a resposta de detalhe contém identificação complementar e as coleções `addresses`, `contacts`, `bankAccounts`, `pixKeys` e `relationships`. Cada relacionamento apresenta `direction` (`OUTGOING` ou `INCOMING`), `relationshipType`, `displayRelationshipType`, a outra Pessoa e a descrição. O item `INCOMING` é uma projeção de apresentação; não representa um segundo vínculo persistido.

## Duplicação

| Campo | Tipo | Obrigatório | Regra |
| --- | --- | --- | --- |
| `copyAddresses` | boolean | sim | Copia endereços como novos itens. |
| `copyContacts` | boolean | sim | Copia contatos como novos itens. |
| `copyBankAccounts` | boolean | sim | Copia contas como novos itens, exigindo confirmação posterior quando aplicável. |
| `copyPixKeys` | boolean | sim | Copia chaves como novos itens, exigindo confirmação posterior quando aplicável. |
| `copyRelationships` | boolean | sim | Copia relacionamentos somente após confirmação explícita das Pessoas relacionadas. |

CPF, CNPJ e demais identificadores exclusivos não são copiados.

## Exclusão física

Uma solicitação de exclusão retorna sucesso sem corpo quando a Pessoa pode ser removida. Se o módulo conhecer usos bloqueantes, retorna `409 PERSON_IN_USE` com `usages`, lista segura de módulos e descrições que precisam ser resolvidos. Se um impedimento de integridade ocorrer sem diagnóstico prévio, retorna `409 PERSON_DELETE_CONFLICT` com mensagem segura e sem detalhe técnico.

## Erros comuns

| Status | Código | Significado |
| --- | --- | --- |
| `400` | `PERSON_VALIDATION_FAILED` | Um ou mais campos são inválidos; erros identificam o caminho do campo ou item. |
| `403` | `PERSON_ACCESS_DENIED` | Usuário sem contexto elegível ou permissão suficiente. |
| `404` | `PERSON_NOT_FOUND` | Pessoa não existe na organização atual. |
| `409` | `PERSON_DOCUMENT_CONFLICT` | CPF ou CNPJ já pertence a outra Pessoa da organização. |
| `409` | `PERSON_RELATIONSHIP_CONFLICT` | Relacionamento inválido, repetido ou com Pessoa fora da organização. |
| `409` | `PERSON_IN_USE` | Uso conhecido impede exclusão física. |
| `409` | `PERSON_DELETE_CONFLICT` | Impedimento de integridade não classificado impede exclusão. |
| `409` | `PERSON_VERSION_CONFLICT` | Dados mudaram desde a leitura; o cliente deve recarregar antes de alterar. |
| `429` | `API_RATE_LIMITED` | Limite geral de requisições autenticadas excedido. |
