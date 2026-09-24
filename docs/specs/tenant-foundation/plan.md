# Plano de Implementação: Fundação de Tenants

**Feature**: `tenant-foundation` | **Data**: 2026-09-24 | **Spec**: [spec.md](spec.md)

## Resumo

Implementar o plano de controle de tenants no schema principal, provisionar um schema isolado por tenant e adicionar um contexto explícito, apenas em memória e por aba, à web autenticada. A API continuará sendo a autoridade para criação, associação, disponibilidade e revalidação contextual; a web compõe o workspace pessoal com os recursos contextuais válidos.

## Contexto Técnico

**Linguagens/versões**: PHP 8.2+ e Laravel 12 no backend; TypeScript 5.7, Vue 3.5 e Pinia 4 na web.
**Dependências principais**: Laravel, Eloquent, fila persistida, Axios, Vue I18n e design system próprio.
**Armazenamento**: MySQL; schema global `rinosone`, schemas `rinosone_{tenantId}`, sessões e fila no schema global.
**Testes**: PHPUnit, Vitest, Playwright, verificação de tipos e build de produção.
**Plataforma-alvo**: web responsiva em navegadores modernos; API JSON versionada para consumidores futuros.
**Tipo de projeto**: monólito modular com SPA web e API.
**Comportamento de execução**: a validação de contexto e a listagem não bloqueiam a interface; o provisionamento informa progresso de forma assíncrona. Metas numéricas de desempenho serão definidas junto aos cenários de carga e à capacidade de cada ambiente.
**Restrições**: sessão de autenticação server-side, tenant nunca persistido nela, credenciais reais por ambiente, credencial de provisionamento separada e nenhum módulo de negócio nesta fase.
**Escopo**: criar, preparar, ativar, desabilitar e selecionar tenants; uma única associação `OWNER` por criação.

**Política de retentativa de provisionamento**: até três tentativas automáticas para falhas transitórias de conexão, indisponibilidade temporária ou bloqueio de banco, aguardando por padrão 1, 5 e 15 minutos. Permissão insuficiente, identificador físico inválido e migration incompatível falham definitivamente. Limite, intervalos e classificação operacional ficam configuráveis por ambiente. Limites de quantidade de tenants por usuário ou origem estão explicitamente adiados para a futura feature de limites e contratação.

## Arquitetura das Superfícies de Interação

**Catálogo de superfícies**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md).
**Aplicabilidade de Interface Design**: REQUIRED — o seletor, a criação, estados de preparação e a identificação persistente do tenant mudam uma superfície humana responsiva.

| Surface ID | Cobertura da feature | Decisão tecnológica | Módulo/repositório | Notas |
| --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | FULL | Vue 3, TypeScript e navegador | `resources/js`, `resources/css`, `resources/views` | Reutiliza casca autenticada, avatar e tokens; introduz contexto apenas em memória por aba. |
| API-HTTP-V1 | PARTIAL | PHP, Laravel e JSON | `app`, `routes/api`, `app/Http` | Criação, listagem, validação de contexto e disponibilidade; futuros módulos adotam o prefixo contextual. |

## Constitution Check

*GATE: aprovado antes do Phase 0 e rechecado após o desenho.*

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental e escopo autorizado | PASS | Cria somente proprietário inicial e estrutura necessária; membros, permissões e módulos continuam adiados. |
| II. Fronteira API e domínio independente da interface | PASS | A seleção é validada por contrato JSON; regras não pertencem a componentes web. |
| III. Identidade e acesso seguros por padrão | PASS | Todo contexto revalida usuário, vínculo e estado; identificador conhecido não concede acesso. |
| IV. Dados mínimos, sessões controladas e configuração segura | PASS | Contexto não entra na sessão; schemas e credenciais seguem separação por ambiente. |
| V. Mudanças verificáveis e documentação alinhada | PASS | Contratos, quickstart e cenários backend, frontend e E2E são definidos antes das tarefas. |

## Desenho da Arquitetura

### Fluxo de criação e provisionamento

1. A API valida usuário e nome, reserva a intenção idempotente, cria `tenant`, `tenantMembership(OWNER)` e `tenantProvisioning(QUEUED)` na transação global.
2. Após a confirmação, a fila persistida executa o provisionamento com a credencial própria de infraestrutura.
3. O worker deriva e valida o nome físico do schema a partir do ULID, cria o schema e aplica exclusivamente o catálogo de migrations de tenant.
4. Somente após confirmar a versão esperada, o worker marca a operação `SUCCEEDED` e o tenant `ACTIVE`.
5. Em falha, o tenant e a operação ficam `FAILED`, sem poder iniciar contexto; uma retentativa controlada usa o mesmo tenant e nunca cria outro schema.

### Resolução de contexto

