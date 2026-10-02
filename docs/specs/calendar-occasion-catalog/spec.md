# Especificação da Feature: Catálogo de Ocasiões de Calendário

**Feature**: `calendar-occasion-catalog`
**Criada em**: 2026-10-02
**Status**: Draft

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Administração global de ocasiões | Web responsiva administrativa | Pessoa responsável pelo catálogo [inferido] | PARTIAL | Cadastra, consulta, altera e exclui definições de ocasião. | Permissões, auditoria, tela detalhada e seus estados serão definidos antes da implementação da interface. |
| Consulta de definições | Interface de serviço | Módulos consumidores | FULL | Filtra e devolve regras-base completas. | Projeções especializadas por consumidor. |
| Consulta de ocorrências | Interface de serviço | Módulos consumidores | FULL | Calcula as ocorrências aplicáveis a um período e localidade. | Persistir instâncias ou incluir horários/jornadas. |
| Workforce, Projects e Marketing | Web responsiva dos módulos consumidores | Pessoas usuárias dos módulos | DEFERRED | Consumirão consultas desta capacidade em features próprias. | Telas, fluxos e políticas de cada módulo. |

## Cenários de Usuário e Testes

### User Story 1 - Manter uma definição de ocasião (Prioridade: P1)

Como pessoa responsável pelo catálogo global, quero cadastrar, consultar, alterar e excluir uma definição de ocasião com sua categoria, localidade, vigência e recorrência, para manter uma referência única para todos os módulos.

**Por que esta prioridade**: sem as regras-base, não há ocorrências nem valor reutilizável para os módulos consumidores.

**Teste independente**: cadastrar uma definição de feriado, recuperá-la completa, alterar um campo permitido e excluí-la; confirmar que dados jurídicos, horários e regras de jornada não são solicitados nem registrados.

**Cenários de aceite**:

1. **Dado** que uma pessoa informa nome, categoria, escopo, localidade exigida e recorrência válida, **quando** cria a definição, **então** ela fica disponível para consultas posteriores.
2. **Dado** que uma definição existente precisa de correção, **quando** a pessoa a altera, **então** a consulta posterior apresenta os dados atuais sem exigir versionamento nem justificar a alteração.
3. **Dado** que uma definição não deve mais constar no catálogo, **quando** a pessoa a exclui, **então** ela deixa de ser retornada nas consultas futuras.
4. **Dado** que a pessoa tenta informar lei, decreto, URL normativa, horário ou regra de expediente, **quando** cadastra uma definição, **então** esses dados não fazem parte do cadastro.

---

### User Story 2 - Consultar regras-base de calendário (Prioridade: P1)

Como módulo consumidor, quero consultar definições completas de ocasião por filtros, para aplicar ou exibir regras-base sem depender da consulta de ocorrências.

**Por que esta prioridade**: Marketing e outras capacidades podem precisar conhecer a regra e a recorrência, não apenas uma data expandida.

**Teste independente**: cadastrar definições de diferentes escopos e recorrências, pesquisar por categoria, escopo e período, e confirmar que a resposta contém os objetos de definição completos e não instâncias calculadas.

**Cenários de aceite**:

1. **Dado** que há definições em mais de uma categoria e escopo, **quando** o consumidor filtra por categoria ou escopo, **então** recebe somente as definições compatíveis.
2. **Dado** que um consumidor filtra definições por período, **quando** uma regra pode gerar ao menos uma ocorrência nesse intervalo e está vigente nessa data, **então** a definição é incluída.
3. **Dado** que uma definição não pode produzir data no intervalo ou não está vigente em nenhuma data dele, **quando** a consulta é executada, **então** ela não é retornada.
4. **Dado** que uma definição é retornada, **quando** o consumidor examina o resultado, **então** ele recebe sua regra de recorrência e atributos completos, sem `occurrenceDate` gerada.

---

### User Story 3 - Obter ocorrências efetivas por localidade (Prioridade: P1)

Como módulo consumidor, quero consultar as ocorrências de ocasiões em um período e uma localidade de destino, para usar o calendário efetivo em minha funcionalidade.

**Por que esta prioridade**: Workforce, Projects e Marketing precisam de datas concretas, não de cada módulo recalcular uma mesma regra.

**Teste independente**: consultar um município brasileiro durante dois anos que contenha uma data fixa, uma data relativa à Páscoa e uma data comemorativa nacional; confirmar uma linha por ocorrência aplicável com a data calculada e a definição de origem.

**Cenários de aceite**:

1. **Dado** um intervalo que abrange dois anos e uma definição anual fixa vigente, **quando** o consumidor consulta ocorrências, **então** recebe uma ocorrência para cada ano em que a data exista e esteja vigente.
2. **Dado** uma definição de data única dentro do intervalo, **quando** o consumidor consulta ocorrências, **então** recebe uma única ocorrência; fora do intervalo, não recebe nenhuma.
3. **Dado** uma definição anual em 29 de fevereiro, **quando** o período inclui anos bissextos e não bissextos, **então** recebe ocorrências somente nos anos bissextos, sem deslocamento para outra data.
4. **Dado** duas definições independentes aplicáveis à mesma localidade e data, **quando** o consumidor consulta ocorrências, **então** recebe ambas como ocorrências distintas.
5. **Dado** uma ocorrência retornada, **quando** o consumidor examina o resultado, **então** recebe `definitionId`, nome, categoria, escopo territorial e `occurrenceDate`, sem identificador próprio de ocorrência.

