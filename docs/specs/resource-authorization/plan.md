# Plano de Implementação: Autorização por Recurso

## Resumo

Estender a porta de decisão com referência tipada de recurso e relations auditáveis, sem transformar recursos em permissions. O primeiro adaptador protege pastas/arquivos pessoais; módulos de tenant integram-se registrando seus tipos de recurso e relations permitidas.

## Contexto Técnico

Laravel/PHP, MySQL e API JSON; workspace Vue 3 existente. Depende de fundação e restrictions. O core mantém a autorização genérica e não cria FK para tabelas dos schemas de tenant; adaptadores de módulo comprovam existência e pertencimento do recurso.

## Arquitetura das Superfícies de Interação

**Aplicabilidade de Interface Design:** REQUIRED — o workspace passa a apresentar itens e ações filtrados por autorização.

| Surface ID | Cobertura | Tecnologia | Notas |
| --- | --- | --- | --- |
| API-HTTP-V1 | FULL | Laravel/JSON | Check com recurso, lote e listagem. |
| SURF-WEB-ACCESS | PARTIAL | Vue 3/TypeScript | Exibe itens e ações autorizadas; editor é adiado. |
| SURF-WEB-SHARING | DEFERRED | Vue 3/TypeScript | Gestão de compartilhamento virá com módulo de arquivo. |

## Constitution Check

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental | PASS | Núcleo genérico e dois adaptadores iniciais, sem editor genérico. |
| II. API e domínio independentes | PASS | Contrato tipado e adaptadores de recurso fora do controller. |
| III–IV. Segurança e dados | PASS | Anti-IDOR, default deny e auditoria segura. |
| V. Verificabilidade | PASS | Casos cruzados de tenant, listagem e revogação. |
| VI. Identidades e referências | PASS | Core armazena referência lógica; não possui FK para dados de tenant. |

## Desenho da Arquitetura

1. `ResourceReference(type, id, scope, tenantId?)` é validada por um registry de adaptadores de recurso.
2. `auth_resource_relation` armazena subject, tipo/referência lógica, relation, escopo, tenant, estado e auditoria; IDs usam BIGINT UNSIGNED quando persistidos.
3. A decisão aplica contrato canônico: contexto, restriction, grant, relation exigida e allow.
4. Repositórios de módulos fornecem consultas de listagem autorizada; não há loop de decisão individual.

## Estrutura do Projeto

```text
app/Domain/Authorization/Resource/
app/Services/Authorization/Resource/
app/Infrastructure/Authorization/Resource/
app/Domain/FileStorage/
resources/js/workspace/
database/migrations/core/
tests/Feature/Authorization/
docs/specs/resource-authorization/
```

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| DB | `auth_*` para tabelas e `camelCase` para colunas | migration e FK/índices core | `database/migrations/core/` |
| Resource reference/API | camelCase | Form Request e parser TS | `contracts/resource-decision.md` |
| Rota | kebab-case, IDs numéricos | router/policy | `routes/api.php` |

Mapper de referência fica no adaptador do tipo de recurso; frontend não decide relação ou permissão.

## Validação Planejada

- Compartilhamento de pasta permite somente ação/recurso previstos.
- Recurso ou relation de outro tenant é negado sem enumeração.
- Listagem agregada iguala o subconjunto de referência.
- UI trata loading, vazio, stale e acesso negado com contrato real.
