# Briefing da Feature: Catálogo de Ocasiões de Calendário

**Feature**: `calendar-occasion-catalog`
**Data**: 2026-10-02
**Status**: Confirmado
**Versão**: 1.0

---

## 1. Visão e Propósito

**O que é**: um catálogo global reutilizável de ocasiões de calendário — feriados, pontos facultativos e datas comemorativas — com definição territorial, vigência e recorrência automática.

**Problema que resolve**: evitar que módulos como Workforce, Projects e Marketing mantenham calendários próprios, calculadores de datas divergentes ou regras territoriais duplicadas.

**Proposta de valor**: disponibilizar uma fonte comum para consultas de regras-base e de ocorrências efetivas em um período e localidade, sem incorporar regras de jornada, controle legal ou burocracia normativa.

## 2. Usuários e Stakeholders

| Ator | Papel | Ações principais |
| --- | --- | --- |
| Módulo consumidor | Sistema interno | Consulta definições ou ocorrências filtradas por período, categoria e escopo territorial. |
| Pessoa responsável pelo catálogo [inferido] | Administração global | Cadastra, altera e exclui definições de ocasião. |
| Responsável pelo produto da Plataforma | Decisor | Define escopo e prioriza a evolução da capacidade. |

**Stakeholders de decisão**: responsável pelo produto da Plataforma.

## 3. Interfaces e Canais

| Interface/Canal | Usuários | Dispositivos/Plataformas | Cobertura | Conectividade | Paridade esperada |
| --- | --- | --- | --- | --- | --- |
| API JSON versionada | Módulos internos e futuros consumidores | Serviço a serviço / web responsiva consumidora | MVP | Online | Contratos de consulta de definições e ocorrências. |
| Cadastro administrativo [inferido] | Pessoa responsável pelo catálogo | Web responsiva administrativa existente | MVP | Online | Manutenção normal de registros; permissões não pertencem ao escopo desta feature. |

**Restrições tecnológicas já obrigatórias**: Laravel/PHP, Vue 3, TypeScript, MySQL, API JSON versionada e schema global já adotados pela Plataforma.

## 4. Escopo

### MVP (Essencial)

1. Manter definições globais de ocasiões com nome, categoria, escopo, localidade, vigência e regra de recorrência.
2. Classificar cada definição como `HOLIDAY`, `OPTIONAL_DAY_OFF` ou `COMMEMORATIVE_DATE`.
3. Associar obrigatoriamente o escopo `COUNTRY` a um País; `BRAZIL_STATE` a uma UF brasileira; e `BRAZIL_MUNICIPALITY` a um Município brasileiro. Datas comemorativas ficam inicialmente somente em `COUNTRY`.
4. Cobrir feriados internacionais exclusivamente no nível `COUNTRY`; aplicar a estrutura completa País/UF/Município para o Brasil.
5. Calcular recorrências `ONE_TIME_DATE`, `ANNUAL_FIXED_DATE`, `ANNUAL_NTH_WEEKDAY` e `EASTER_OFFSET`.
6. Consultar definições completas por filtros, inclusive por período conforme a sua regra de recorrência.
7. Consultar ocorrências calculadas sob demanda por período, categoria e localidade de destino, retornando `definitionId`, nome, categoria, escopo e data da ocorrência.
8. Resolver pontos facultativos e feriados vinculados ao longo da hierarquia País → UF → Município, retornando a definição mais específica que promover o ponto facultativo para feriado.

### Pós-MVP (Desejável)

1. Ampliar localidades subnacionais de países além do Brasil quando houver fonte e modelo aprovados.
2. Incluir estratégias de recorrência de outros calendários somente quando uma necessidade de país e uma regra verificável forem aprovadas.
3. Ampliar filtros e projeções da API segundo necessidades concretas dos módulos consumidores.

### Fora de Escopo

- Jornada, horário, meio expediente, compensação ou demais regras de Workforce.
- Controle de permissões e responsabilidades administrativas.
- Armazenamento de lei, decreto, artigo, URL ou outra referência normativa.
- Verificação de conformidade jurídica, inclusive limites de feriados religiosos municipais.
- Auditoria, versionamento obrigatório, imutabilidade ou retenção histórica de definições.
- Persistência de instâncias/ocorrências calculadas e identificador próprio de ocorrência.
- Subdivisões e municípios estrangeiros.

## 5. Prioridades e Trade-offs

**Ordem de prioridade**: consistência do calendário compartilhado > cálculos corretos e reutilizáveis > evolução incremental das consultas > ampliação internacional.

**Decisões explícitas**:

- Datas são apenas datas de calendário; a feature não modela horário ou expediente.
- `validFrom` e `validTo` são inclusivos. Início nulo significa que a definição sempre existiu; fim nulo significa vigência indeterminada.
- Registros podem ser cadastrados, alterados e excluídos normalmente. Para uma mudança prospectiva, encerrar a definição anterior e criar uma nova é orientação, não bloqueio sistêmico.
- Consulta de definições e consulta de ocorrências são capacidades distintas. A primeira devolve as regras-base; a segunda expande as datas aplicáveis ao período.
- Ocorrências não têm ID persistido. A resposta expõe apenas o `definitionId` que as gerou.
- Definições independentes que incidam na mesma data são retornadas como ocorrências distintas.
- Uma definição de `HOLIDAY` não pode ser rebaixada a `OPTIONAL_DAY_OFF` em escopo inferior.
- Não haverá motor lunar astronômico genérico no MVP. `EASTER_OFFSET` calcula a Páscoa gregoriana-eclesiástica e aplica deslocamento em dias; Paixão de Cristo equivale a `-2` e Corpus Christi a `+60`.
- `ANNUAL_FIXED_DATE` em 29 de fevereiro gera ocorrência somente nos anos bissextos; não há deslocamento silencioso para 28 de fevereiro ou 1º de março.

