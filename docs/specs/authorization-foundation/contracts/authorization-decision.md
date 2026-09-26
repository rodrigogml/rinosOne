# Contrato Interno: Decisão de Autorização

## `check`

**Uso**: serviço de aplicação e Policies/Gates chamam uma única porta de decisão antes de uma operação protegida.

### Entrada

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| `principal` | sujeito de autorização | sim | Pessoa autenticada na fundação; extensível a identidade de serviço nas políticas avançadas. |
| `permissionKey` | string | sim | Permission ativa registrada. |
| `scope` | enum | sim | `PLATFORM`, `PERSONAL` ou `TENANT`, compatível com a permission. |
| `tenantId` | inteiro positivo | somente TENANT | Tenant ativo e membership ativa. |
| `resource` | referência tipada opcional | não | Recurso e tipo pertencentes à esfera e ao tenant da decisão, quando a permission exigir relação por recurso. |
| `context` | atributos confiáveis opcionais | não | Valores resolvidos pelo servidor para política contextual; entrada livre do cliente não pode ampliar acesso. |

### Saída

| Campo | Tipo | Descrição |
| --- | --- | --- |
| `allowed` | boolean | Resultado final da decisão. |
| `reasonCode` | string | Código seguro para observabilidade e futura explicação; não expõe grants de terceiros. |

### Regras

- Ausência de permission, grant, membership, tenant, recurso ou relação válida exigida pela permission resulta em `allowed = false`.
- Decisões PLATFORM e PERSONAL rejeitam contexto de tenant.
- A fundação implementa decisões para pessoa, permission, esfera e tenant; `resource` e `context` são extensões contratuais inativas até as respectivas features. Uma chamada que exija regra ainda não habilitada é negada, nunca ignorada.
- A ordem canônica é: validar esfera, sujeito, tenant e recurso; negar por restriction aplicável; resolver grants elegíveis; exigir relation quando a permission/recurso a exigir; aplicar separação de funções, vigência, aprovação, delegação e condições quando habilitadas; permitir somente se todos os controles aplicáveis forem satisfeitos.
- Restriction, falha de validação e política avançada não satisfeita nunca podem ser neutralizadas por role, relação, cache ou capability de interface.
- O resultado não deve ser persistido na sessão nem aceito de volta como prova de autorização.

## Capabilities de workspace

O backend projeta capabilities específicas da superfície por um único serviço de projeção. A capability `canManageAvailability` é sempre um booleano em resumos e contextos TENANT. Cada capability é derivada de uma decisão atual e só controla a experiência; a operação protegida chama `check` novamente.
