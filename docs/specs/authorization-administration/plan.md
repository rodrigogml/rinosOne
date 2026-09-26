# Plano de Implementação: Administração de Autorização

## Resumo

Entregar API e web responsiva para administração protegida de memberships, grupos, roles, assignments e restrictions, além de acesso efetivo, `explain` seguro e consulta de auditoria. Toda operação usa a mesma porta de autorização e preserva o último administrador direto do tenant.

## Contexto Técnico

Laravel/PHP e API JSON versionada com Vue 3/TypeScript no workspace. Depende de fundação, restrictions e resource authorization; políticas avançadas permanecem fora da tela inicial de administração.

## Arquitetura das Superfícies de Interação

**Aplicabilidade de Interface Design:** REQUIRED — há navegação, tabelas, formulários, confirmações e leitura de dados de segurança.

| Surface ID | Cobertura | Tecnologia | Notas |
| --- | --- | --- | --- |
| API-HTTP-V1 | FULL | Laravel/JSON | CRUD protegido, effective access, explain e audit. |
| SURF-WEB-ADMIN | FULL | Vue 3/TypeScript | Administração por tenant/esfera permitida. |
| SURF-WEB-ACCESS | DEFERRED | Vue 3/TypeScript | Atualização visual fora da administração fica para entrega posterior. |

## Constitution Check

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental | PASS | Sem gestão web de PLATFORM, delegação ou aprovação. |
| II. API e domínio independentes | PASS | API explícita; UI consome DTOs e não aplica regra. |
| III–IV. Segurança e dados | PASS | Escopo administrativo, anti-enumeração, dados mínimos e auditoria. |
| V. Verificabilidade | PASS | Contrato, E2E e acessibilidade previstos. |
| VI. Identidades e referências | PASS | IDs BIGINT; consultas não introduzem FK core→tenant. |

## Desenho da Arquitetura

1. Facade administrativa valida permission, esfera e tenant antes de chamar serviços de escrita.
2. Serviços transacionais preservam compatibilidade e a invariável do último `tenant.administrator`, escrevendo auditoria para sucesso e recusa relevante.
3. `EffectiveAccessQuery` e `AuthorizationExplainService` projetam somente fatores visíveis ao administrador solicitante.
4. Controllers expõem DTOs camelCase; Vue atualiza stores após mutação e trata capability como UX.

## Estrutura do Projeto

```text
app/Domain/Authorization/Administration/
app/Services/Authorization/Administration/
app/Http/Controllers/Api/Authorization/
resources/js/workspace/authorization/
resources/js/design-system/
routes/api.php
tests/Feature/Authorization/Administration/
docs/specs/authorization-administration/
```

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| DB | snake_case | serviços e migrations existentes | core authorization tables |
| API/DTO/TS | camelCase | Form Request, contrato e parser | `contracts/administration-api.md` |
| Rotas | kebab-case + IDs positivos | router e testes | `routes/api.php` |

O mapper fica nas queries/facades administrativas; tipos TypeScript espelham o contrato e recebem teste de parser.

## Validação Planejada

- Escrita fora de escopo falha sem alteração parcial.
- Explain/effective access não expõem terceiros nem bypassam restriction.
- Cada interação crítica é validada por teclado, leitor de tela e viewport responsivo.
- E2E cria, concede, consulta e revoga, confirmando efeito na operação real.
