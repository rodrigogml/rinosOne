# Plano de implementação: Interface unificada de acessos e permissões

**Feature**: `access-permissions-interface` | **Data**: 2026-09-28 | **Spec**: [spec.md](spec.md)

## Resumo

Evoluir a superfície de segurança existente para administrar acessos por contexto ativo, com uma experiência comum para `PERSONAL`, `TENANT` e `PLATFORM`. A entrega reutiliza a fundação de autorização e seus comandos, acrescenta projeções de leitura seguras para sujeitos, papéis, permissões, acesso efetivo e compartilhamentos, e substitui entradas por IDs técnicos por seleção legível e confirmação contextual.

## Contexto técnico

**Linguagem/versão**: PHP 8.2, TypeScript 5.7 e Vue 3.5
**Dependências principais**: Laravel 12, Vue 3, Pinia, Vue I18n, Axios e Vite 7
**Armazenamento**: MySQL core para autorização; workspace virtual já configurado para recursos
**Testes**: PHPUnit 11, Vitest 5 e Playwright 1.63
**Plataforma-alvo**: Aplicação Laravel/Vite em navegadores modernos, desktop, tablet e telefone
**Tipo de projeto**: Monólito web com API JSON versionada e SPA responsiva
**Metas de desempenho**: Em homologação, `context`, `subjects` e `roles` com página padrão de 25 itens têm p95 de até 500 ms para até 1.000 sujeitos ativos; `effective-access` e `explain` têm p95 de até 1 s. A interface não carrega o grafo completo de autorização de um contexto.
**Restrições**: BIGINT, separação de escopo, servidor como decisão final, credenciais de API exibidas uma única vez, sem SCIM e sem posse individual de recurso
**Escala/escopo**: Uma interface comum, três esferas, catálogo incremental de permissões de negócio e mecanismos avançados existentes

## Arquitetura das superfícies de interação

**Catálogo de superfícies**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Aplicabilidade de Interface Design**: REQUIRED — a feature altera navegação, hierarquia, consultas, formulários e confirmações de segurança.

| Surface ID | Cobertura | Decisão tecnológica | Módulo | Notas |
|---|---|---|---|---|
| SURF-WEB-ADMIN | FULL | Vue 3 + TypeScript + Vue I18n | `resources/js/authorization`, `resources/js/design-system` | Evolui a tela existente para painel contextual de participantes, papéis, explicação, auditoria e avançados. |
| SURF-WEB-SHARING | FULL | Vue 3 + TypeScript | `resources/js/authorization`, `resources/js/drive` | Painel comum de relações de recurso, invocado a partir do Drive ou da administração. |
| SURF-WEB-ACCESS | PARTIAL | Vue 3 + TypeScript | `resources/js/design-system`, `resources/js/workspace` | Entrada contextual e visibilidade condicionada por capability; não cria uma nova casca. |
| SURF-FUTURE-CONSUMERS | PARTIAL | Laravel 12 + JSON `/api/v1` | `app`, `routes/api` | Contratos contextuais reutilizáveis, sem criar novo cliente humano. |

## Constitution Check

*GATE: aprovado antes do Phase 0 e revalidado após o desenho.*

| Princípio | Status | Notas |
|---|---|---|
| I. Simplicidade incremental e escopo autorizado | PASS | Reutiliza fundação e não cria SCIM, posse por item ou novas permissões de negócio. |
| II. Fronteira API e domínio independente da interface | PASS | Projeções e comandos ficam em API JSON; Vue não decide autorização. |
| III. Identidade e acesso seguros por padrão | PASS | Contexto deriva da rota; erro seguro, confirmação e servidor protegem toda escrita. |
| IV. Dados mínimos, sessões controladas e configuração segura | PASS | Leituras projetam somente dados administrativos necessários; segredos permanecem fora das listas. |
| V. Verificabilidade e qualidade | PASS | Contrato, parser, testes de integração, E2E e acessibilidade são definidos no quickstart e interface spec. |
| VI. Identidades e referências BIGINT | PASS | Entidades persistidas e relações existentes usam BIGINT; DTOs rejeitam IDs inválidos. |

## Desenho técnico

