# Plano Técnico — Serviço Indicadores Econômicos

## Resumo

Implementar o serviço global `economic-indicators` no schema core. Ele sincroniza SELIC, IPCA, IGP-M, INCC, remuneração da poupança e PTAX de fechamento de USD/EUR a partir de fontes oficiais; expõe consultas apenas para serviços internos e integra uma rotina diária idempotente ao Hub de Manutenções existente.

## Contexto Técnico

| Aspecto | Decisão |
| --- | --- |
| Aplicação | Laravel, PHP, Eloquent e MySQL do core global. |
| Fontes | BCB SGS para indicadores; BCB PTAX OData para câmbio. |
| TLS de fonte | Validação de certificado sempre ativa. Em Windows, o conector pode usar a CA nativa do sistema (`BCB_USE_NATIVE_CA=true`) quando `BCB_CA_BUNDLE` não indicar bundle explícito; em outros ambientes, infraestrutura informa o bundle confiável quando necessário. |
| Execução | Uma rotina diária agendada, protegida por lock compartilhado e com recuperação manual autorizada pelo Hub. A primeira execução percorre o histórico completo de cada série em janelas de até 3.650 dias, dentro do limite de dez anos da fonte; se uma janela histórica esgotar a conexão, ela é dividida automaticamente até uma faixa atendida pela fonte. As posteriores usam sobreposição incremental. |
| Interface | Não há tela ou API pública de listagem de valores. O Hub exibe somente estado e histórico técnico seguro. |
| Consumo | Serviços internos usam uma porta de consulta; nenhum consumidor consulta tabelas diretamente. |

## Constitution Check

| Princípio | Status | Nota |
| --- | --- | --- |
| I. Escopo autorizado | PASS | Limita-se às séries aprovadas e não cria conversão financeira. |
| II. Fronteira API e domínio | PASS | Conectores externos e persistência ficam fora do serviço de consulta. |
| IV. Dados mínimos e configuração segura | PASS | Sem credenciais em código; logs não retêm payloads de fontes. |
| V. Mudanças verificáveis | PASS | Migrations, contratos internos, sincronização e Hub terão testes. |
| VI. Identidades e referências | PASS | Dados são globais e não possuem dependência inversa de tenant. |

## Arquitetura

1. Adaptadores SGS e PTAX traduzem respostas oficiais em registros normalizados e validam datas, valores e boletim de fechamento.
2. `EconomicIndicatorSynchronizationService` baixa a carga histórica inicial fora da transação de persistência, em janelas configuráveis compatíveis com o limite da fonte. Em `ConnectionException` de uma janela histórica, divide-a recursivamente sem sobreposição até obter uma resposta ou chegar a um único dia; depois persiste cada publicação de forma idempotente. Cargas posteriores usam somente a janela de sobreposição, criam revisão quando a fonte mudar um valor já conhecido e, antes de concluir, pedem a recomposição integral dos acumulados compatíveis.
3. `EconomicIndicatorAccumulationService` recompõe cada série `COMPOUND_PUBLISHED_RATE` da primeira observação vigente, com base 100, escala interna 36 e materialização decimal `HALF_UP` de 18 casas. A projeção persistida não entra no próximo cálculo. Poupança declara `NO_GLOBAL_ACCUMULATION` por ter períodos mensais sobrepostos.
4. `EconomicIndicatorQueryService` resolve indicador, PTAX ou projeção por índice vigente; ausência de dado nunca vira valor estimado.
5. `EconomicIndicatorMaintenanceService` coordena lock, histórico, auditoria manual e agenda diária. O histórico seguro conserva resultado por série, fonte, intervalo, criações, revisões e recomposições; uma falha identifica a série afetada sem armazenar payload externo.
6. `MaintenanceHubService` e o controlador existente registram explicitamente a rotina; não há descoberta genérica nem tela nova.

## Interface Design

N/A — não há superfície humana nova. A integração reutiliza o detalhe de rotina já existente no Hub exclusivamente para status, histórico e disparo manual autorizado.

## Estrutura de Projeto

```text
app/
  Domain/EconomicIndicator/
  Infrastructure/EconomicIndicator/
  Models/EconomicIndicator*.php
  Services/EconomicIndicator/
  Services/Maintenance/EconomicIndicatorMaintenanceService.php
database/migrations/core/
docs/specs/economic-indicators/
  contracts/economic-indicator-internal.md
```

## Convenções de Borda

| Camada | Convenção | Fonte da verdade |
| --- | --- | --- |
| Core DB | `camelCase`, BIGINT e FKs explícitas | migrations core |
| Serviços PHP | objetos tipados e `camelCase` | contratos internos |
| Fonte externa | formato do BCB isolado por adaptador | Infrastructure |
| Hub | payload existente em `camelCase` | contrato de manutenção |

O mapeamento fonte → registro normalizado pertence aos adaptadores; o mapeamento registro → modelo global pertence ao serviço de sincronização.

## Validação

- Migration cria somente tabelas globais, índices e constraints definidos no modelo.
- Carga repetida não duplica observações; revisão oficial preserva a versão anterior.
- Falha de fonte mantém o último dado válido e registra histórico técnico seguro.
- Consulta interna retorna o valor vigente ou ausência explícita.
- Agenda diária registra a rotina no scheduler e o lock impede execução concorrente.
- Primeira carga começa na data inicial definida para cada série e alcança a data de execução sem ultrapassar a janela máxima permitida pela fonte.
