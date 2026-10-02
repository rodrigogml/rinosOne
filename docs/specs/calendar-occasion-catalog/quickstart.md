# Guia de Validação — Catálogo de Ocasiões de Calendário

## Cenário 1: Cadastrar e consultar uma definição anual

1. Criar uma definição `HOLIDAY` de escopo `COUNTRY` para Brasil, com data fixa anual em 25 de dezembro e vigência sem limites.
2. Consultar definições por categoria `HOLIDAY` e período de 2026-12-01 a 2026-12-31.
3. **Esperado**: a definição completa é retornada, com recorrência de data fixa e sem `occurrenceDate`.

## Cenário 2: Calcular ocorrências de regras distintas

1. Cadastrar uma data única para 2026-11-23, uma data anual em 25 de dezembro e uma data relativa à Páscoa com deslocamento `60`.
2. Consultar ocorrências do País Brasil entre 2026-01-01 e 2027-12-31.
3. **Esperado**: a data única aparece uma vez; a data fixa aparece em 2026 e 2027; a data relativa aparece uma vez por ano com a data correta do computus gregoriano.

## Cenário 3: Vigência e 29 de fevereiro

1. Cadastrar uma definição anual fixa em 29 de fevereiro com vigência indeterminada.
2. Consultar ocorrências entre 2027-01-01 e 2028-12-31.
3. **Esperado**: somente 2028-02-29 é retornada.
4. Definir fim de vigência em 2028-02-29 e repetir a consulta até 2029-12-31.
5. **Esperado**: a ocorrência de 2028 permanece; não há ocorrência posterior.

## Cenário 4: Promover ponto facultativo na localidade de destino

1. Criar ponto facultativo nacional anual fixo para Brasil.
2. Criar feriado para São Paulo/SP com a mesma recorrência e vinculá-lo ao ponto facultativo nacional.
3. Consultar ocorrências para São Paulo/SP e para outro Estado brasileiro no mesmo período.
4. **Esperado**: São Paulo recebe somente o feriado estadual; o outro Estado recebe somente o ponto facultativo nacional.
5. Criar um feriado municipal ligado a ponto facultativo estadual e consultar o Município e outro Município da mesma UF.
6. **Esperado**: somente o Município vinculado recebe o feriado municipal; o outro recebe o ponto facultativo estadual.

## Cenário 5: Rejeitar vínculo territorial inválido

1. Tentar criar data comemorativa em escopo estadual.
2. Tentar vincular um ponto facultativo inferior a um feriado superior ou criar ciclo entre duas definições.
3. **Esperado**: cada operação retorna erro de validação seguro, sem alterar registros existentes.

## Cenário 6: Roundtrip ponta a ponta

1. Criar uma definição por requisição real à API de administração e capturar a resposta.
2. Consultar a definição pela API de regras-base e comparar o payload com o contrato: `camelCase`, localidade, vigência e objeto `recurrence`.
3. Consultar ocorrências reais para um intervalo que a inclua e comparar o payload com o contrato: `definitionId`, `occurrenceDate`, categoria, escopo e localidade.
4. Abrir a administração web, recuperar a mesma definição e executar uma alteração válida.
5. **Esperado**: API, contrato e cliente apresentam os mesmos valores, e uma consulta de ocorrência posterior reflete a alteração.