---

### User Story 4 - Resolver ponto facultativo promovido territorialmente (Prioridade: P1)

Como módulo consumidor, quero receber a definição territorial efetiva quando um ponto facultativo for substituído por um feriado de esfera menor, para não duplicar ou classificar incorretamente a data.

**Por que esta prioridade**: a mesma ocasião pode ter efeitos diferentes conforme a localidade consultada.

**Teste independente**: criar um ponto facultativo nacional, vinculá-lo a um feriado estadual e consultar a UF e um município pertencente a ela; confirmar que apenas o feriado estadual é retornado. Repetir com um ponto facultativo estadual e feriado municipal.

**Cenários de aceite**:

1. **Dado** um ponto facultativo de País sem definição vinculada em escopo inferior aplicável ao destino, **quando** o consumidor consulta uma localidade daquele País, **então** recebe o ponto facultativo do País.
2. **Dado** um ponto facultativo de País substituído por feriado de uma UF, **quando** o consumidor consulta a UF ou município nela contido, **então** recebe somente o feriado estadual daquela relação.
3. **Dado** um ponto facultativo estadual substituído por feriado de Município, **quando** o consumidor consulta esse Município, **então** recebe somente o feriado municipal daquela relação.
4. **Dado** uma definição marcada como feriado, **quando** alguém tenta vinculá-la a uma definição inferior marcada como ponto facultativo para rebaixá-la, **então** o vínculo não é aceito.
5. **Dado** que dois feriados independentes coincidam com a data promovida, **quando** não fizerem parte da mesma relação de substituição, **então** continuam sendo retornados separadamente.

---

### User Story 5 - Cobrir ocasiões nacionais brasileiras e internacionais (Prioridade: P2)

Como módulo consumidor, quero usar o mesmo catálogo para ocasiões nacionais de qualquer País cadastrado e, no Brasil, também para ocasiões estaduais e municipais, para expandir o uso sem duplicar o domínio.

**Por que esta prioridade**: a estrutura deve ser global desde o início, sem antecipar subdivisões internacionais não necessárias.

**Teste independente**: criar uma data comemorativa em nível de País para um País cadastrado e uma definição municipal brasileira; confirmar que ambas são aceitas nos respectivos limites e que uma data comemorativa estadual ou municipal é recusada.

**Cenários de aceite**:

1. **Dado** um País cadastrado, **quando** uma pessoa cria um feriado, ponto facultativo ou data comemorativa em escopo `COUNTRY`, **então** a definição fica associada obrigatoriamente a esse País.
2. **Dado** uma UF ou Município brasileiro cadastrado, **quando** uma pessoa cria feriado ou ponto facultativo no escopo territorial correspondente, **então** a definição fica associada obrigatoriamente àquela localidade.
3. **Dado** uma data comemorativa, **quando** alguém tenta associá-la a uma UF ou Município, **então** o cadastro não é aceito.
4. **Dado** um País que não é o Brasil, **quando** alguém tenta criar definição estadual ou municipal, **então** o cadastro não é aceito nesta fase.

---

### Edge Cases

- `validFrom` e `validTo` são inclusivos; uma ocorrência na própria data de início ou fim é válida.
- `validFrom` ausente significa que a definição sempre existiu; `validTo` ausente significa que não tem término previsto.
- Uma regra anual em 29 de fevereiro não gera ocorrência em ano não bissexto.
- Uma regra de data única não gera repetição em outros anos.
- Paixão de Cristo e Corpus Christi são calculadas a partir da Páscoa gregoriana-eclesiástica; não dependem de observação astronômica em tempo real.
- Vínculos de substituição não podem criar ciclo, combinar territórios incompatíveis, relacionar regras que não representam a mesma data, nem criar substitutos concorrentes de mesma especificidade para a mesma definição ancestral.
- Alteração ou exclusão de uma definição não preserva resultado histórico para módulos consumidores; eles devem manter seus próprios registros quando precisarem de rastreabilidade.

## Requisitos

### Requisitos Funcionais

