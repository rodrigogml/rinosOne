# Plano de Implementação: Fundação de Autorização

**Feature**: `authorization-foundation` | **Data**: 2026-09-25 | **Spec**: [spec.md](spec.md)

## Resumo

Consolidar uma porta única de autorização baseada em permissions, roles, groups e assignments para as esferas PLATFORM, PERSONAL e TENANT. Membership continuará delimitando somente a elegibilidade de contexto organizacional. O bootstrap existente de `tenant.administrator` será evoluído para o modelo completo, preservando a remoção de OWNER e a reavaliação em toda operação protegida.

## Contexto Técnico

**Linguagens/versões**: PHP 8.2+ e Laravel 12 no backend; TypeScript 5.7 e Vue 3.5 na web.
**Dependências principais**: Laravel, Eloquent, Policies/Gates, MySQL, Vue, Pinia e Axios.
**Armazenamento**: MySQL com schema global `rinosone`; dados de domínio organizacional permanecem em `rinosone_{tenantId}`.
**Testes**: PHPUnit, Vitest, Playwright, verificação de tipos e build de produção.
**Plataforma-alvo**: API JSON versionada e web responsiva em navegadores modernos.
**Tipo de projeto**: monólito modular Laravel com SPA web.
**Metas de desempenho**: a decisão simples não pode executar varredura de todos os usuários ou recursos; medição e cache distribuído ficam para a feature de performance.
**Restrições**: default deny, tenant explícito, sessões server-side sem chaveiro persistido, IDs BIGINT UNSIGNED, core sem FK para dados dos schemas de tenant e nenhuma relação por recurso nesta fase. Auditoria retida por 90 dias por padrão, configurável por ambiente e limpa diariamente.
**Escala/escopo**: catálogo comum de permissions, roles diretas e por grupos, grupos aninhados sem ciclos, auditoria e projeções mínimas de capability.

## Arquitetura das Superfícies de Interação

**Catálogo de superfícies**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md).
**Aplicabilidade de Interface Design**: N/A — esta feature não cria tela administrativa; a web apenas consome capabilities em superfícies já especificadas. A interface completa de gestão pertence a `authorization-administration`.

| Surface ID | Cobertura da feature | Decisão tecnológica | Módulo/repositório | Notas |
| --- | --- | --- | --- | --- |
| API-HTTP-V1 | FULL | PHP, Laravel e JSON | `app`, `routes/api`, `app/Http` | Adaptadores HTTP delegam a uma porta de decisão única. |
| SURF-WEB-ACCESS | PARTIAL | Vue 3, TypeScript e navegador | `resources/js` | Recebe capabilities de UX; nunca autoriza operações. |
| SURF-FUTURE-CONSUMERS | DEFERRED | API JSON versionada | `app`, `routes/api` | Sem contrato público de administração nesta fase. |

## Constitution Check

*GATE: aprovado antes do Phase 0 e rechecado após o desenho.*

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental e escopo autorizado | PASS | ReBAC, restrictions, cache e administração visual permanecem fora do escopo. |
| II. Fronteira API e domínio independente da interface | PASS | A decisão fica no domínio/serviço; Vue consome somente projeções. |
| III. Identidade e acesso seguros por padrão | PASS | Default deny, tenant explícito e nova decisão por operação são obrigatórios. |
| IV. Dados mínimos, sessões controladas e configuração segura | PASS | Nenhuma permission efetiva é persistida na sessão; auditoria exclui segredos. |
| V. Mudanças verificáveis e documentação alinhada | PASS | Modelo, contrato, quickstart e testes de autorização acompanham a implementação. |
| VI. Identidades numéricas e referências unidirecionais | PASS | Entidades usam BIGINT; relações de tenant ficam no core e o core não referencia recursos de schemas de tenant. |

## Desenho da Arquitetura

1. Um módulo chama `check(principal, permission, scope, tenant?)` por meio do contrato de autorização.
2. O serviço valida a permission e sua esfera. Para TENANT, valida também tenant ativo e membership ativa.
3. O serviço agrega assignments diretos e recebidos por grupos ativos, incluindo hierarquia válida de grupos.
4. A decisão permite somente quando encontra uma role ativa com a permission compatível; em qualquer outro caso, nega.
5. A mesma transação que altera roles, grupos, memberships ou assignments escreve o evento de auditoria correspondente.
6. Policies, Gates ou controllers HTTP atuam somente como adaptadores da porta de decisão; componentes web não reimplementam regras.

