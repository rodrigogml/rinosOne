# Modelo de Dados: Autorização por Recurso

## `auth_resource_type`

| Campo | Tipo | Restrição |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK |
| `key` | VARCHAR(120) | único, estável |
| `scope` | VARCHAR(16) | esfera compatível |
| `active` | BOOLEAN | inativo não autoriza |

## `auth_resource_relation`

| Campo | Tipo | Restrição |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK |
| `idResourceType` | BIGINT UNSIGNED | FK core |
| `resourceId` | BIGINT UNSIGNED | referência lógica, sem FK core→tenant |
| `relationKey` | VARCHAR(120) | declarada pelo tipo |
| `idUser` / `idGroup` | BIGINT UNSIGNED | exatamente um sujeito |
| `scope`, `idTenant` | contexto | tenant obrigatório em TENANT |
| `active` | BOOLEAN | relação efetiva |

O registry do adaptador confirma existência, pertencimento e actions permitidas. Eventos de relation seguem a auditoria append-only.
