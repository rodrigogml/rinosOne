# Plano Técnico — Cadastro de Pessoas por Organização

**Feature**: `person-registration` | **Data**: 2026-09-28 | **Spec**: [spec.md](spec.md)

## Resumo

Implementar o primeiro módulo de domínio organizacional para Pessoas físicas e jurídicas. O módulo usa o schema isolado da organização, referencia somente catálogos corporativos necessários e expõe um agregado de Pessoa com endereços, contatos, contas bancárias, chaves Pix e relacionamentos direcionais. A exclusão física é assistida por verificadores de uso e preservada por proteção de integridade como fallback.

## Contexto técnico

| Aspecto | Decisão |
| --- | --- |
| Aplicação | Laravel/PHP, Eloquent, MySQL, API JSON e Vue 3 já existentes. |
| Identidade | PKs `BIGINT UNSIGNED AUTO_INCREMENT`; identificadores de negócio são atributos e não substituem a PK. |
| Armazenamento | Schema isolado por organização; catálogo corporativo permanece em `rinosone`. |
| Interface | SPA responsiva única para desktop, tablet e telefone, com funcionalidade integral. |
| Testes | PHPUnit para domínio, persistência e API; Vitest e Playwright para cliente web e fluxos críticos. |
| Desempenho | A listagem inicial atende o objetivo de 95% em até 2 segundos em condições normais. |
| Segurança | Contexto de organização e decisão de autorização no backend precedem toda consulta ou mutação. |
| Retenção | Eventos de auditoria expiram após 90 dias por padrão configurável e são limpos diariamente por rotina explícita de Pessoas integrada ao Hub de Manutenções. |
| Operação de API | Paginação geral padrão 50/máximo 200, `Idempotency-Key` UUID v4 por 24 horas, 120 requisições autenticadas por minuto e corpo JSON máximo de 1 MiB; todos configuráveis por ambiente. |

## Arquitetura proposta

```text
Web responsiva ──> API versionada com contexto da organização
                         │
                         v
                PersonApplicationService
                  ├─ validação e normalização
                  ├─ repositório no schema da organização
                  ├─ referências de catálogos corporativos
                  ├─ projetor de relacionamentos opostos
                  ├─ verificador de uso para exclusão
                  └─ auditoria mínima de operações

Scheduler/HUB ──> rotina explícita de retenção de auditoria de Pessoas
```

## Componentes e responsabilidades

| Local existente | Responsabilidade planejada |
| --- | --- |
| `database/migrations/tenant` | Criar as tabelas, chaves, índices e relações descritos em [data-model.md](data-model.md) para cada schema organizacional. |
| `app/Domain` | Tipos de Pessoa, relacionamento, normalização, validações e regras de ciclo de vida. |
| `app/Services` | Operações transacionais do agregado, busca, duplicação, exclusão assistida, auditoria e retenção. |
| `app/Services/Maintenance` | Integração explícita da rotina de retenção de auditoria de Pessoas ao Hub, sem abstração genérica adicional. |
| `app/Infrastructure` | Conexão resolvida da organização, repositórios e consultas aos catálogos corporativos. |
| `app/Http/Controllers/Api/V1` e `app/Http/Requests` | Endpoints, validação de entrada e mapeamento seguro de erros conforme [people-api.md](contracts/people-api.md). |
| `routes/api` | Grupo de rotas autenticadas contextualizado pela organização. |
| `resources/js` | Cliente de API, tipos, estado e superfície responsiva de Pessoas. |
| `resources/css` | Adaptações de layout dentro do sistema de design e tokens existentes. |
| `tests` | Cobertura unitária, de feature, integração de schema, contrato e interface. |

## Fluxos técnicos principais

### Criar e atualizar

1. A API confirma usuário, contexto ativo da organização e permissão da operação.
2. A aplicação resolve a conexão do schema da organização antes de consultar ou gravar o agregado.
3. Os campos são normalizados; documentos e valores tipados são validados no domínio.
4. A operação persiste a Pessoa e as coleções recebidas em uma transação única, após conferir as referências corporativas permitidas.
5. O nome de exibição é calculado no domínio; o evento mínimo de auditoria é gravado sem snapshots sensíveis.
6. A resposta apresenta o agregado em `camelCase`, incluindo a projeção de relacionamentos de entrada e saída.

### Buscar

1. A listagem usa exclusivamente a conexão da organização e filtros normalizados por nome, alias, nome de exibição, documento ou contato.
2. Por padrão, apresenta Pessoas ativas; filtro explícito permite incluir inativas quando a permissão autorizar.
3. A resposta de lista não expõe as coleções completas; o detalhe é consultado sob demanda.

### Relacionamento

