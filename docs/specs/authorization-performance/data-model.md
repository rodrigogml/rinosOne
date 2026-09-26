# Modelo de Dados: Performance de Autorização

## `auth_policy_version`

| Campo | Tipo | Restrição |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK |
| `scope`, `id_tenant` | contexto | tenant somente quando TENANT |
| `subject_fingerprint` | VARCHAR(128) | identificador seguro do contexto afetado |
| `version` | BIGINT UNSIGNED | monotônico |
| `updated_at` | TIMESTAMP | controle de invalidação |

Entradas de cache não são dados de domínio nem migração obrigatória. A chave contém a versão e pode ser descartada a qualquer momento.