`tenant.administrator` é uma role de sistema TENANT. O catálogo aplica a ela toda permission TENANT, inclusive permissions novas; nenhuma permission de PLATFORM ou PERSONAL pode ser incluída. A remoção do último assignment direto elegível é rejeitada pela operação de escrita. A primeira role PLATFORM é atribuída diretamente no banco pela infraestrutura e nunca pelo fluxo de cadastro, autenticação ou criação de tenant.

### Sequência de entrega

1. Consolidar catálogo, grants diretos, `check`, `tenant.administrator`, bootstrap de PLATFORM e auditoria transacional.
2. Adicionar groups com members pessoas e grants por grupo.
3. Adicionar groups aninhados, verificação de ciclo e resolução transitiva.
4. Adicionar rotina diária de retenção, projections de capability e toda a matriz de testes e contratos.

Essa ordem entrega autorização utilizável em cada incremento e preserva todos os requisitos aprovados para a feature.

## Estrutura do Projeto

### Documentação desta feature

```text
docs/specs/authorization-foundation/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
└── contracts/
    └── authorization-decision.md
```

### Código-fonte afetado

```text
app/
├── Domain/Authorization/          # valores e regras puras de autorização
├── Services/Authorization/        # porta de decisão, escrita e auditoria
├── Models/                        # entidades globais de autorização
├── Http/Controllers/Api/V1/       # adaptadores HTTP autorizados
└── Services/Tenant/               # cria membership e grant inicial em transação
config/authorization.php           # retenção de auditoria e valores operacionais seguros
database/migrations/core/          # tabelas e evolução global da autorização
resources/js/tenant/               # parser e store de capabilities de tenant
tests/
├── Unit/
├── Feature/
├── js/
└── e2e/
```

**Decisão estrutural**: a autorização é um módulo de domínio separado de Tenant. Tenant chama somente o bootstrap transacional e módulos consumidores dependem do contrato de decisão, não das tabelas físicas.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Colunas MySQL | camelCase; tabelas `auth_*` | migrations e constraints | `database/migrations/core/` |
| Modelos e serviços PHP | PascalCase e camelCase | PHPUnit | `app/Models`, `app/Services/Authorization` |
| Payload JSON | camelCase | requests, responses e testes de contrato | `docs/specs/authorization-foundation/contracts/` |
| Tipos e parser web | camelCase | TypeScript e parser da resposta | `resources/js/tenant/` |
| Parâmetro de rota | `tenantId` inteiro sem sinal | rota e resolvedor contextual | `routes/api`, `app/Services/Tenant` |

**Camada de mapeamento (DB ↔ DTO)**: modelos representam dados globais; o serviço de autorização retorna uma decisão de domínio e controladores projetam somente capabilities necessárias ao contrato HTTP.

**Validação de schema**: requests são validados no backend; responses de capability são verificadas por testes de contrato no backend e pelo parser TypeScript antes de atualizar a interface.

**Envelope de erro**: falhas previstas continuam no formato `{ "error": { "code": "SAFE_CODE", "message": "texto seguro" } }`; nenhuma resposta expõe grants de terceiros, SQL ou identificadores de recurso fora do contexto permitido.

**Configuração operacional**: `config/authorization.php` define `auditRetentionDays`, com padrão 90 e substituição por ambiente. A limpeza é agendada diariamente; a falha da rotina não autoriza, bloqueia ou altera decisões de acesso.

## Validação Planejada

- Testes unitários para compatibilidade de escopo, resolução de grants, grupos aninhados e detecção de ciclo.
- Testes de feature para default deny, isolamento entre tenants, atribuições diretas e por grupo, revogação sem login e último administrador.
- Testes de persistência para FKs, unicidade, integridade de escopo e auditoria transacional.
- Testes de contrato para projections de capability e negação segura.
- Testes de interface para ocultação de capability sem aceitar a interface como autoridade.
- Teste E2E de criação de tenant, capability administrativa e revalidação no endpoint real.

## Complexity Tracking

Nenhuma violação da Constituição foi identificada. A hierarquia de grupos e a auditoria transacional são complexidade necessária para as regras explicitamente aprovadas; ambas permanecem confinadas ao módulo de autorização.
