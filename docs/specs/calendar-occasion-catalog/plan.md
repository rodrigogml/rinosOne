# Plano Técnico — Catálogo de Ocasiões de Calendário

**Feature**: `calendar-occasion-catalog` | **Data**: 2026-10-02 | **Spec**: [spec.md](spec.md)

## Resumo

Implementar um catálogo global de definições de feriados, pontos facultativos e datas comemorativas. Uma definição persiste território, vigência e um dos quatro tipos fechados de recorrência. Consultas de definições devolvem regras-base; consultas de ocorrências expandem datas sob demanda para o período e destino solicitados, resolvendo substituições explícitas entre País, UF e Município sem suprimir ocasiões independentes.

## Contexto técnico

| Aspecto | Decisão |
| --- | --- |
| Aplicação | Laravel 12 em PHP 8.2+, Eloquent, MySQL 9, API JSON versionada e Vue 3 já existentes. |
| Identidade | `BIGINT UNSIGNED AUTO_INCREMENT`; País, UF e Município são referências globais existentes. |
| Ownership | Dados somente no schema global `rinosone`; a feature não cria tabelas de tenant nem FKs core → tenant. |
| Datas | Campos persistidos como `DATE`; cálculo com calendário gregoriano e objetos imutáveis de data. |
| Páscoa | `ext-calendar` obrigatório no runtime; `easter_days` com `CAL_EASTER_ALWAYS_GREGORIAN`. |
| Testes | PHPUnit para domínio, persistência, contrato e feature; Vitest para cliente administrativo; Playwright para jornada web crítica posterior. |
| Desempenho | Catálogo global de volume reduzido; filtrar candidatas por destino, vigência e categoria antes de expandir recorrências. Uma meta numérica de escala não foi aprovada nesta fase. |
| Segurança | Rotas seguem a autenticação existente. Não há permissão nova nesta feature; autorização detalhada permanece fora do escopo. |
| Operação | Sem scheduler, job, materialização de ocorrência, token externo ou retenção nova. |

## Arquitetura proposta

```text
Administração web ──> API de definições ──> CalendarOccasionApplicationService
                                             ├─ CalendarOccasionValidator
                                             ├─ CalendarOccasionRepository
                                             └─ LocalityReferenceResolver

Módulo consumidor ──> API de ocorrências ──> CalendarOccurrenceQueryService
                                             ├─ DestinationDefinitionQuery
                                             ├─ RecurrenceOccurrenceCalculator
                                             ├─ SubstitutionResolutionService
                                             └─ OccurrenceProjection

Foundation de Localidades ───────────────────> country / brazilState / brazilMunicipality
```

## Componentes e responsabilidades

| Local | Responsabilidade planejada |
| --- | --- |
| `database/migrations/core` | Criar `calendarOccasion`, FKs, índices e timestamps do catálogo global. |
| `app/Models` | Modelo Eloquent e relações para localidade e substituição auto-referenciada. |
| `app/Domain/CalendarOccasion` | Enums, objeto de recorrência, validador de combinações, cálculo determinístico e regra de substituição. |
| `app/Services/CalendarOccasion` | Operações de cadastro, busca de candidatas, expansão de ocorrências e projeções de resposta. |
| `app/Services/Locality` | Consultas de País, UF e Município para validadores e seletores, sem alterar o catálogo territorial. |
| `app/Http/Controllers/Api/V1` e `app/Http/Requests` | Fronteira HTTP, validação estrutural, serialização `camelCase` e erros seguros do contrato. |
| `routes/api` | Rotas de administração, consulta de definições, consulta de ocorrências e referência territorial de leitura. |
| `resources/js` e `resources/css` | `CalendarOccasionWorkspace`, catálogo e editor responsivos, seguindo a composição de `PeopleWorkspace`, `PeopleCatalog` e `PersonForm`. |
| `tests/Unit`, `tests/Feature`, `tests/js` e `tests/e2e` | Cobertura do cálculo, integridade, API e jornada humana. |

## Fluxos técnicos principais

### Criar, alterar e excluir definição

1. A fronteira HTTP valida forma, enums e datas ISO antes de enviar a intenção à aplicação.
2. O domínio valida escopo/localidade, vigência inclusiva, parâmetros exclusivos do tipo de recorrência e a relação opcional de substituição.
3. A aplicação resolve as referências territoriais no catálogo global e confirma a cadeia País → UF → Município.
4. A definição é persistida no core e projetada como objeto com `locality`, `validity` e `recurrence` aninhados.
5. Alterações sempre revalidam o registro completo resultante. Exclusão física remove a definição; filhas que a substituíam ficam com vínculo nulo por `SET NULL` e passam a ser independentes.

### Consultar definições

