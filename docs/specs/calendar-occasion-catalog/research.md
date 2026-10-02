# Pesquisa Técnica — Catálogo de Ocasiões de Calendário

**Data**: 2026-10-02
**Escopo**: decisões técnicas verificadas para persistir definições de ocasiões e calcular suas ocorrências por localidade.

## Decisões

| Tema | Decisão | Fundamentação |
| --- | --- | --- |
| Localidades | Reutilizar `country`, `brazilState` e `brazilMunicipality` do schema global. | A fundação de localidades já atribui identidades `BIGINT` e hierarquia oficial ao Brasil; criar catálogo paralelo causaria divergência. |
| Unidade temporal | Persistir e calcular somente `DATE`, sem horário e sem fuso horário. | A capacidade representa datas civis; jornada e expediente pertencem aos módulos consumidores. |
| Recorrência | Representar os quatro tipos fechados aprovados em uma definição, com parâmetros mutuamente exclusivos. | Preserva consultas simples e evita criar um interpretador genérico de regras de calendário antes de haver necessidade. |
| Páscoa | Calcular Páscoa pelo computus gregoriano-eclesiástico e aplicar deslocamento inteiro em dias. | Paixão de Cristo e Corpus Christi dependem dessa referência, não de observação astronômica do ciclo lunar. |
| Biblioteca de cálculo | Usar `easter_days($year, CAL_EASTER_ALWAYS_GREGORIAN)` e `DateTimeImmutable`, declarando `ext-calendar` como requisito de runtime. | A função nativa calcula fora da limitação de timestamps Unix e permite selecionar calendário gregoriano sempre; o ambiente local já disponibiliza a extensão. |
| Ocorrências | Calcular sob demanda, sem tabela de instâncias. | O catálogo é pequeno, alterações devem refletir consultas futuras e consumidores não exigem ID próprio de ocorrência. |
| Substituição territorial | Auto-referência dirigida da definição filha para o ponto facultativo ancestral substituído. | Explica explicitamente por que uma definição inferior suprime uma superior, sem deduzir relação por nome ou data. |
| Exclusão | Permitir exclusão normal; exclusão de ancestral desvincula a substituição de suas filhas. | Respeita o comportamento aprovado e evita bloquear ou apagar uma definição inferior somente porque a ancestral foi removida. |

## Cálculo de recorrências

| Tipo | Campos persistidos | Cálculo para um ano | Casos relevantes |
| --- | --- | --- | --- |
| `ONE_TIME_DATE` | `oneTimeDate` | Retorna a data somente se estiver no intervalo. | Nunca reaparece em outro ano. |
| `ANNUAL_FIXED_DATE` | `fixedMonth`, `fixedDay` | Constrói a data no ano solicitado. | 29 de fevereiro só existe em ano bissexto; não é deslocado. |
| `ANNUAL_NTH_WEEKDAY` | `weekMonth`, `weekOrdinal`, `weekDay` | Localiza a ocorrência ordinal do dia da semana no mês. | O quinto ordinal pode não existir; `LAST` usa a última ocorrência disponível. |
| `EASTER_OFFSET` | `easterOffsetDays` | Calcula a Páscoa gregoriana e soma/subtrai dias. | Paixão de Cristo `-2`; Corpus Christi `+60`; offsets negativos podem cruzar para março. |

O validador constrói datas com formato estrito e confirma erros de overflow antes de persistir. Não usa interpretação textual relativa, pois ela pode depender do contexto de data atual.

## Regras da relação de substituição

1. A filha declara `idReplacesOptionalDayOff` para uma ancestral de categoria `OPTIONAL_DAY_OFF`.
2. A filha deve pertencer ao mesmo País e a uma localidade estritamente mais específica e contida na localidade ancestral.
3. A filha pode ser `OPTIONAL_DAY_OFF` ou `HOLIDAY`; uma ancestral `HOLIDAY` nunca é elegível para ser rebaixada.
4. Filha e ancestral devem ter recorrências equivalentes e ao menos uma vigência comum. Assim, o vínculo representa a mesma ocasião, não apenas uma coincidência textual.
5. Cada filha tem no máximo uma ancestral substituída. A validação percorre os ancestrais para rejeitar ciclos.
6. Para uma mesma ancestral, data e destino, não pode haver duas filhas aplicáveis de mesma especificidade; o cadastro rejeita a ambiguidade.
7. Na consulta de ocorrências, cada filha aplicável suprime apenas a ocorrência ancestral ligada. Definições sem vínculo permanecem independentes e são retornadas todas.

## Fontes consultadas

- [PHP Manual — `easter_days`](https://www.php.net/manual/en/function.easter-days.php): função e modos de calendário para determinar a Páscoa, inclusive fora da faixa de timestamps Unix.
- [PHP Manual — constantes de calendário](https://www.php.net/manual/en/calendar.constants.php): `CAL_EASTER_ALWAYS_GREGORIAN` seleciona o calendário gregoriano proléptico.
- [PHP Manual — `DateTimeImmutable::createFromFormat`](https://www.php.net/manual/en/datetimeimmutable.createfromformat.php): parsing exige verificar avisos de overflow para não aceitar datas normalizadas implicitamente.
- [US Naval Observatory — Date of Easter](https://aa.usno.navy.mil/faq/easter): referência sobre o cálculo eclesiástico da Páscoa.
- [Fundação de Localidades](../locality-foundation/data-model.md): catálogo territorial e regras de identidade já existentes no core.

## Alternativas rejeitadas

| Alternativa | Motivo para não adotar agora |
| --- | --- |
| Tabela materializada de ocorrências por ano | Cria sincronização e histórico que a feature não exige; alterações normais passariam a demandar recomputação. |
| Motor de expressões ou regras de recorrência livres | Amplia excessivamente a superfície de validação e não é necessário para os quatro tipos aprovados. |
| Cálculo por lua astronômica | Não reproduz necessariamente o calendário eclesiástico usado para as datas brasileiras relacionadas à Páscoa. |
| Inferir promoção por mesmo nome ou mesma data | Pode suprimir ocasiões independentes e contradiz o requisito de retornar coincidências não vinculadas. |
| Guardar uma cópia de País, UF e Município nas definições | Duplicaria o catálogo territorial e enfraqueceria a validação de pertença. |