## 6. Restrições

| Restrição | Valor | Notas |
| --- | --- | --- |
| Ownership | Core global | A capacidade é compartilhada; o core não referencia dados de tenant. |
| Localidades | Fundação de Localidades existente | Países, UFs e Municípios brasileiros já possuem catálogo global. |
| Dados jurídicos | Não armazenados | O catálogo não prova nem verifica a base legal da definição. |
| Instâncias | Calculadas sob demanda | Alterações e exclusões afetam consultas futuras; consumidores que precisem preservar decisões passadas deverão registrar seus próprios dados. |
| Prazo, equipe e orçamento | A definir | Não informados durante o briefing. |

## 7. Stack Técnica

| Camada | Tecnologia | Justificativa |
| --- | --- | --- |
| Backend e API | PHP, Laravel e API JSON versionada | Stack obrigatória e fronteira para os módulos consumidores. |
| Banco de dados | MySQL, schema global | Catálogo compartilhado e relações com País, UF e Município. |
| Interface administrativa | Web responsiva existente, Vue 3 e TypeScript | Canal administrativo já adotado pela Plataforma. |
| Cálculo | Serviço de domínio [inferido] | Gera ocorrências sem persistir instâncias e mantém a regra fora dos consumidores. |
| Integrações | Fundação de Localidades | Disponibiliza `country`, `brazilState` e `brazilMunicipality`. |

## 8. Qualidade e Padrões

**Padrões adotados**:

- Separar definição de calendário de ocorrência calculada.
- Tratar data como `DATE`, sem fuso horário ou horário de expediente.
- Manter recorrências explícitas e extensíveis, sem inferir datas por texto ou por observação astronômica genérica.
- Resolver substituições territoriais apenas por vínculo explícito; coincidência de nome ou data nunca deduz promoção.
- Testar vigência inclusiva, 29 de fevereiro, recorrências relativas à Páscoa, ordinal de dia da semana, datas únicas, sobreposições independentes e resolução País → UF → Município.

**Integridade da promoção territorial proposta**:

- Uma definição inferior pode declarar que substitui uma definição ancestral de `OPTIONAL_DAY_OFF`; sua categoria pode continuar como ponto facultativo ou promovê-la a `HOLIDAY`.
- O vínculo é dirigido da definição mais específica para a ancestral, deve permanecer no mesmo País e em território contido pelo da ancestral.
- A definição filha possui no máximo um vínculo de substituição; o grafo não pode formar ciclos.
- O vínculo só é válido quando as recorrências representam a mesma data em suas vigências comuns; isto evita relacionar por engano duas ocasiões diferentes com nomes parecidos.
- Em uma data e destino, se houver dois substitutos concorrentes de mesma especificidade para a mesma definição ancestral, o cadastro é inválido. Definições sem vínculo continuam independentes e são retornadas todas.

Essas validações preservam determinismo do cálculo; não avaliam se a definição respeita a legislação aplicável.

**Compliance**: não há validação jurídica ou registro de base normativa. A legislação brasileira é apenas referência externa de pesquisa; por exemplo, a Lei nº 9.093/1995 limita feriados religiosos municipais, mas esse controle foi explicitamente excluído desta feature.

## 9. Visão de Futuro

**6 meses**: Workforce, Projects e Marketing consultam o mesmo calendário efetivo sem duplicar cálculos de recorrência ou territorialidade.

**12 meses**: novos países podem usar ocasiões nacionais e, quando houver necessidade e fontes aprovadas, receber modelos de subdivisão próprios.

**Riscos conhecidos**:

- Alteração ou exclusão normal de uma definição recalcula resultados futuros; consumidores que exigirem rastreabilidade devem preservar sua própria decisão/ocorrência.
- Vínculos territoriais incorretos podem ocultar ponto facultativo aplicável ou selecionar uma esfera errada; validações de aciclicidade e compatibilidade são essenciais.
- A expansão de calendários não gregorianos exige regra de negócio específica, não apenas uma fórmula lunar genérica.

---

## Itens a Definir

| Item | Dimensão | Impacto |
| --- | --- | --- |
| Campos textuais complementares da definição, como descrição e nomes localizados | Dados e experiência | Médio |
| Filtros iniciais e projeções exatas de cada endpoint de definição e ocorrência | Contrato de API | Médio |
| Tela administrativa e seus estados de exclusão/confirmação | Interface | Médio |
| Formato de erros para vínculos de substituição inválidos | Contrato e validação | Médio |

---

**Próximo passo recomendado**: `Dev Pipeline - 3. Specification - Specify` para converter este briefing em requisitos funcionais, cenários de aceite e critérios de sucesso.