1. A API normaliza filtros de categoria, escopo, território, paginação e período opcional.
2. A consulta elimina registros de país/escopo incompatíveis e registros cuja vigência não intersecta o período.
3. Quando houver período, o calculador verifica por ano se a regra produz ao menos uma data válida e vigente no intervalo.
4. A resposta pagina definições completas; não cria nem inclui instâncias de ocorrência.

### Consultar ocorrências

1. A API exige `from`, `to` e localidade de destino coerente; valida que País, UF e Município formam uma cadeia territorial existente.
2. A consulta carrega candidatas aplicáveis ao País, à UF e ao Município de destino, respeitando filtros opcionais e vigência que intersecta o intervalo.
3. Para cada ano necessário, o calculador gera datas de cada tipo de recorrência e retém somente datas dentro do período e da vigência inclusiva.
4. O resolvedor percorre vínculos de substituição aplicáveis ao destino: uma filha suprime apenas sua ancestral de ponto facultativo; cadeias válidas são processadas da definição mais específica para a ancestral.
5. A projeção devolve todas as ocorrências restantes ordenadas por data, nome e `definitionId`. Não persiste instâncias nem inventa ID de ocorrência.

## Estratégia de cálculo de recorrência

- `ONE_TIME_DATE`: testa diretamente `oneTimeDate` contra período e vigência.
- `ANNUAL_FIXED_DATE`: tenta a data estrita para cada ano. Falha de 29 de fevereiro em ano não bissexto representa ausência de ocorrência, não correção para outra data.
- `ANNUAL_NTH_WEEKDAY`: calcula primeiro ou último dia do mês e desloca aritmeticamente até o dia da semana/ordinal solicitado; `FIFTH` pode não gerar data em determinado mês.
- `EASTER_OFFSET`: calcula a Páscoa gregoriana-eclesiástica com `easter_days` e desloca por inteiro. O código não usa timestamp Unix nem parser relativo dependente do relógio atual.
- Toda rotina recebe intervalo de datas explícito e usa UTC apenas como contexto técnico neutro para objetos de data; a data persistida e exposta não possui timezone.

## Segurança, exclusão e consistência

- A feature não armazena base normativa, expõe dados pessoais ou cria segredo/configuração sensível.
- O domínio retorna erros de validação por campo e códigos seguros; nunca descreve detalhes de SQL ou de implementação ao cliente.
- A substituição só é aceita entre mesma cadeia territorial, recorrência equivalente e ponto facultativo ancestral. Validador consulta a cadeia inteira antes de salvar para recusar ciclos e empates de mesma especificidade.
- Não existe `UNIQUE` de nome/data: coincidências independentes são requisito de negócio.
- A exclusão normal afeta consultas futuras. Não há consumer snapshot ou tabela de auditoria nesta feature. O impacto de FKs futuras de tenant é decisão explícita da feature consumidora, conforme a Constituição.

## Arquitetura das superfícies

**Catálogo**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)  
**Aplicabilidade de Interface Design**: REQUIRED — a administração cria e altera regras condicionais, seleciona localidade hierárquica e confirma exclusão; seus estados, acessibilidade e responsividade serão definidos na etapa 6.

| Surface ID | Cobertura | Decisão técnica | Módulo existente | Observações |
| --- | --- | --- | --- | --- |
| `SURF-WEB-ADMIN` | PARTIAL | Vue 3, TypeScript e navegador moderno | `resources/js`, `resources/css`, `app`, `routes/api` | SPA responsiva na Área de Trabalho; entrada Domínio → Administração → Tabelas Centrais → Feriados, com `planet` e `holiday` no catálogo raster. |
| `SURF-FUTURE-CONSUMERS` | FULL | JSON versionado | `app`, `routes/api` | Contratos de definições e ocorrências sem um consumidor específico entregue. |

## Constitution Check

*GATE: aprovado antes do Phase 0 e rechecado após o design.*

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental e escopo autorizado | PASS | Limita-se ao catálogo, recorrências aprovadas, consultas e administração; não cria motor de regras livre, calendário lunar ou módulos consumidores. |
| II. Fronteira API e domínio independente da interface | PASS | Cálculo, validação territorial e substituição residem no domínio e são expostos por contratos JSON. |
| III. Identidade e acesso seguros por padrão | PASS | Usa autenticação existente e não introduz segredos nem fluxo de acesso. A política fina de permissão não é expandida. |
| IV. Dados mínimos, sessões controladas e configuração segura | PASS | Persiste somente definição necessária; não registra fundamento legal, horário, ocorrência materializada ou auditoria extra. |
| V. Mudanças verificáveis e documentação alinhada | PASS | Plano define testes unitários, schema, contrato, cliente e ponta a ponta. |
| VI. Identidades numéricas e referências unidirecionais | PASS | PKs e FKs são `BIGINT`; dados permanecem globais sem referência a tenant. Exclusão e futuras referências de tenant têm impacto explicitamente documentado. |

