# Contrato HTTP — Perfil do usuário

Todos os endpoints exigem sessão autenticada e operam exclusivamente sobre o usuário da sessão.

## Consultar perfil

`GET /api/v1/profile`

```json
{
  "user": {
    "id": 42,
    "displayName": "Ana Souza"
  },
  "avatar": {
    "available": true,
    "url": "/api/v1/profile/avatar",
    "updatedAt": "2026-09-26T14:30:00Z"
  }
}
```

## Alterar nome

`PATCH /api/v1/profile`

```json
{ "displayName": "Ana Sousa" }
```

Retorna o perfil atualizado. O nome validado atualiza de imediato os componentes de identidade que o consomem.

## Criar ou substituir avatar

`POST /api/v1/profile/avatar` como `multipart/form-data`:

| Campo | Tipo |
| --- | --- |
| `image` | arquivo de imagem aceito |
| `cropX` | decimal normalizado |
| `cropY` | decimal normalizado |
| `cropSize` | decimal normalizado |

Resposta `201 Created` com a mesma representação de perfil. O servidor produz e guarda apenas o arquivo final de 400 × 400 px.

## Obter avatar

`GET /api/v1/profile/avatar`

Entrega a imagem pelo fluxo privado. Retorna `404` quando o usuário não possui avatar; uma requisição sem sessão retorna `401`.

## Remover avatar

`DELETE /api/v1/profile/avatar`

Retorna `204 No Content`. A binding é desfeita e a posse gerenciada anterior é liberada no mesmo fluxo da fundação de arquivos.

## Falhas

| HTTP | Código | Situação |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Sessão ausente ou inválida. |
| 422 | `VALIDATION_ERROR` | Nome ou parâmetros de recorte inválidos. |
| 422 | `AVATAR_FORMAT_UNSUPPORTED` | Formato fora de JPEG, PNG e WebP. |
| 422 | `AVATAR_TOO_LARGE` | Arquivo superior a 10 MB. |
| 422 | `AVATAR_DIMENSIONS_TOO_SMALL` | Largura ou altura abaixo de 400 px. |
| 422 | `AVATAR_CROP_INVALID` | Região fora dos limites ou inconsistente. |
| 503 | `AVATAR_PROCESSING_UNAVAILABLE` | GD ou codec necessário indisponível no servidor. |
