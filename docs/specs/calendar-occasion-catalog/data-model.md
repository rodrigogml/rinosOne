# Modelo de Dados — Catálogo de Ocasiões de Calendário

Todas as tabelas desta feature pertencem ao schema global `rinosone`. Usam `BIGINT UNSIGNED AUTO_INCREMENT` como chave primária e não possuem FK para tenants. O cadastro referencia a Fundação de Localidades; consumidores de tenant poderão referenciá-lo somente na direção tenant → core quando a respectiva feature for aprovada.

## `calendarOccasion`

Representa a definição persistida de uma ocasião. Sua recorrência é um objeto lógico formado pelos campos do próprio registro; ocorrências são calculadas e não são persistidas.

| Campo | Tipo | Restrições | Finalidade |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade técnica da definição. |
| `name` | VARCHAR(160) | obrigatório | Nome apresentado aos consumidores. |
| `occasionCategory` | VARCHAR(24) | obrigatório, indexado | `HOLIDAY`, `OPTIONAL_DAY_OFF` ou `COMMEMORATIVE_DATE`. |
| `territorialScope` | VARCHAR(32) | obrigatório, indexado | `COUNTRY`, `BRAZIL_STATE` ou `BRAZIL_MUNICIPALITY`. |
| `idCountry` | BIGINT UNSIGNED | obrigatório, FK | País aplicável a toda definição. |
| `idBrazilState` | BIGINT UNSIGNED | opcional, FK | Obrigatório em `BRAZIL_STATE` e `BRAZIL_MUNICIPALITY`; vazio em `COUNTRY`. |
| `idBrazilMunicipality` | BIGINT UNSIGNED | opcional, FK | Obrigatório somente em `BRAZIL_MUNICIPALITY`. |
| `validFrom` | DATE | opcional, indexado | Primeiro dia inclusivo de vigência; vazio significa desde sempre. |
| `validTo` | DATE | opcional, indexado | Último dia inclusivo de vigência; vazio significa sem término. |
| `recurrenceType` | VARCHAR(32) | obrigatório, indexado | Um dos quatro tipos aprovados. |
| `oneTimeDate` | DATE | condicional | Usado somente por `ONE_TIME_DATE`. |
| `fixedMonth` | TINYINT UNSIGNED | condicional | Mês de `ANNUAL_FIXED_DATE`. |
| `fixedDay` | TINYINT UNSIGNED | condicional | Dia de `ANNUAL_FIXED_DATE`. |
| `weekMonth` | TINYINT UNSIGNED | condicional | Mês de `ANNUAL_NTH_WEEKDAY`. |
| `weekOrdinal` | VARCHAR(12) | condicional | `FIRST`, `SECOND`, `THIRD`, `FOURTH`, `FIFTH` ou `LAST`. |
| `weekDay` | VARCHAR(12) | condicional | `MONDAY` a `SUNDAY`. |
| `easterOffsetDays` | SMALLINT | condicional | Deslocamento de `EASTER_OFFSET` em dias, positivo ou negativo. |
| `idReplacesOptionalDayOff` | BIGINT UNSIGNED | opcional, FK para si | Definição ancestral de ponto facultativo substituída pela atual. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatórios | Auditoria temporal local do registro atual; não é histórico de revisões. |

### Combinações territoriais válidas

| `territorialScope` | País | UF | Município | Categorias permitidas |
| --- | --- | --- | --- | --- |
| `COUNTRY` | obrigatório | vazio | vazio | Todas |
| `BRAZIL_STATE` | Brasil obrigatório | obrigatório | vazio | `HOLIDAY`, `OPTIONAL_DAY_OFF` |
| `BRAZIL_MUNICIPALITY` | Brasil obrigatório | obrigatório | obrigatório | `HOLIDAY`, `OPTIONAL_DAY_OFF` |

O validador de domínio confirma que UF pertence ao País informado e Município pertence à UF. Para Brasil, a redundância explícita da UF no escopo municipal permite filtros de destino indexados; a combinação precisa corresponder à hierarquia oficial.

### Combinações de recorrência válidas

| `recurrenceType` | Campos obrigatórios | Campos que devem permanecer vazios |
| --- | --- | --- |
| `ONE_TIME_DATE` | `oneTimeDate` | Todos os demais parâmetros de recorrência |
| `ANNUAL_FIXED_DATE` | `fixedMonth`, `fixedDay` | `oneTimeDate`, campos semanais e `easterOffsetDays` |
| `ANNUAL_NTH_WEEKDAY` | `weekMonth`, `weekOrdinal`, `weekDay` | `oneTimeDate`, campos fixos e `easterOffsetDays` |
| `EASTER_OFFSET` | `easterOffsetDays` | `oneTimeDate`, campos fixos e semanais |

`validTo`, quando presente, não pode anteceder `validFrom`. A data fixa anual deve formar uma data gregoriana válida; 29 de fevereiro é permitido e seu cálculo produz data somente em anos bissextos.

### Índices e relações

| Nome | Tipo | Campos / relação | Finalidade |
| --- | --- | --- | --- |
| `pk_calendar_occasion` | PK | `id` | Identidade da definição. |
| `idx_calendar_occasion_destination` | índice | `idCountry, territorialScope, idBrazilState, idBrazilMunicipality` | Carregamento de candidatas para o destino consultado. |
| `idx_calendar_occasion_category_scope` | índice | `occasionCategory, territorialScope` | Filtros gerais de definições. |
| `idx_calendar_occasion_validity` | índice | `validFrom, validTo` | Eliminação inicial de definições sem vigência no período. |
| `idx_calendar_occasion_recurrence` | índice | `recurrenceType` | Filtros e diagnóstico de regras. |
| `idx_calendar_occasion_replaces_optional` | índice | `idReplacesOptionalDayOff` | Resolução de cadeia de substituição. |
| `fk_calendar_occasion_country` | FK | `idCountry → country.id` | `ON UPDATE CASCADE`, `ON DELETE CASCADE`. |
| `fk_calendar_occasion_brazil_state` | FK | `idBrazilState → brazilState.id` | `ON UPDATE CASCADE`, `ON DELETE CASCADE`. |
| `fk_calendar_occasion_brazil_municipality` | FK | `idBrazilMunicipality → brazilMunicipality.id` | `ON UPDATE CASCADE`, `ON DELETE CASCADE`. |
| `fk_calendar_occasion_replaces_optional` | FK | `idReplacesOptionalDayOff → calendarOccasion.id` | `ON UPDATE CASCADE`, `ON DELETE SET NULL`. |

Não há unicidade por nome, data ou localidade: ocasiões independentes podem coincidir e devem ser preservadas. As regras que dependem de tipo, vigência, hierarquia e aciclicidade são validadas no domínio; uma constraint física simples não cobre corretamente a cadeia e seus parâmetros mutuamente exclusivos.

## Regras de exclusão e referências futuras

- Excluir uma definição remove-a das consultas futuras de definição e ocorrência.
- Excluir uma definição ancestral de ponto facultativo mantém a filha, mas define `idReplacesOptionalDayOff` como nulo; a filha passa a ser uma definição independente.
- Esta feature não cria referências de tenant. Uma feature consumidora que precisar preservar decisão histórica deve armazenar sua própria data efetiva e identificação de negócio, em vez de depender de ocorrência calculada após a definição ter mudado ou sido excluída.
- Quando uma futura tabela de tenant possuir FK para esta definição, seu plano deverá declarar explicitamente `CASCADE` ou `SET NULL`, conforme a dependência do filho, em conformidade com a Constituição.
