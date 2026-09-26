# Plano de Implementação: Restrições de Autorização

## Resumo

Adicionar negação explícita por pessoa ou grupo como etapa imutável da porta de decisão. A restriction é um vínculo global do core, compatível com uma permission e esfera, e é avaliada antes de qualquer grant, inclusive `tenant.administrator`.

## Contexto Técnico

Laravel/PHP, MySQL no schema core, API JSON versionada e Vue 3/TypeScript. A feature depende da fundação com roles, grupos, auditoria e contrato `check`. Não cria tela nesta entrega; a gestão web pertence a `authorization-administration`.

## Arquitetura das Superfícies de Interação

**Aplicabilidade de Interface Design:** N/A — a API e o motor passam a reconhecer restrictions; não há interação humana nova nesta entrega.

| Surface ID | Cobertura | Tecnologia | Notas |
| --- | --- | --- | --- |
| API-HTTP-V1 | FULL | Laravel e JSON | Operações internas protegidas e decisão atualizada. |
| SURF-WEB-ACCESS | PARTIAL | Vue 3 | Reflete apenas capabilities já calculadas. |
| SURF-WEB-ADMIN | DEFERRED | Vue 3 | Administração pertence à feature de administração. |

## Constitution Check

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental | PASS | Sem relation, política contextual ou interface administrativa antecipada. |
| II. API e domínio independentes | PASS | Serviço de domínio atrás de endpoints/Policies. |
| III. Acesso seguro por padrão | PASS | Explicit deny e nova decisão por operação. |
| IV. Dados e configuração segura | PASS | Auditoria sem segredos e nenhuma decisão em sessão. |
| V. Mudanças verificáveis | PASS | Testes de precedência, escopo e vigência previstos. |
| VI. BIGINT e referências | PASS | Tabelas core usam BIGINT UNSIGNED e não referenciam recursos de tenant. |

## Desenho da Arquitetura

1. `auth_restriction` referencia permission, sujeito direto ou grupo, esfera, tenant quando aplicável, estado e vigência.
2. O resolvedor de grupos retorna subjects diretos e herdados ativos.
3. A porta `check` valida contexto e consulta restrictions aplicáveis antes de grants; qualquer uma nega com motivo seguro.
4. Escritas transacionais registram `auth_audit_event`; remoção lógica preserva histórico.

## Estrutura do Projeto

```text
app/Domain/Authorization/Restriction/
app/Services/Authorization/
app/Http/Controllers/Api/
database/migrations/core/
tests/Unit/Authorization/
tests/Feature/Authorization/
docs/specs/authorization-restrictions/
```

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| DB | `auth_*` para tabelas e `camelCase` para colunas | migrations e constraints | `database/migrations/core/` |
| DTO/JSON | camelCase | Form Request e testes de contrato | `contracts/restrictions.md` |
| Serviço | PascalCase/camelCase | PHPUnit | `app/Services/Authorization/` |

O mapper DB–DTO fica no serviço de autorização; controllers não consultam tabelas de restrictions diretamente.

## Validação Planejada

- Grant válido com restriction direta ou herdada sempre nega.
- Isolamento entre esfera/tenant e fronteiras de vigência inclusiva/exclusiva.
- Revogação, desativação e auditoria na mesma transação.
- Contrato de `check` preserva motivos seguros e capabilities não são autoridade.