1. A operação confirma que as duas Pessoas existem no mesmo schema organizacional, são distintas e atendem à unicidade direcional.
2. O domínio armazena um único tipo direcional e obtém seu oposto no catálogo fechado do módulo.
3. Ao consultar a outra Pessoa, o projetor apresenta o mesmo registro com direção de entrada e rótulo oposto, sem criar uma cópia.

### Exclusão física

1. O serviço solicita a cada verificador registrado por outros módulos os usos bloqueantes da Pessoa.
2. Se houver uso conhecido, devolve lista segura e acionável ao usuário, sem tentar apagar a Pessoa.
3. Se não houver uso, remove a Pessoa em transação; seus dados filhos e relacionamentos seguem a regra de cascata aprovada.
4. Caso surja impedimento de integridade não previsto, a transação é revertida e a API devolve conflito seguro, sem vazar SQL ou nomes internos.
5. O evento de exclusão permanece na auditoria por identificador histórico, sem FK que bloqueie a remoção, e expira conforme a política de 90 dias.

## Autorização e auditoria

- Registrar permissões organizacionais de leitura, criação, alteração, duplicação, inativação, reativação e exclusão em alinhamento com a fundação de autorização existente.
- A autorização é reavaliada no backend para cada endpoint; capacidade apresentada na web nunca substitui a decisão de servidor.
- Eventos de auditoria registram ator, momento, correlação e ação, mas não armazenam documentos, endereços, contatos, contas ou chaves Pix em cópia integral; a rotina diária explícita de Pessoas, integrada ao Hub, remove eventos vencidos conforme configuração de ambiente, com padrão de 90 dias.
- A exibição de auditoria continua evolução própria; este módulo produz e retém os eventos mínimos aprovados, mas não cria tela de consulta nesta fase.
- A proteção criptográfica em repouso é requisito de infraestrutura de banco, backup e armazenamento. A aplicação não cifra nem mascara CPF, CNPJ, conta ou Pix; senhas permanecem tratadas exclusivamente pelo subsistema de acesso.

## Políticas gerais de API e concorrência

- `page` inicia em 1 e `perPage` usa padrão 50, máximo 200; ambos são configuráveis por ambiente e a ordenação padrão é estável por `displayName` e `id`.
- A lista pode ordenar somente por campos aprovados no contrato; respostas incluem página atual, tamanho, total e última página.
- Requisições mutáveis exigem `Idempotency-Key` UUID v4; a intenção e sua resposta ficam disponíveis por 24 horas configuráveis, no escopo usuário + organização + operação.
- A API autenticada aplica limite geral de 120 requisições por minuto por usuário e organização, configurável por ambiente; excesso retorna resposta segura de limite e tempo para nova tentativa.
- Corpos JSON usam limite global de 1 MiB configurável por ambiente. Esta feature não cria teto especial de coleções além da validação de payload geral.
- A atualização exige a versão devolvida na leitura. Divergência retorna conflito; a interface recarrega e o usuário refaz a alteração. Não há última gravação prevalecente nem mesclagem.
- Não há cache compartilhado de lista ou detalhe de Pessoas nesta fase. A meta de busca é medida com página padrão de 50, até 100.000 Pessoas na organização e 25 consultas concorrentes autorizadas.
- Métricas seguras registram p95 de leitura, volume de requisições, respostas de erro, limitação de taxa e conflitos de versão por operação. O alerta padrão de leitura usa p95 acima de 2 segundos durante cinco minutos, configurável por ambiente; nenhuma métrica inclui dados pessoais.

## Arquitetura das superfícies

**Catálogo**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)  
**Aplicabilidade de Interface Design**: REQUIRED — a feature cria jornadas de lista, formulário, coleções, confirmação destrutiva e responsividade completa.

| Surface ID | Cobertura | Decisão técnica | Módulo existente | Observações |
| --- | --- | --- | --- | --- |
| `SURF-WEB-PEOPLE` | FULL | Vue 3, TypeScript e navegador moderno | `resources/js`, `resources/css`, `resources/views` | SPA responsiva, i18n, tokens de design e mesma capacidade em todos os form factors. |
| `SURF-FUTURE-CONSUMERS` | API | JSON versionado | `app`, `routes/api` | Sem consumidor adicional entregue nesta feature. |

## Estrutura do projeto

### Documentação da feature

```text
docs/specs/person-registration/
├── spec.md
├── research.md
├── data-model.md
├── plan.md
├── quickstart.md
└── contracts/
    └── people-api.md
```

### Raízes de código existentes

```text
app/
├── Domain/
├── Http/
├── Infrastructure/
└── Services/
database/migrations/tenant/
resources/
├── css/
├── js/
└── views/
routes/
└── api/
tests/
├── Feature/
├── Unit/
├── e2e/
└── js/
```

