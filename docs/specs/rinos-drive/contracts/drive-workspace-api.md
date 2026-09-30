# Contrato de API — Rinos Drive

Todos os contratos exigem sessão autenticada, retornam JSON em camelCase e aplicam autorização no servidor para cada localização ou item. Nenhuma resposta contém backend, caminho físico, hash, chave de armazenamento ou URL pública.

## Alvos de workspace

| Alvo | Prefixo | Contexto resolvido |
| --- | --- | --- |
| Pessoal | `/api/v1/drive/personal` | Usuário autenticado. |
| Work | `/api/v1/tenants/{tenantId}/drive` | Tenant ativo, membership e acesso efetivo ao ramo solicitado. |

O `tenantId` é validado no servidor; ele não basta para conceder acesso e não depende da organização ativa em outra superfície.

## Catálogo unificado e compartilhados

| Método e rota | Finalidade | Resposta principal |
| --- | --- | --- |
| `GET /api/v1/drive/catalog` | Raízes de drives disponíveis ao principal. | `drives[]` e raiz virtual `sharedWithMe`. |
| `GET /api/v1/drive/shared-with-me` | Itens diretamente concedidos ao principal. | `folders[]`, `files[]` e capabilities seguras. |

Cada item de `drives[]` contém somente `target` (`scope`, `tenantId` quando aplicável), `displayName`, categoria (`PERSONAL` ou `TENANT`) e `usage` do workspace. O catálogo não contém árvore, contagem de itens, caminho, quota de outro drive nem tenant sem acesso efetivo.

Compartilhados comigo não possui alvo mutável próprio. Cada item retornado inclui seu `originTarget` autorizado e um arquivo compartilhado diretamente sempre tem `capabilities.edit=false` e `capabilities.trash=false`.

## Leitura e navegação

| Método e rota | Finalidade | Resposta principal |
| --- | --- | --- |
| `GET {prefix}/tree` | Raízes e árvore acessível. | `folders[]` com `id`, `parentFolderId`, `displayName`, `capabilities`. |
| `GET {prefix}/locations/root` | Conteúdo da raiz acessível. | `location`, `breadcrumbs`, `folders[]`, `files[]`, `capabilities`, `usage`. |
| `GET {prefix}/folders/{folderId}` | Conteúdo de pasta autorizada. | Mesmo formato de localização. |
| `GET {prefix}/trash` | Itens recuperáveis do workspace. | `location`, `folders[]`, `files[]`, `capabilities`, `purgeAfter`. |
| `GET {prefix}/items/{itemType}/{itemId}/details` | Metadados seguros de item. | `item`, `location`, `capabilities`, `metadata`. |

`itemType` admite somente `folder` ou `file`. A projeção de arquivo usa o identificador da posse, não o conteúdo físico ou a versão.

## Operações de organização

Para criação, renomeação, movimentação e upload, o servidor calcula e reserva o `displayName` final atomicamente, serializado pelo workspace e pela localização de destino. Requisições simultâneas que solicitarem o mesmo nome recebem projeções com nomes finais distintos; o nome declarado pelo cliente nunca é uma reserva.

| Método e rota | Entrada | Resultado |
| --- | --- | --- |
| `POST {prefix}/folders` | `displayName`, `parentFolderId` nulo ou válido | Pasta criada e projeção segura, com `displayName` final. |
| `PATCH {prefix}/folders/{folderId}` | `displayName` | Pasta renomeada, com `displayName` final. |
| `POST {prefix}/folders/{folderId}/move` | `destinationFolderId` nulo ou válido | Pasta movida, com `displayName` final. |
| `POST {prefix}/files/{possessionId}/move` | `destinationFolderId` nulo ou válido | Arquivo movido, com `displayName` final. |
| `POST {prefix}/items/trash` | `items[]` | Sucesso integral ou erro; não há remoção parcial silenciosa. |
| `POST {prefix}/items/restore` | `items[]` | Restauro integral ou erro. |
| `POST {prefix}/items/release` | `items[]`, `confirmation` | Limpeza definitiva integral depois de confirmação explícita. |

## Transferências entre painéis

