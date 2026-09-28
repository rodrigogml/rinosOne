# Contrato de Consulta de Referência Postal

Este contrato é uma fronteira de API para consumidores futuros. A feature entrega o domínio, a persistência e o processamento assíncrono; não entrega uma tela de CEP nem endereço final.

## `POST /api/v1/localities/postal-references/lookup`

Inicia ou acompanha a atualização compartilhada para um CEP e devolve imediatamente os candidatos locais ativos.

### Entrada

```json
{
  "countryCode": "BR",
  "postalCode": "01001-000"
}
```

- `countryCode`: ISO alpha-2, obrigatório.
- `postalCode`: obrigatório; é normalizado pelo servidor antes de consultar a base e os provedores.
- Os dois endpoints deste contrato usam o middleware autenticado já aplicado à API. Esta fundação não cria uma permissão funcional nova: quando uma funcionalidade de endereço consumir a consulta, ela deve aplicar sua própria autorização antes de invocá-la.

### Saída inicial — `200 OK`

```json
{
  "postalCode": {
    "countryCode": "BR",
    "displayValue": "01001-000"
  },
  "candidates": [
    {
      "id": 42,
      "kind": "STREET",
      "streetType": "Praça",
      "streetName": "da Sé",
      "neighborhoodName": "Sé",
      "municipality": { "id": 3550308, "name": "São Paulo", "stateAbbreviation": "SP" }
    }
  ],
  "refresh": {
    "state": "PENDING",
    "pollAfterMilliseconds": 1000
  }
}
```

- `candidates` contém somente `ACTIVE`, ordenados de forma determinística e provenientes da base local no instante da resposta.
- O endpoint enfileira uma atualização somente se não houver uma atualização ativa para a mesma chave `countryCode + normalizedPostalCode`.
- Não expõe provedor, *payload* bruto, mensagens de transporte ou detalhes de falha.

## `GET /api/v1/localities/postal-references/lookup-status`

Consulta a atualização em andamento, sem iniciá-la.

### Query string

`countryCode=BR&postalCode=01001-000`

### Saída — `200 OK`

```json
{
  "candidates": [
    {
      "id": 42,
      "kind": "STREET",
      "streetType": "Praça",
      "streetName": "da Sé",
      "neighborhoodName": "Sé",
      "municipality": { "id": 3550308, "name": "São Paulo", "stateAbbreviation": "SP" }
    }
  ],
  "refresh": {
    "state": "COMPLETED_WITH_ERRORS",
    "pollAfterMilliseconds": null
  }
}
```

### Estados de atualização

| Estado | Significado para o cliente |
| --- | --- |
| `PENDING` | A busca externa está em execução; a tela pode consultar novamente em até um segundo. |
| `COMPLETED` | Todos os adaptadores concluíram e o catálogo já foi consolidado. |
| `COMPLETED_WITH_ERRORS` | A atualização terminou com uma ou mais falhas isoladas; candidatos locais continuam válidos. |

Após `COMPLETED` ou `COMPLETED_WITH_ERRORS`, o cliente encerra o *polling*. Fechar a tela também encerra apenas o *polling*; a tarefa enfileirada não é cancelada.

## Semântica de consolidação

1. A requisição lê o catálogo local antes de qualquer chamada externa.
2. A tarefa assíncrona dispara ViaCEP e BrasilAPI em paralelo, quando habilitados.
3. Cada adaptador retorna um `PostalReferenceSourceRecord` interno com dados observados e informações de reconciliação, jamais o DTO de sua API pública.
4. O serviço associa a resposta ao Município IBGE apenas quando o código for válido e existir no catálogo oficial; caso contrário, preserva os textos observados.
5. Resultados válidos novos ficam imediatamente elegíveis. Resultado removido anteriormente é reconhecido pela observação/assinatura e não volta à seleção.
6. A regra de equivalência forte descrita em [research.md](../research.md) é a única autorizada a criar remoção automática nesta entrega.

## Erros de contrato

| Situação | HTTP | Código |
| --- | --- | --- |
| País ou código postal inválido | 422 | `LOCALITY_LOOKUP_VALIDATION_FAILED` |
| Sessão ausente ou inválida | 401 | Contrato de autenticação existente |
| Falha interna antes de iniciar a leitura local | 500 | `LOCALITY_LOOKUP_UNAVAILABLE` |

Falhas de fontes não são retornadas como erro deste contrato se a consulta local puder ser respondida.