- **FR-OCC-001**: O sistema DEVE permitir criar, consultar, alterar e excluir definições de ocasião sem exigir referência normativa, horário, duração ou regra de expediente.
- **FR-OCC-002**: Cada definição DEVE possuir nome, categoria, escopo territorial, localidade obrigatória conforme o escopo, regra de recorrência e vigência.
- **FR-OCC-003**: O sistema DEVE oferecer somente as categorias `HOLIDAY`, `OPTIONAL_DAY_OFF` e `COMMEMORATIVE_DATE` nesta fase.
- **FR-OCC-004**: O escopo `COUNTRY` DEVE exigir um País; `BRAZIL_STATE`, uma UF brasileira; e `BRAZIL_MUNICIPALITY`, um Município brasileiro. `COMMEMORATIVE_DATE` DEVE aceitar somente `COUNTRY`.
- **FR-OCC-005**: O sistema DEVE permitir ocasiões nacionais em qualquer País cadastrado e limitar escopos estadual e municipal ao Brasil nesta fase.
- **FR-OCC-006**: A vigência DEVE ser opcional no início e fim, aplicar ambos os limites de forma inclusiva e tratar início ausente como vigência desde sempre e fim ausente como vigência indeterminada.
- **FR-OCC-007**: O sistema DEVE suportar recorrências de data única, data fixa anual, dia ordinal da semana em mês anual e deslocamento em dias relativo à Páscoa gregoriana-eclesiástica.
- **FR-OCC-008**: Uma data fixa anual em 29 de fevereiro DEVE ocorrer somente em anos bissextos, sem mudança automática de data.
- **FR-OCC-009**: O sistema NÃO DEVE modelar horário, meio expediente, jornada, compensação ou qualquer efeito operacional de Workforce.
- **FR-OCC-010**: O sistema DEVE permitir que uma definição inferior vinculada substitua uma definição ancestral de ponto facultativo aplicável ao mesmo destino, podendo mantê-la como ponto facultativo ou promovê-la a feriado.
- **FR-OCC-011**: O sistema DEVE impedir que uma definição de feriado seja rebaixada por vínculo a um ponto facultativo de escopo inferior.
- **FR-OCC-012**: Vínculos de substituição DEVEM ser determinísticos: sem ciclos, entre territórios hierarquicamente compatíveis, com no máximo uma definição ancestral substituída por definição filha, e sem substitutos concorrentes de mesma especificidade no mesmo período.
- **FR-OCC-013**: A consulta de definições DEVE aceitar filtros gerais, incluindo período, categoria e escopo territorial, e devolver regras-base completas; o filtro de período DEVE considerar a capacidade da recorrência de gerar data vigente no intervalo.
- **FR-OCC-014**: A consulta de ocorrências DEVE aceitar período, localidade de destino e filtros gerais, calcular todas as datas aplicáveis e devolver `definitionId`, nome, categoria, escopo territorial e `occurrenceDate`.
- **FR-OCC-015**: A consulta de ocorrências DEVE retornar definições independentes coincidentes separadamente e suprimir somente o ponto facultativo explicitamente substituído por uma definição territorial efetiva.
- **FR-OCC-016**: Ocorrências DEVEM ser resultados calculados, sem identificador próprio persistido e sem promessa de imutabilidade após alteração ou exclusão da definição.
- **FR-OCC-017**: O sistema NÃO DEVE armazenar, solicitar ou validar bases legais, limites de feriados por legislação, nem outras informações normativas nesta fase.
- **FR-OCC-INFRA**: Decisões de infraestrutura: N/A (a capacidade não agenda trabalho periódico, não consome tokens externos e não persiste ocorrências calculadas).

### Entidades Principais

- **Definição de ocasião**: regra-base de um feriado, ponto facultativo ou data comemorativa; reúne nome, categoria, escopo, localidade, vigência e recorrência.
- **Regra de recorrência**: descreve como uma definição produz datas únicas, anuais fixas, anuais por dia ordinal da semana ou relativas à Páscoa.
- **Escopo territorial**: estabelece se a definição pertence a País, UF brasileira ou Município brasileiro e qual localidade a identifica.
- **Vínculo de substituição territorial**: relação explícita entre uma definição inferior e um ponto facultativo ancestral, usada para determinar a classificação efetiva no destino consultado.
- **Ocorrência calculada**: resultado não persistido de uma definição aplicada a uma data e localidade, identificado no retorno pelo `definitionId` e `occurrenceDate`.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-OCC-001**: 100% dos cenários de teste de criação aceitam somente categoria, escopo, localidade e recorrência compatíveis com o catálogo.
- **SC-OCC-002**: 100% dos cenários de recorrência cobrem corretamente data única, data fixa, dia ordinal da semana, Páscoa relativa e 29 de fevereiro.
- **SC-OCC-003**: 100% das consultas de ocorrência em testes retornam uma linha para cada data aplicável no período, com `definitionId` e sem identificador próprio de instância.
- **SC-OCC-004**: 100% dos cenários de promoção País → UF, UF → Município e ausência de promoção retornam a categoria e o escopo efetivos esperados, sem duplicar o ponto facultativo substituído.
- **SC-OCC-005**: 100% das definições independentes que coincidem no mesmo destino e data permanecem visíveis como ocorrências distintas.
- **SC-OCC-006**: 100% das tentativas de vínculo cíclico, de território incompatível, de rebaixamento de feriado ou de data não equivalente são rejeitadas sem alterar definições existentes.
