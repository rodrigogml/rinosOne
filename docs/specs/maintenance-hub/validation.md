# Evidências de Validação

**Data**: 2026-09-26
**Escopo**: Central de Manutenções e sincronização de instituições financeiras.

## Cenários operacionais

| Cenário | Evidência | Resultado |
| --- | --- | --- |
| Consulta centralizada e ausência de execução | `MaintenanceHubServiceTest` e `MaintenanceApiTest` | Usuário sem leitura não descobre a rotina; operador autorizado consulta estado, histórico e auditoria seguros. |
| Disparo manual e auditoria | `FinancialInstitutionMaintenanceServiceTest`, `MaintenanceHubServiceTest` e `MaintenanceApiTest` | A solicitação permitida cria auditoria administrativa e histórico técnico separados. |
| Recusa de ação | `MaintenanceApiTest` e `FinancialInstitutionMaintenanceServiceTest` | Usuário sem permissão recebe recusa sem iniciar a rotina; solicitação concorrente é recusada e auditada. |
| Agenda diária e singleton | `php artisan schedule:list` e `FinancialInstitutionMaintenanceServiceTest` | Existe somente `maintenance.financial-institution-catalog`, diária; o lock da rotina impede execução concorrente. |
| Retenção independente | `MaintenanceRetentionServiceTest` | Histórico técnico e auditoria administrativa expiram por seus próprios prazos, com padrão de 90 dias. |

## Revisão de segurança de dados

| Fronteira | Verificação | Resultado |
| --- | --- | --- |
| Logs da sincronização BCB | `FinancialInstitutionSynchronizationService` | Registra apenas origem, data de referência, contadores e classe de falha; não registra payload, URL, CNPJ, nome de instituição ou segredo. |
| Histórico técnico | `FinancialInstitutionMaintenanceService` e `MaintenanceHubService` | A visão administrativa expõe estado, gatilho, datas, resumo e contadores. Detalhes internos, inclusive `failureCode`, não atravessam o read model. |
| Auditoria administrativa | `MaintenanceHubService` e `MaintenanceController` | A resposta contém somente identificador do operador, ação, resultado e instante. Não há parâmetros sensíveis nesta ação. |
| Descoberta e ação | `MaintenanceApiTest` | A falta de leitura resulta em ausência da rotina; ação não autorizada é recusada sem iniciar sincronização. |
| Interface web | `MaintenanceHubSurface` e E2E Chrome | A tela usa campos do contrato seguro e não exibe dados técnicos internos; confirmação comunica somente a origem oficial e a regra de concorrência. |

> [!NOTE]
> O relatório não substitui a revisão individual das rotinas ainda não integradas. Consulte [routine-integration-review.md](routine-integration-review.md) antes de ampliar o hub.

## Execuções registradas

```text
php artisan test --filter='(FinancialInstitutionMaintenanceServiceTest|MaintenanceHubServiceTest|MaintenanceApiTest|MaintenanceRetentionServiceTest)'
14 passed, 71 assertions

PLAYWRIGHT_BROWSER_CHANNEL=chrome PLAYWRIGHT_PORT=8011 npm run test:e2e -- --grep "opens the authorized maintenance hub"
1 passed

npm run type-check
passed

npm test
98 passed
```

As capturas E2E de telefone registram a confirmação, o sucesso e o alcance do histórico pela região rolável da janela de trabalho.
