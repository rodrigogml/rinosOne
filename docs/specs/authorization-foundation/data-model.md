# Modelo de Dados: Fundação de Autorização

Todos os registros deste documento pertencem ao schema global `rinosone` e usam `BIGINT UNSIGNED` como identidade persistente. As colunas físicas seguem a convenção legada do schema core (`idRole`, `idTenant`, `createdAt`); DTOs e payloads externos usam `camelCase`. A fundação não cria FK do core para recursos localizados em schemas de tenant.

## Entidade: `auth_permission`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identidade persistente. |
| `key` | VARCHAR(160) | obrigatório, único | Ação de negócio estável, como `tenant.availability.manage`. |
| `displayName` | VARCHAR(160) | obrigatório | Nome administrativo. |
| `description` | TEXT | obrigatório | Finalidade e limite da action. |
| `scope` | VARCHAR(16) | obrigatório | `PLATFORM`, `PERSONAL` ou `TENANT`. |
| `systemManaged` | BOOLEAN | obrigatório | Impede alteração administrativa de permission gerenciada. |
| `active` | BOOLEAN | obrigatório | Permission inativa não concede acesso. |
| `createdAt`, `updatedAt` | TIMESTAMP | obrigatórios | Auditoria temporal. |

## Entidade: `auth_role`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identidade persistente. |
| `idTenant` | BIGINT UNSIGNED | nulo para role global; FK para `tenant` quando a role TENANT é própria | Delimita a propriedade de role customizada por organização. |
| `key` | VARCHAR(160) | obrigatório | Chave estável no seu contexto. |
| `displayName` | VARCHAR(160) | obrigatório | Nome editável para administração futura. |
| `scope` | VARCHAR(16) | obrigatório | Esfera única compatível com suas permissions. |
| `type` | VARCHAR(16) | obrigatório | `SYSTEM`, `BUILTIN` ou `CUSTOM`. |
| `systemManaged` | BOOLEAN | obrigatório | Protege composição e ciclo de vida da role. |
| `active` | BOOLEAN | obrigatório | Role inativa não concede acesso. |
| `createdAt`, `updatedAt` | TIMESTAMP | obrigatórios | Auditoria temporal. |

Roles `SYSTEM` e roles reutilizáveis fornecidas pela plataforma são globais (`idTenant` nulo). Uma role `CUSTOM` TENANT pode pertencer a um único tenant (`idTenant` obrigatório nesse caso) e somente pode ser atribuída a pessoa ou grupo do mesmo tenant. `tenant.administrator` é uma role global `SYSTEM`, TENANT e protegida. Cada tenant deve possuir ao menos uma atribuição direta, ativa e associada a uma membership ativa para ela. A composição inclui todas as permissions TENANT do catálogo, inclusive as registradas futuramente.

## Entidade: `auth_role_permission`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `idRole` | BIGINT UNSIGNED | PK/FK para `auth_role` | Role concedente. |
| `idPermission` | BIGINT UNSIGNED | PK/FK para `auth_permission` | Permission concedida. |

O serviço impede associação entre role e permission de esferas diferentes.

## Entidade: `auth_group`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identidade persistente. |
| `idTenant` | BIGINT UNSIGNED | nulo fora de TENANT; FK quando aplicável | Contexto organizacional. |
| `displayName` | VARCHAR(160) | obrigatório | Nome administrativo. |
| `scope` | VARCHAR(16) | obrigatório | Esfera do grupo. |
| `active` | BOOLEAN | obrigatório | Grupo inativo não concede acesso. |
| `createdAt`, `updatedAt` | TIMESTAMP | obrigatórios | Auditoria temporal. |

## Relações de grupo e assignment

| Relação | Campos principais | Garantia |
| --- | --- | --- |
| `auth_group_user` | `idGroup`, `idUser` | Pessoa recebe roles do grupo ativo. |
| `auth_group_group` | `idParentGroup`, `idChildGroup` | Herança entre grupos compatíveis, sem ciclo. |
| `auth_role_assignment` | `idRole`, `idUser`, `idTenant` opcional, `state` | Concessão direta para pessoa; tenant obrigatório apenas na esfera TENANT. |
| `auth_group_role_assignment` | `idRole`, `idGroup`, `idTenant`, `state` | Concessão para grupo. |

Todos os vínculos TENANT devem referenciar o mesmo tenant. A remoção ou inativação de qualquer elemento invalida sua contribuição na próxima decisão.

Uma pessoa vinculada a um grupo filho recebe as roles concedidas diretamente ao grupo filho e a todos os seus grupos ancestrais ativos. A resolução sobe de filho para pai e é interrompida quando encontra um grupo inativo.

## Entidade: `auth_audit_event`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identidade do evento. |
| `occurredAt` | TIMESTAMP | obrigatório | Momento da alteração. |
| `idActorUser` | BIGINT UNSIGNED | nulo para automação | Agente responsável. |
| `idTenant` | BIGINT UNSIGNED | nulo fora de TENANT | Contexto aplicável. |
| `operation` | VARCHAR(80) | obrigatório | Operação de segurança estável. |
| `targetType`, `targetId` | VARCHAR(80), BIGINT UNSIGNED | obrigatórios | Alvo da mudança. |
| `before`, `after` | JSON | nulos quando não aplicáveis | Diferença segura e sem segredos. |
| `correlationId` | VARCHAR(100) | nulo | Correlação com a operação. |

Eventos não possuem atualização ou remoção pelo aplicativo. A rotina diária de retenção remove somente eventos vencidos conforme o prazo configurado, de 90 dias por padrão.

## Decisão e integridade

```text
principal + permission + esfera + tenant opcional
        -> membership válida, quando TENANT
        -> assignments diretos e por grupos ativos
        -> role e permission ativas e compatíveis
        -> ALLOW ou DENY
```

Restrictions, relações com recursos e atributos contextuais não participam desta versão do cálculo.
