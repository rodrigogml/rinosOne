# Modelo de Dados: Políticas Avançadas

| Entidade | Campos essenciais | Garantia |
| --- | --- | --- |
| `auth_policy` | BIGINT id, scope, tenant, type, version, active | condição fechada e versionada |
| `auth_policy_binding` | policy, permission/grant, resource qualifier opcional | política só filtra grant existente |
| `auth_delegation` | origem, delegador, destinatário, limites, vigência, state | cadeia acíclica e auditável |
| `auth_access_request` | solicitante, acesso, aprovador, decisão, expiração | aprovador independente |
| `auth_separation_rule` | escopo, conjunto incompatível, active | bloqueia concessão e decisão |
| `auth_service_identity` | BIGINT id, owner, finalidade, estado, vigência | subject não humano separado |
| `auth_directory_mapping` | provider, grupo externo, grupo interno, state | sync explícito e fail-safe |
| `auth_permission_implication` | permission de origem, permission implicada | grafo acíclico no mesmo escopo |
| `auth_restriction` (extensão) | resource type e resource id opcionais | deny limitado a recurso validado |

Todas as tabelas core usam `BIGINT UNSIGNED`; credenciais ficam em armazenamento seguro e não em registros de autorização/auditoria.