1. Criar uma fachada de leitura contextual no domínio de autorização. Ela recebe exclusivamente o solicitante autenticado e o contexto já derivado da rota, aplica permissão de leitura e retorna uma projeção paginada, mínima e consistente.
2. Manter os serviços transacionais existentes para papéis, grupos, vínculos, restrições, delegações, solicitações e credenciais. Adaptadores de comando convertem a intenção da UI em chamadas compatíveis, preservando o último administrador de tenant e a invalidação de políticas.
3. Adicionar famílias explícitas de rotas para `PERSONAL` e `PLATFORM` e ampliar a família `TENANT`; não aceitar seleção de esfera no body. Compatibilidade das rotas de tenant existentes é preservada durante a migração.
4. Criar um cliente TypeScript contextual com parsers de forma estrita e um composable/store local à superfície. O cliente usa descritores de contexto derivados da `WorkspaceSurface` e nunca grava capability como verdade durável.
5. Dividir a composição Vue em painel de contexto, lista de participantes, catálogo de papéis, detalhe de acesso efetivo, auditoria, painel de compartilhamento e entrada progressiva para controles avançados. Componentes visuais existentes permanecem a base.
6. Para compartilhamento, criar adaptador de recursos que projeta `auth_resource_relation` e os recursos de Drive sem introduzir proprietário de item. O responsável do workspace é texto de contexto, não um vínculo editável de arquivo ou pasta.
7. Após qualquer mutação, invalidar/recarregar a projeção contextual. Em conflito, revogação concorrente ou contexto desatualizado, preservar somente formulário não sensível e exigir nova confirmação.

### Mapeamento da fundação contextual

| Esfera resolvida | Regra já existente | Capability projetada | Limite preservado |
|---|---|---|---|
| `PERSONAL` | O usuário autenticado é responsável pelo próprio workspace pessoal; relações de recurso definem o acesso de terceiros. | Ler acesso e administrar compartilhamentos do próprio workspace. | Não recebe tenant, não cria papel administrativo pessoal e não cria propriedade por item. |
| `TENANT` | `tenant.authorization.read/manage` exige tenant ativo e membership ativa. | Ler acesso; gerir papéis, compartilhamentos e controles avançados quando autorizado. | `tenantId` vem da rota e nunca do corpo. |
| `PLATFORM` | `platform.authorization.tenant.read/manage` é grant independente de plataforma, sem membership em tenant. | Supervisão de acesso e controles avançados de plataforma. | Não é reinterpretado como membership nem gera contexto de tenant. |

## Estrutura do projeto

### Documentação

```text
docs/specs/access-permissions-interface/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── contextual-access-administration-api.md
└── interface-spec.md
```

### Código-fonte

```text
app/Http/Controllers/Api/V1/Authorization/
app/Http/Requests/Authorization/
app/Services/Authorization/
app/Services/Authorization/Administration/
app/Services/Authorization/Resource/
resources/js/authorization/
resources/js/design-system/
resources/js/drive/
resources/js/workspace/
routes/api/authenticated.php
tests/Feature/Authorization/
resources/js/**/*.test.ts
tests/e2e/
```

**Decisão de estrutura**: a feature amplia os módulos reais `authorization`, `drive`, `workspace` e os controllers versionados já existentes. Não cria um módulo de aplicação paralelo nem mistura regra de domínio no design system.

## Convenções de borda

| Camada | Case style | Validação | Fonte da verdade |
|---|---|---|---|
| Colunas DB | camelCase no esquema atual | migrations e constraints existentes | `database/migrations/core/` |
| Backend DTO | camelCase | Form Requests, services e testes de feature | controllers e contratos desta feature |
| Frontend DTO | camelCase | parser estrito no cliente de autorização | `resources/js/authorization/` |
| Payload API | camelCase | request/response e testes de contrato | `contracts/contextual-access-administration-api.md` |
| URL e query | kebab-case, IDs numéricos | roteamento Laravel e testes | `routes/api/authenticated.php` |

**Mapper DB ↔ DTO**: serviços/fachadas em `app/Services/Authorization/Administration/` e adaptador de recurso em `app/Services/Authorization/Resource/` são responsáveis por projetar entidades persistidas para DTOs seguros.

**Validação de schema**: request é validado por Form Requests; response é garantida por testes de feature e consumida por parsers TypeScript, sem coerção silenciosa.

## Validação planejada

- Testes de feature cobrem cada família de rota, separação de contexto, anti-enumeração, conflito de último administrador, herança de compartilhamento e reconsulta após revogação.
- Uma medição reproduzível em homologação verifica p95 de 500 ms para projeções paginadas de 25 itens e de 1 s para consulta/explicação de acesso, no cenário de até 1.000 sujeitos ativos.
- Testes de unidade cobrem projeções, seleção de dados mínimos e parsers TypeScript, incluindo IDs inválidos, enum desconhecido e payload incompleto.
- E2E percorre os fluxos de atribuição de papel, explicação de acesso e compartilhamento em desktop e telefone, inclusive teclado, foco, offline e erro concorrente.
- `npm run type-check`, `npm test`, `npm run build`, `php artisan test` e E2E configurado são gates antes de marcar a feature como implementada.

## Complexity Tracking

Nenhuma violação da constituição foi necessária.
