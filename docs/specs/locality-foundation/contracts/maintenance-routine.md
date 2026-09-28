# Contrato de Integração — Rotina Territorial IBGE no Hub

`locality-ibge-territory-catalog` é uma rotina conhecida explicitamente pela Central de Manutenções. Ela não implementa nem cria um contrato genérico de descoberta de rotinas.

## Capacidades

| Propriedade | Valor |
| --- | --- |
| `routineKey` | `locality-ibge-territory-catalog` |
| Permissão de leitura planejada | `platform.maintenance.locality-ibge.read` no escopo Plataforma |
| Agenda | `auto`: primeira execução devida sem sucesso anterior; após sucesso, mensal; após falha, retentativa controlada em seis horas configuráveis. |
| Concorrência | Singleton por lock compartilhado. Uma tentativa concorrente é recusada sem gerar segunda execução. |
| Execução manual | Não suportada. |
| Histórico | `maintenanceExecutionHistory`, com retenção configurável existente. |
| Auditoria administrativa | Não aplicável nesta fase, porque não há ação administrativa. |

## Extensão dos endpoints existentes

O Hub continua a usar seus endpoints administrativos existentes. A implementação acrescentará esta rotina a `GET /api/v1/platform/maintenance/routines` e ao detalhe já existente, condicionados à permissão de leitura. A representação deve incluir:

```json
{
  "routineKey": "locality-ibge-territory-catalog",
  "title": "Localidades brasileiras",
  "description": "Atualiza o catálogo territorial global com dados oficiais do IBGE.",
  "state": "NOT_EXECUTED",
  "scheduleDescription": "Inicial automática e mensal",
  "capabilities": {
    "canSynchronize": false
  }
}
```

O endpoint de ação de sincronização existente deve responder `404 MAINTENANCE_ROUTINE_NOT_AVAILABLE` para esta `routineKey`: não se cria botão, permissão de disparo ou rota alternativa para contornar a regra da rotina.

## Resultado seguro no histórico

`details` pode conter somente contagens técnicas seguras: `createdCountryCount`, `updatedCountryCount`, `createdStateCount`, `updatedStateCount`, `createdMunicipalityCount`, `updatedMunicipalityCount` e `failureCode`. A interface não expõe URL, *payload*, exceção ou cabeçalhos da fonte.
