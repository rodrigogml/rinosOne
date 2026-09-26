# Modelo de Dados: Restrições de Autorização

## `auth_restriction`

| Campo | Tipo | Restrição |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `idPermission` | BIGINT UNSIGNED | FK core, obrigatório |
| `idUser` / `idGroup` | BIGINT UNSIGNED | exatamente um sujeito; validado pelo serviço transacional |
| `scope` | VARCHAR(16) | PLATFORM, PERSONAL ou TENANT |
| `idTenant` | BIGINT UNSIGNED | obrigatório somente em TENANT |
| `startsAt`, `endsAt` | TIMESTAMP | início inclusivo, fim exclusivo; nulos permitidos |
| `active` | BOOLEAN | somente ativa contribui |
| `createdAt`, `updatedAt` | TIMESTAMP | auditoria temporal |

O serviço garante compatibilidade de permission, sujeito, grupo e tenant. Uma exclusão é lógica ou preserva antes/depois no evento append-only de auditoria.