**Decisão de estrutura**: a implementação criará um recorte coeso de Pessoas dentro dessas raízes já existentes, sem criar nova aplicação, novo schema global ou camada paralela.

## Convenções de borda

| Camada | Convenção | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Colunas e tabelas | inglês em `camelCase` | migration e testes de schema | `database/migrations/tenant` e [data-model.md](data-model.md) |
| Entidades e serviços PHP | `PascalCase` / `camelCase` | testes de domínio e feature | `app/Domain`, `app/Services` |
| Payloads e DTOs | `camelCase` | requests, responses e testes de contrato | [people-api.md](contracts/people-api.md) |
| Cliente TypeScript | `camelCase` | parser de resposta e testes JS | `resources/js` |
| Rotas | plural em inglês, parâmetros `camelCase` | testes de feature | [people-api.md](contracts/people-api.md) |

**Mapper**: controladores e serviços de aplicação convertem entre payload JSON, domínio e persistência; componentes web consomem somente os tipos de contrato.

**Validação de schema**: requisições validam formato e presença; o domínio valida tipo, normalização, contexto organizacional, referências e transições; respostas de contrato são verificadas por testes de feature e cliente.

## Ordem de implementação

1. Criar migrations de tenant, modelos e testes de integridade para o agregado e seus vínculos com catálogos corporativos.
2. Implementar domínio de identidade, normalização, busca e ciclo de vida de Pessoa.
3. Implementar endereços, contatos, contas e chaves Pix, com regras de validação e operações transacionais.
4. Implementar relacionamentos direcionais, tipos opostos e projeção de consulta nas duas pontas.
5. Integrar autorização, auditoria mínima, rotina explícita de retenção observada pelo Hub e verificação de usos bloqueantes antes de exclusão.
6. Expor contratos da API e cobrir erros de validação, conflito e isolamento organizacional.
7. Implementar a superfície web responsiva conforme a futura especificação de interface.
8. Executar os cenários de [quickstart.md](quickstart.md), testes de schema, API, cliente e fluxo ponta a ponta.

## Validação obrigatória

- Migrations limpas em schema de organização novo e atualização de schema existente, com PKs BIGINT, índices, FKs e sem ações restritivas.
- Isolamento: uma organização nunca consulta nem altera Pessoa de outra.
- Documentos: ausência aceita; formato e dígito verificador válidos; unicidade ativa/inativa por organização; reutilização em outra organização permitida.
- Endereço: país obrigatório; Brasil exige UF e Município; rua textual aceita; referências corporativas opcionais não eliminam o endereço.
- Coleções: contato e Pix válidos por tipo, sem principal/finalidade; conta e chaves repetidas tratadas conforme contrato.
- Relacionamentos: quatro combinações PF/PJ, direção, inverso, `OTHER`, auto-vínculo recusado e exclusão que remove somente o vínculo.
- Exclusão: diagnóstico prévio de uso, fallback de integridade sem mensagem técnica, reversão transacional e auditoria mínima.
- Interface: teclado, toque, estados vazio/carregando/erro/sucesso, i18n e paridade funcional em desktop, tablet e telefone.

## Limites desta fase

- Não cria dados fiscais, dados profissionais, dependentes, contato principal, conta principal ou finalidade/prioridade de chave Pix.
- Não cria cadastro manual de País, UF, Município, CEP ou Localidade.
- Não cria sincronização, fonte externa ou manutenção de catálogo; a retenção diária é uma rotina explícita de Pessoas integrada ao Hub de Manutenções.
- Não cria nova superfície nativa, aplicativo móvel separado ou integração de terceiro.
- Não define tela de auditoria; a retenção dos eventos produzidos é de 90 dias por padrão configurável, mas a consulta visual fica fora de escopo.

## Rechecagem da Constituição

| Princípio | Status | Evidência |
| --- | --- | --- |
| I. Simplicidade incremental | PASS | Mantém somente o cadastro organizacional e suas coleções aprovadas; exclusões permanecem explícitas. |
| II. Fronteira API e domínio | PASS | Regras de validação, ciclo de vida e exclusão ficam fora da web e são expostas por contrato versionado. |
| III. Identidade e acesso seguros | PASS | Todo endpoint exige autenticação, contexto organizacional elegível e autorização no servidor. |
| IV. Dados mínimos e configuração segura | PASS | Auditoria evita snapshots sensíveis; nenhuma credencial ou configuração nova é introduzida. |
| V. Mudanças verificáveis | PASS | Plano define testes de schema, domínio, API, cliente e cenário ponta a ponta. |
| VI. Identidades numéricas e referências unidirecionais | PASS | PKs BIGINT; organização referencia catálogos corporativos sem dependência inversa e sem FKs restritivas. |