| Método e rota | Entrada | Resultado |
| --- | --- | --- |
| `POST /api/v1/drive/transfers` | `sourceTarget`, `destinationTarget`, `items[]`, `mode` | Operação opaca criada com estado e contadores seguros. |
| `GET /api/v1/drive/transfers/{transferId}` | nenhum | Estado, modo, total/processado, prazo de lease e erro categorizado ao solicitante. |
| `POST /api/v1/drive/transfers/{transferId}/cancel` | nenhum | Cancela somente uma operação do próprio solicitante ainda `PENDING`; libera reservas. |

`sourceTarget` e `destinationTarget` usam `{ "scope": "PERSONAL"|"TENANT", "tenantId": number|null, "folderId": number|null }`; ambos são resolvidos contra catálogo e autorização atuais. `items[]` aceita somente `folder` ou `file` de uma única origem. `mode` aceita `COPY` ou `MOVE`.

O servidor adquire reservas antes de responder sucesso. Operações mutáveis que intersectem reserva ativa retornam `DRIVE_TRANSFER_IN_PROGRESS`, sem identificar a transferência concorrente. Transferências entre drives retornam HTTP 202 e continuam após fechar a interface; o cliente consulta somente seu `transferId` opaco.

## Upload e download

| Método e rota | Entrada | Resultado |
| --- | --- | --- |
| `POST {prefix}/uploads` | `multipart/form-data` com `files[]` e `parentFolderId` nulo ou válido | Resultado individual por arquivo, com nome final e estado. |
| `GET {prefix}/files/{possessionId}/download` | nenhum | Fluxo privado de um arquivo autorizado. |
| `POST {prefix}/exports` | `items[]` | `exportId`, `state`, `expiresAt`. |
| `GET {prefix}/exports/{exportId}` | nenhum | Estado seguro e prazo de disponibilidade. |
| `GET {prefix}/exports/{exportId}/download` | nenhum | Fluxo privado do pacote pronto, após nova autorização. |
| `POST {prefix}/exports/{exportId}/cancel` | nenhum | Cancela exportação própria ainda pendente; um pacote pronto continua disponível até expirar. |

## Projeções e erros

### Item de Drive

```json
{
  "id": 42,
  "kind": "file",
  "displayName": "Contrato.pdf",
  "parentFolderId": 7,
  "logicalSizeBytes": 203004,
  "detectedMimeType": "application/pdf",
  "modifiedAt": "2026-09-27T20:15:00Z",
  "capabilities": { "read": true, "edit": false, "trash": false }
}
```

Erros previstos usam o envelope seguro da plataforma. Códigos principais: `DRIVE_WORKSPACE_UNAVAILABLE`, `DRIVE_LOCATION_NOT_FOUND`, `DRIVE_ACCESS_DENIED`, `DRIVE_NAME_CONFLICT_RESOLVED`, `DRIVE_UPLOAD_LIMIT_EXCEEDED`, `DRIVE_EXPORT_LIMIT_EXCEEDED`, `DRIVE_EXPORT_NOT_READY` e `DRIVE_EXPORT_EXPIRED`.

Para o catálogo e transferências, códigos adicionais são `DRIVE_TRANSFER_INVALID`, `DRIVE_TRANSFER_IN_PROGRESS`, `DRIVE_TRANSFER_CANCELLED`, `DRIVE_TRANSFER_FAILED` e `DRIVE_TRANSFER_STALE`. Nenhum deles revela nome, titular, caminho, item ou contexto da operação de terceiro.

O upload sempre devolve HTTP 200 quando a localização é autorizada e uma lista está estruturada corretamente. Cada `results[]` traz `clientIndex`, `state` (`STORED` ou `REJECTED`) e, no sucesso, o item seguro criado; na rejeição, somente `error.code`. Limites, MIME real, nome inválido ou falha de ingestão de um item não criam posse ativa daquele item nem desfazem os demais. A autorização do destino é repetida antes da posse ser criada.

O download transmite apenas uma posse `WORKSPACE` ativa como anexo, com `Content-Type` detectado e `X-Content-Type-Options: nosniff`. A sessão, o contexto e a relação de leitura são revalidados imediatamente antes da abertura do stream; item removido, na lixeira, sem bytes privados ou com acesso revogado devolve o mesmo erro seguro de localização indisponível. A resposta não inclui URL pública, backend, hash, caminho ou chave física.
