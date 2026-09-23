# Contrato de Interface: API de Acesso

Todos os payloads JSON usam `camelCase`. Respostas de solicitação de cadastro e de acesso sem senha são neutras: não confirmam se um e-mail possui conta. Quando uma operação autentica com êxito, ela cria a sessão server-side e envia o cookie de sessão seguro; quando a emissão tiver `rememberMe` selecionado, também envia a credencial persistente segura.

## Iniciar cadastro

**Method**: POST `/api/v1/auth/registrations`  
**Auth**: Não requerida

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| email | string | sim | e-mail válido |
| rememberMe | boolean | não | Solicita autenticação persistente após confirmação. |

### Response (202)

| Campo | Tipo | Descrição |
| --- | --- | --- |
| message | string | Confirmação neutra de que a instrução foi processada. |
| challengeId | string | Identificador opaco para a confirmação por código na mesma aba. Quando uma emissão estiver disponível, referencia essa emissão; nos demais casos, não é utilizável. Não é um segredo de acesso. |

## Concluir validação de e-mail

**Method**: POST `/api/v1/auth/email-verifications`  
**Auth**: Não requerida

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- |
| challengeId | string | sim | Emissão de validação ativa. |
| code | string | sim | Código numérico de 6 dígitos e uso único; a emissão é excluída após 3 erros por padrão. |
| displayName | string | sim | Nome de exibição não vazio. |

### Response (201)

| Campo | Tipo | Descrição |
| --- | --- | --- |
| user | object | Conta validada e pronta para acesso. |
| user.id | string | Identificador da conta. |
| user.displayName | string | Nome de exibição. |

A resposta cria a sessão autenticada. O link de validação entrega a mesma jornada de confirmação e consome a mesma emissão que o código.

## Concluir validação de e-mail por link

O link no e-mail abre uma rota web pública com `challengeId` e `token` opaco. A rota remove os parâmetros da URL antes de enviar esta operação e aplica política de referrer que não propaga a URL do link.

**Method**: POST `/api/v1/auth/email-verifications/link-confirmations`  
**Auth**: Não requerida

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| challengeId | string | sim | Emissão de validação ativa. |
| token | string | sim | Segredo opaco recebido somente no link. |
| displayName | string | sim | Nome de exibição não vazio. |

### Response (201)

Mesmo corpo e mesmos cookies da confirmação por código. O primeiro consumo válido por código ou link invalida integralmente a emissão.

## Definir senha

**Method**: PUT `/api/v1/auth/password`  
**Auth**: Requerida

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| password | string | sim | Mínimo de 6 caracteres e 2 de 4 categorias. |

### Response (204)

Sem corpo.

## Entrar por senha

**Method**: POST `/api/v1/auth/password-sessions`  
**Auth**: Não requerida

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| email | string | sim | e-mail válido |
| password | string | sim | não vazio |
| rememberMe | boolean | não | Solicita autenticação persistente. |

### Response (201)

| Campo | Tipo | Descrição |
| --- | --- | --- |
| user | object | Conta da sessão iniciada. |

## Solicitar acesso sem senha

**Method**: POST `/api/v1/auth/passwordless-sessions`  
**Auth**: Não requerida

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| email | string | sim | e-mail válido |
| rememberMe | boolean | não | Solicita autenticação persistente após confirmação. |

### Response (202)

| Campo | Tipo | Descrição |
| --- | --- | --- |
| message | string | Confirmação neutra de que a instrução foi processada. |
| challengeId | string | Identificador opaco para a confirmação por código na mesma aba. Quando uma emissão estiver disponível, referencia essa emissão; nos demais casos, não é utilizável. Não é um segredo de acesso. |

## Concluir acesso sem senha

**Method**: POST `/api/v1/auth/passwordless-sessions/confirmations`  
**Auth**: Não requerida

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| challengeId | string | sim | Emissão de acesso ativa. |
| code | string | sim | Código numérico de 6 dígitos e uso único; a emissão é excluída após 3 erros por padrão. |

### Response (201)

| Campo | Tipo | Descrição |
| --- | --- | --- |
| user | object | Conta da sessão iniciada. |

## Concluir acesso sem senha por link

O link mágico segue a mesma regra de remoção de parâmetros e de referrer da confirmação de e-mail.

**Method**: POST `/api/v1/auth/passwordless-sessions/link-confirmations`  
**Auth**: Não requerida

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| challengeId | string | sim | Emissão de acesso ativa. |
| token | string | sim | Segredo opaco recebido somente no link. |

### Response (201)

Mesmo corpo e mesmos cookies da confirmação por código. O primeiro consumo válido por código ou link invalida integralmente a emissão.

## Consultar sessão atual

**Method**: GET `/api/v1/auth/session`  
**Auth**: Requerida

### Response (200)

| Campo | Tipo | Descrição |
| --- | --- | --- |
| user | object | Conta da sessão atual. |
| user.id | string | Identificador público da conta. |
| user.displayName | string | Nome de exibição. |
| user.passwordDefined | boolean | Indica se a conta possui senha definida. |
| persistentAuthentication | boolean | Indica se a sessão atual está associada a uma credencial persistente. |

Não retorna identificadores de sessão, credenciais persistentes, dispositivos nem lista de outras sessões.

## Encerrar sessões

**Method**: DELETE `/api/v1/auth/session`  
**Auth**: Requerida

### Response (204)

Sem corpo.

**Method**: DELETE `/api/v1/auth/other-sessions`  
**Auth**: Requerida

### Response (204)

Sem corpo. A sessão que fez a solicitação permanece ativa. Sessões e autenticações persistentes de outros navegadores são revogadas.

## Erros

| Status | Código | Descrição |
| --- | --- | --- |
| 400 | INVALID_CREDENTIAL | Credencial, código ou link inválido, expirado, substituído ou já utilizado. |
| 403 | EMAIL_NOT_VERIFIED | A conta não está elegível ao acesso. |
| 422 | VALIDATION_ERROR | Dados de entrada não atendem às regras. |
| 429 | RATE_LIMITED | O limite temporário de emissão ou tentativa foi atingido. |
