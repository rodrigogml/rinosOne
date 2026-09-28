# Contrato de API — Rinos Drive

Todos os contratos exigem sessão autenticada, retornam JSON em camelCase e aplicam autorização no servidor para cada localização ou item. Nenhuma resposta contém backend, caminho físico, hash, chave de armazenamento ou URL pública.

## Alvos de workspace

| Alvo | Prefixo | Contexto resolvido |
| --- | --- | --- |
| Pessoal | `/api/v1/drive/personal` | Usuário autenticado. |
| Work | `/api/v1/tenants/{tenantId}/drive` | Tenant ativo e membership autorizada da aba. |

O `tenantId` é validado contra o contexto da requisição; ele não basta para conceder acesso.

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

## Upload e download

| Método e rota | Entrada | Resultado |
| --- | --- | --- |
| `POST {prefix}/uploads` | `multipart/form-data` com `files[]` e `parentFolderId` nulo ou válido | Resultado individual por arquivo, com nome final e estado. |
| `GET {prefix}/files/{possessionId}/download` | nenhum | Fluxo privado de um arquivo autorizado. |
| `POST {prefix}/exports` | `items[]` | `exportId`, `state`, `expiresAt`. |
| `GET {prefix}/exports/{exportId}` | nenhum | Estado seguro, progresso e prazo. |
| `GET {prefix}/exports/{exportId}/download` | nenhum | Fluxo privado do pacote pronto, após nova autorização. |
| `DELETE {prefix}/exports/{exportId}` | nenhum | Cancela ou descarta exportação própria ainda disponível. |

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

O upload sempre devolve HTTP 200 quando a localização é autorizada e uma lista está estruturada corretamente. Cada `results[]` traz `clientIndex`, `state` (`STORED` ou `REJECTED`) e, no sucesso, o item seguro criado; na rejeição, somente `error.code`. Limites, MIME real, nome inválido ou falha de ingestão de um item não criam posse ativa daquele item nem desfazem os demais. A autorização do destino é repetida antes da posse ser criada.

O download transmite apenas uma posse `WORKSPACE` ativa como anexo, com `Content-Type` detectado e `X-Content-Type-Options: nosniff`. A sessão, o contexto e a relação de leitura são revalidados imediatamente antes da abertura do stream; item removido, na lixeira, sem bytes privados ou com acesso revogado devolve o mesmo erro seguro de localização indisponível. A resposta não inclui URL pública, backend, hash, caminho ou chave física.
