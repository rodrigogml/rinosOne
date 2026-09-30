# Contrato de indisponibilidade por compatibilidade de schema

Este contrato define a resposta comum quando uma operação funcional não pode ser atendida porque o schema necessário não está compatível. Todos os payloads usam `camelCase`.

## Bloqueio global

**Aplicação**: páginas funcionais e endpoints da API quando o catálogo global estiver ausente, incompleto ou ilegível.

**Resposta**: `503 Service Unavailable`

```json
{
  "error": {
    "code": "PLATFORM_SCHEMA_INCOMPATIBLE",
    "message": "A plataforma está temporariamente indisponível para atualização."
  }
}
```

A resposta não contém identificadores, nomes de schema, migrations, versões, SQL, credenciais nem erro de infraestrutura.

## Bloqueio de organização

**Aplicação**: seleção de contexto ou operação direcionada a uma organização cujo catálogo esteja incompatível, em atualização ou com atualização falha.

**Resposta**: `503 Service Unavailable`

```json
{
  "error": {
    "code": "TENANT_SCHEMA_UNAVAILABLE",
    "message": "Esta organização está temporariamente indisponível para atualização."
  }
}
```

A interface usa o código para atualizar seu estado contextual e comunica a indisponibilidade sem exibir detalhes de operação.

## Exceções operacionais

Verificações de saúde e os mecanismos não HTTP indispensáveis para executar a recuperação permanecem fora deste contrato. Eles não expõem detalhes ao público e são definidos pelo plano operacional da implantação.
