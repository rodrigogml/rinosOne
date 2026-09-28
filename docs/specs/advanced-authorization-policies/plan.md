# Plano de Implementação: Políticas Avançadas de Autorização

## Resumo

Adicionar filtros condicionais, delegação limitada, elevação temporária aprovada, separação de funções, identidades de serviço e sincronização corporativa sem criar uma via de allow acima do motor existente. A política só reduz ou ativa grant já existente; restriction continua soberana.

## Contexto Técnico

Laravel/PHP, MySQL core, API JSON, jobs/scheduler e Vue 3. Depende das quatro features anteriores. Diretório corporativo é integrado por porta `GroupDirectoryProvider`, com adaptador inicial SCIM 2.0; outro fornecedor exige ADR e adaptador, não mudança no domínio.

## Arquitetura das Superfícies de Interação

**Aplicabilidade de Interface Design:** REQUIRED — administradores publicam políticas/delegações e usuários solicitam acesso temporário.

| Surface ID | Cobertura | Tecnologia | Notas |
| --- | --- | --- | --- |
| API-HTTP-V1 | FULL | Laravel/JSON | Avaliação, delegação, aprovação, service accounts e sync. |
| SURF-WEB-ADMIN | FULL | Vue 3/TypeScript | Política, delegação, requests, SoD e integração. |
| SURF-WEB-ACCESS | PARTIAL | Vue 3/TypeScript | Solicita e acompanha acesso temporário. |

## Constitution Check

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental | PASS | DSL fechada e adaptador de diretório; sem scripts arbitrários. |
| II. API e domínio independentes | PASS | Motor e portas independem de Vue/HTTP. |
| III–IV. Segurança e dados | PASS | Fail-safe, aprovação independente, credenciais fora de eventos. |
| V. Verificabilidade | PASS | Matriz de precedência, expiração e integração prevista. |
| VI. Identidades e referências | PASS | Entidades core BIGINT; recursos de tenant continuam lógicos/adaptados. |

## Desenho da Arquitetura

1. Catálogo fechado de condições versionadas; avaliadores recebem somente `context` confiável. Sem categorias de negócio, o catálogo inicia vazio.
2. Políticas/SoD são filtros da decisão após grants/relation e nunca geram allow por si.
3. Delegações e requests mantêm cadeia/origem, prazo e aprovação independente; jobs expiram estados e disparam invalidação.
4. Service accounts são subjects próprios com proprietário e credenciais segregadas.
5. `GroupDirectoryProvider` sincroniza grupos mapeados, aplica fail-safe e não pode remover o último administrador direto.
6. Implicações de permission, profundidade de grupos e restrictions por recurso reutilizam o catálogo e os adaptadores de recurso existentes, preservando a precedência de restriction.

## Decisões iniciais aprovadas

- Delegação e acesso temporário têm prazo máximo padrão de 30 dias; uma delegação não pode ser repassada na primeira versão.
- Aprovação exige outro administrador elegível do mesmo tenant; solicitante, destinatário e participante da cadeia de origem não podem aprovar.
- Service identities são criadas sem credencial materializada; a credencial será adicionada somente por integração que use armazenamento seguro.
- O adaptador SCIM é entregue desacoplado e desabilitado sem configuração de fornecedor; nenhuma chamada externa é executada por padrão.

## Estrutura do Projeto

```text
app/Domain/Authorization/Policy/
app/Domain/Authorization/Delegation/
app/Domain/Authorization/ServiceIdentity/
app/Services/Authorization/Advanced/
app/Infrastructure/IdentityDirectory/
app/Jobs/Authorization/
resources/js/workspace/authorization/
database/migrations/core/
docs/adr/
tests/Feature/Authorization/Advanced/
```

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| DB | snake_case | migrations/constraints | `database/migrations/core/` |
| API/DTO | camelCase | request, contract, TS parser | `contracts/advanced-policies-api.md` |
| SCIM adapter | provider mapping | contract tests | `app/Infrastructure/IdentityDirectory/` |

Segredos e credenciais são resolvidos por configuração segura, nunca por payload, evento de auditoria ou frontend.

## Validação Planejada

- Restrictions sempre vencem condições, delegação, cache e aprovação.
- Cadeia de delegação, SoD, prazo e independência de aprovador cobertos por testes de fronteira.
- Sincronização inválida/falha não amplia acesso nem remove último administrador.
- Contratos e fluxos web passam por API real, responsividade e acessibilidade.
