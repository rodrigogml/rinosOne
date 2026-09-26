# Plano Técnico — Central de Manutenções

## Resumo

Construir uma área administrativa de manutenção que conhece integrações específicas, exibe suas capacidades e solicita ações ao respectivo motor. A central não definirá um protocolo genérico de rotina nem absorverá regras de agenda, concorrência, retentativa ou instâncias. A primeira integração será a atualização diária do catálogo de instituições financeiras.

## Contexto Técnico

| Aspecto | Decisão |
| --- | --- |
| Backend | Laravel/PHP e API JSON versionada existente |
| Interface | SPA web responsiva existente, com Vue 3 e TypeScript |
| Persistência | MySQL no schema global `rinosone` |
| Autorização | Ações declaradas por integração; vínculo definitivo pendente da fundação de permissões |
| Agenda | Pontos explícitos por integração; instituições financeiras executa diariamente |
| Retenção | Histórico técnico e auditoria administrativa: 90 dias padrão, configurações independentes; auditoria imutável até expirar |

## Arquitetura de Integração

1. O módulo do hub contém uma integração explícita para cada rotina conhecida.
2. A integração declara, no próprio hub, a apresentação, capacidades e ações que a rotina permite expor.
3. Para ações permitidas, a integração chama o serviço ou motor já pertencente à rotina alvo.
4. O hub registra histórico técnico seguro e auditoria administrativa separada.
5. A agenda diária de instituições financeiras é definida em seu ponto específico na central e chama o serviço de sincronização existente; não há agenda adicional dentro do catálogo financeiro.

> [!IMPORTANT]
> Não será criado contrato plugável, tabela de cadastro de rotinas ou motor universal. Adicionar uma nova manutenção exigirá uma integração específica e a revisão de seus requisitos.

## Arquitetura das Superfícies

- `SURF-WEB-MAINTENANCE`: área web administrativa responsiva para administradores da Plataforma; **Interface Design: REQUIRED**.
- `SURF-FUTURE-CONSUMERS`: API administrativa diferida, sem consumidor externo nesta fase.
- A interface exibe apenas capacidades fornecidas pela integração específica; não renderiza controles genéricos de agenda ou disparo.

## Constitution Check

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental | PASS | Integrações explícitas evitam abstração genérica antecipada. |
| II. Fronteira API e domínio | PASS | Regras de integração e persistência ficam no backend; web consome contratos versionados. |
| III. Identidade e acesso | PASS condicionado | Autorização por rotina será conciliada antes da ativação efetiva das ações. |
| IV. Dados mínimos e configuração segura | PASS | Apenas contexto seguro é persistido; retenções são configuráveis sem segredos. |
| V. Mudanças verificáveis | PASS | Cenários incluem web, API, agendas, auditoria, retenção e integração financeira. |
| VI. Identidades e referências | PASS | Tabelas core usam BIGINT; não há referência a tenant; FK para usuário usa cascatas explícitas. |

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Colunas do banco | camelCase | migration e testes de schema | migrations core |
| Modelos e DTOs backend | camelCase | testes de serviço | contratos e modelos backend |
| Payload API | camelCase | request/response e testes de integração | `contracts/maintenance-administration.md` |
| Tipos da interface | camelCase | verificação de tipos e testes de componente | contrato API e tipos TypeScript |
| Caminhos de API | kebab-case | testes de rota | contrato API |

**Mapeamento DB ↔ resposta**: controladores e serviços do módulo de manutenção compõem respostas seguras a partir das entidades de histórico e das integrações específicas.

## Estrutura de Projeto Planejada

```text
app/
  Http/Controllers/Api/V1/Platform/Maintenance/
  Models/
  Services/Maintenance/
  Services/FinancialInstitution/
database/migrations/core/
resources/js/
  maintenance/
  design-system/
tests/
  Feature/
  js/maintenance/
docs/specs/maintenance-hub/
```

## Modelo de Dados e Contratos

- [data-model.md](data-model.md) define o histórico técnico e a auditoria administrativa, ambos core e com retenção independente.
- [maintenance-administration.md](contracts/maintenance-administration.md) define a fronteira administrativa versionada.
- Não haverá tabela de rotinas; `routineKey` identifica a integração específica e não referencia uma entidade persistida.

## Validação

- Ações não declaradas por uma integração nunca aparecem na interface nem iniciam execução.
- Cada solicitação administrativa cria auditoria, inclusive quando recusada.
- Histórico técnico e auditoria obedecem suas retenções independentes.
- A rotina de instituições financeiras é chamada somente pela agenda específica do hub ou por solicitação manual permitida.
- Um fluxo web crítico percorre interface, contrato, backend, auditoria e estado exibido.

## Complexidade

Nenhuma violação de princípios. A integração explícita por rotina é complexidade deliberada e aprovada para preservar suas regras independentes.
