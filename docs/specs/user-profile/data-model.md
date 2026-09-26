# Modelo de dados — Perfil do usuário

## Dados persistidos

| Dado | Armazenamento | Observação |
| --- | --- | --- |
| Nome | `user.displayName` existente | Editável pelo dono da conta. |
| Avatar atual | `file_systemBinding` | Chave `USER_PROFILE_AVATAR`, no máximo uma binding por usuário. |
| Arquivo final | Fundação de arquivos | Posse `SYSTEM_MANAGED`, invisível ao futuro drive. |
| Consumo | `file_ownerUsage.systemManagedBytes` | Também compõe o total lógico do usuário. |

Não será criada tabela de perfil dedicada nesta fase.

## Dados transitórios de recorte

`avatarCropRequest` existe apenas durante a requisição multipart:

| Campo | Regra |
| --- | --- |
| `image` | JPEG, PNG ou WebP; máximo 10 MB. |
| `cropX` | Número normalizado entre 0 e 1. |
| `cropY` | Número normalizado entre 0 e 1. |
| `cropSize` | Fração positiva, dentro dos limites da imagem. |

Antes de persistir, o servidor obtém largura e altura da origem. Se uma das dimensões for menor que 400 px, rejeita o pedido; não há upscale. O recorte final sempre é reamostrado para 400 × 400 px.
