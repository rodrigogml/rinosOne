# Contrato de API — Ocasiões de Calendário

Os contratos são JSON versionados, usam `camelCase` e exigem autenticação existente. Esta feature não introduz permissões novas; a futura política de autorização poderá restringir as mutações sem alterar o formato dos recursos.

## Representação de definição

```json
{
  "id": 42,
  "name": "Corpus Christi",
  "occasionCategory": "OPTIONAL_DAY_OFF",
  "territorialScope": "COUNTRY",
  "locality": {
    "countryId": 1,
    "brazilStateId": null,
    "brazilMunicipalityId": null
  },
  "validity": { "from": null, "to": null },
  "recurrence": {
    "type": "EASTER_OFFSET",
    "easterOffsetDays": 60
  },
  "replacesOptionalDayOffDefinitionId": null,
  "createdAt": "2026-10-02T12:00:00.000000Z",
  "updatedAt": "2026-10-02T12:00:00.000000Z"
}
```

## Criar definição

**Método**: `POST /api/v1/platform/calendar-occasions`

### Request

| Campo | Tipo | Obrigatório | Validação |
| --- | --- | --- | --- |
| `name` | string | sim | 1 a 160 caracteres. |
| `occasionCategory` | enum | sim | `HOLIDAY`, `OPTIONAL_DAY_OFF`, `COMMEMORATIVE_DATE`. |
| `territorialScope` | enum | sim | `COUNTRY`, `BRAZIL_STATE`, `BRAZIL_MUNICIPALITY`. |
| `locality` | object | sim | Combinação compatível de identificadores territoriais. |
| `validity` | object | não | Datas ISO `YYYY-MM-DD`, com fim maior ou igual ao início. |
| `recurrence` | object | sim | Um e somente um conjunto de parâmetros do tipo informado. |
| `replacesOptionalDayOffDefinitionId` | integer | não | Definição ancestral compatível de ponto facultativo. |

**Resposta de sucesso**: `201` com a representação completa.

## Alterar ou excluir definição

| Operação | Método e caminho | Resultado |
| --- | --- | --- |
| Ler | `GET /api/v1/platform/calendar-occasions/{calendarOccasionId}` | `200` com definição completa. |
| Alterar | `PATCH /api/v1/platform/calendar-occasions/{calendarOccasionId}` | `200` com definição atual. |
| Excluir | `DELETE /api/v1/platform/calendar-occasions/{calendarOccasionId}` | `204` sem corpo. |

Atualização recebe os mesmos campos da criação, parcialmente, e sempre revalida a combinação completa resultante.

## Consultar definições

**Método**: `GET /api/v1/calendar-occasions`

| Query | Tipo | Obrigatório | Significado |
| --- | --- | --- | --- |
| `name` | string | não | Trecho do nome da definição, para busca textual administrativa. |
| `from` / `to` | date | juntos, não | Período para selecionar regras capazes de produzir ocorrência vigente. |
| `occasionCategory[]` | enum[] | não | Categorias desejadas. |
| `territorialScope[]` | enum[] | não | Escopos desejados. |
| `countryId` | integer | não | País da definição. |
| `brazilStateId` | integer | não | UF da definição. |
| `brazilMunicipalityId` | integer | não | Município da definição. |
| `page` / `perPage` | integer | não | Paginação de definições completas. |

**Resposta `200`**: coleção paginada de representações de definição. A resposta não contém `occurrenceDate`.

## Consultar ocorrências

**Método**: `GET /api/v1/calendar-occurrences`

| Query | Tipo | Obrigatório | Significado |
| --- | --- | --- | --- |
| `from` / `to` | date | sim | Intervalo inclusivo de ocorrência. |
| `countryId` | integer | sim | País do destino. |
| `brazilStateId` | integer | condicional | Obrigatório para destino brasileiro estadual ou municipal. |
| `brazilMunicipalityId` | integer | condicional | Identifica destino municipal brasileiro. |
| `occasionCategory[]` | enum[] | não | Categorias a incluir. |
| `territorialScope[]` | enum[] | não | Escopos das definições a incluir. |

### Resposta `200`

```json
{
  "occurrences": [
    {
      "definitionId": 87,
      "name": "Corpus Christi",
      "occasionCategory": "HOLIDAY",
      "territorialScope": "BRAZIL_MUNICIPALITY",
      "locality": {
        "countryId": 1,
        "brazilStateId": 2,
        "brazilMunicipalityId": 3
      },
      "occurrenceDate": "2027-05-27"
    }
  ]
}
```

A lista é ordenada por `occurrenceDate`, nome e `definitionId`. Não expõe ID próprio de ocorrência. A consulta retorna todas as definições independentes aplicáveis e suprime somente a ocorrência de ponto facultativo explicitamente substituída para o destino.

## Catálogo territorial para a administração

| Método e caminho | Resultado |
| --- | --- |
| `GET /api/v1/calendar-occasion-localities/countries` | Países ativos selecionáveis. |
| `GET /api/v1/calendar-occasion-localities/countries/{countryId}/brazil-states` | UFs brasileiras ativas do País. |
| `GET /api/v1/calendar-occasion-localities/brazil-states/{brazilStateId}/municipalities` | Municípios brasileiros ativos da UF. |

Essas consultas expõem apenas identificador, nome e, quando aplicável, sigla oficial. Não permitem alteração do catálogo territorial.

## Respostas de erro

| Status | Código | Situação |
| --- | --- | --- |
| `401` | Contrato de autenticação existente | Requisição sem autenticação válida. |
| `404` | `CALENDAR_OCCASION_NOT_FOUND` | Definição ou localidade solicitada inexistente. |
| `422` | `CALENDAR_OCCASION_VALIDATION_FAILED` | Campos obrigatórios, datas, recorrência ou escopo inválidos. |
| `422` | `CALENDAR_OCCASION_LOCALITY_INCOMPATIBLE` | País, UF e Município não formam hierarquia válida. |
| `422` | `CALENDAR_OCCASION_SUBSTITUTION_INVALID` | Vínculo não aponta para ponto facultativo ancestral equivalente, cria ciclo ou gera ambiguidade. |