## Estrutura do projeto

### Documentação da feature

```text
docs/specs/calendar-occasion-catalog/
├── spec.md
├── research.md
├── data-model.md
├── plan.md
├── quickstart.md
├── contracts/
│   └── calendar-occasion-api.md
└── interface-spec.md       # Etapa 6
```

### Raízes de código existentes

```text
app/
├── Domain/
├── Http/
├── Models/
├── Services/
└── Infrastructure/
database/
└── migrations/
    └── core/
resources/
├── css/
└── js/
routes/
└── api/
tests/
├── Unit/
├── Feature/
├── Performance/
├── js/
└── e2e/
```

**Decisão de estrutura**: criar um recorte coeso `CalendarOccasion` nas raízes já existentes, reaproveitando a Fundação de Localidades e sem nova aplicação, schema ou camada paralela.

## Convenções de borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Tabelas e colunas | inglês em `camelCase` | migration, FK, índice e teste de schema | [data-model.md](data-model.md) e `database/migrations/core` |
| Domínio PHP | `PascalCase` / `camelCase` | teste unitário e de feature | `app/Domain/CalendarOccasion`, `app/Services/CalendarOccasion` |
| Payloads JSON | `camelCase` | request, serializador e teste de contrato | [calendar-occasion-api.md](contracts/calendar-occasion-api.md) |
| Cliente TypeScript | `camelCase` | tipos e testes JS | `resources/js` |
| Rotas | plural em inglês; parâmetros `camelCase` | testes de feature | [calendar-occasion-api.md](contracts/calendar-occasion-api.md) |

**Mapper layer (DB ↔ DTO)**: modelo Eloquent e serviços de aplicação mapeiam a definição persistida para objetos de domínio; controladores/serializadores convertem esses objetos em representação JSON. O cliente consome somente a representação de contrato.

**Validação de schema**: requests validam estrutura, formato e enums; o domínio valida combinações territoriais, recorrência, vigência e substituição; testes de feature verificam respostas JSON e testes JS verificam o cliente.

## Ordem de implementação

1. Criar migration core, modelo, relações e testes de integridade do catálogo e suas FKs territoriais/auto-referenciadas.
2. Criar enums, objeto de recorrência e calculador de datas com testes determinísticos para os quatro tipos e limites de vigência.
3. Implementar validador de escopo, localidade, vigência e substituição acíclica/equivalente.
4. Implementar operações de cadastro e consulta paginada de definições, inclusive filtro de período baseado em recorrência.
5. Implementar consulta de ocorrências, carregamento de candidatas por destino e resolução de promoções territoriais.
6. Expor contratos JSON e leituras territoriais para a administração; cobrir validação, exclusão, filtro e ordenação.
7. Implementar a interface especificada em [interface-spec.md](interface-spec.md), incluindo o destino `platform.calendar-occasions`, o novo grupo `Tabelas Centrais` e o registry raster de `planet` e `holiday`.
8. Executar os cenários de [quickstart.md](quickstart.md), testes de schema, domínio, API, cliente e ponta a ponta.

## Validação obrigatória

- Migration aplicada sobre schema global limpo, com PK/FKs `BIGINT`, índices nomeados e nenhuma FK restritiva.
- Combinações de País/UF/Município, categoria e escopo válidas e inválidas.
- Todos os quatro tipos de recorrência, limites inclusivos, 29 de fevereiro, quinto dia semanal inexistente e offsets positivos/negativos da Páscoa.
- Definições por período versus ocorrências por período, comprovando que a primeira devolve regra e a segunda devolve datas expandidas.
- Coincidências independentes preservadas e cadeias País → UF → Município suprimindo somente ancestrais substituídas.
- Ciclos, territórios incompatíveis, recorrência não equivalente, duplicidade de substituto e rebaixamento de feriado rejeitados sem escrita parcial.
- Contratos `camelCase`, erros seguros, seleção territorial de leitura e roundtrip API ↔ cliente web.
- Administração responsiva com teclado, toque, estados de carregamento/erro/sucesso e confirmação de exclusão, após a etapa 6.

## Limites desta fase

- Não implementa jornada, expediente, horário parcial, compensação ou políticas de Workforce.
- Não implementa permissões específicas, auditoria, versionamento ou retenção histórica da definição.
- Não importa, valida ou armazena leis, decretos e referências normativas.
- Não cria subdivisões estrangeiras, recorrências de outros calendários ou motor de expressão livre.
- Não persiste ocorrências nem cria ID próprio para elas.

## Rechecagem da Constituição

O modelo proposto preserva o core global como fonte de verdade e mantém dependências unidirecionais para localidades já existentes. A decisão de exclusão física está documentada sem introduzir referência de tenant nesta fase; qualquer consumidor futuro deverá planejar o próprio impacto e relação de exclusão. Não há violação de princípio constitucional.