1. A web mantém uma fotografia mínima do contexto no store em memória da aba.
2. Ao selecionar, chama o contrato de contexto e só atualiza a fotografia após resposta válida.
3. Troca ou encerramento limpa stores, dados temporários e requisições contextuais da aba antes de expor o novo estado.
4. Toda operação de tenant recebe `tenantId` explicitamente na rota e o backend resolve usuário, associação e estado antes de abrir a conexão dinâmica daquele tenant.
5. Rotas pessoais não recebem nem usam tenant; a ausência de contexto nunca faz fallback para outro tenant.

### Migrations e conexões

- O catálogo legado `database/migrations/` permanece inalterado.
- Novas migrations globais ficam em `database/migrations/core/` e são executadas junto do catálogo legado por um comando de migração global dedicado.
- Migrations de tenant ficam em `database/migrations/tenant/` e são aplicadas uma vez por schema de tenant por um comando dedicado.
- O runtime possui conexão global padrão e conexão de tenant construída somente a partir de um identificador validado.
- A credencial de provisionamento é uma conexão independente, configurada exclusivamente por ambiente, com permissões mínimas para criar e preparar schemas. A credencial de runtime não recebe permissão ampla de criação.
- Deploy executa migrations globais antes de disponibilizar a versão da aplicação; migrations de tenant percorrem tenants ativos e inativos de forma isolada. Falha em um tenant o mantém indisponível e não autoriza uso parcial.

## Estrutura do Projeto

### Documentação desta feature

```text
docs/specs/tenant-foundation/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── tenant-context.md
└── interface-spec.md            # etapa de interface posterior
```

### Código-fonte afetado

```text
app/
├── Domain/                      # domínio de acesso existente; receberá o domínio Tenant
├── Http/
│   ├── Controllers/Api/V1/       # controladores da API versionada
│   ├── Middleware/               # restauração de autenticação e futuro resolvedor contextual
│   └── Requests/                 # validações de fronteira
├── Models/                       # modelos Eloquent globais
├── Services/                     # serviços de aplicação e ciclo de vida
└── Console/Commands/             # comandos operacionais
database/
├── migrations/                   # catálogo global legado, preservado
├── migrations/core/              # novas migrations globais
└── migrations/tenant/            # migrations por tenant
resources/
├── js/design-system/             # casca, avatar e menus reutilizáveis
├── js/                           # estado, cliente HTTP e composição da SPA
└── css/design-system/            # tokens e estilos compartilhados
tests/
├── Feature/
├── Unit/
├── js/
└── e2e/
```

**Decisão estrutural**: o domínio de tenant será separado de acesso, mas integra-se à autenticação pela fronteira de contexto. Modelos globais ficam em `app/Models`; nenhum dado de domínio de tenant é introduzido antes de existir um módulo aprovado.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Colunas MySQL | camelCase | migrations e constraints | `database/migrations/` |
| Modelo de domínio | PascalCase e camelCase | testes unitários | `app/Domain/` |
| DTO/backend | camelCase | Form Requests e serviços | `app/Http/` e `app/Services/` |
| Payload JSON | camelCase | requests, responses e contrato | `contracts/tenant-context.md` |
| Erro JSON | camelCase | código seguro e mensagem localizada | `contracts/tenant-context.md` |
| Tipos da web | camelCase | parser de resposta no cliente | `resources/js/` |
| Parâmetro de rota | `tenantId` ULID | validação de rota e resolvedor contextual | `routes/api/` |

**Camada de mapeamento (DB ↔ DTO)**: modelos Eloquent representam dados globais; serviços de tenant criam respostas explícitas e os controladores somente adaptam HTTP. A interface traduz respostas do cliente HTTP para o store de contexto; ela não transforma estado de tenant em autorização.

**Validação de schema**: requests são validados no backend; responses são verificados por testes de contrato no backend e por parser de resposta no cliente antes de atualizar o contexto da aba.

**Envelope de erro**: toda falha prevista desta feature usa `{ "error": { "code": "SAFE_CODE", "message": "texto seguro" } }`. Erros de validação podem acrescentar `fields` com chaves de campo e mensagens seguras. Nenhum envelope inclui schema, host, SQL, credencial, chave de idempotência ou detalhe interno.

## Validação Planejada

- Testes unitários para derivação de schema, máquina de estados, idempotência e decisão de seleção.
- Testes de feature para criação, associação `OWNER`, transações, falhas de provisionamento, indisponibilidade e revalidação de cada operação contextual.
- Testes de integração MySQL descartáveis para criação de schema, catálogo de migrations e separação de credenciais.
- Testes de interface para avatar de tenant, seletor, estados de criação e limpeza de store contextual.
- Testes E2E para criação, troca em uma aba, isolamento entre abas, perda de contexto em reinício e preservação das funções pessoais.

## Complexity Tracking

Nenhuma violação da Constituição foi identificada. A conexão dinâmica e o worker de provisionamento são complexidade necessária para o isolamento físico já aprovado; permanecem confinados à fundação de tenant e não antecipam módulos.
