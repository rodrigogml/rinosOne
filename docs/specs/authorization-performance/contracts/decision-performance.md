# Contrato: Decisão em Lote e Observabilidade

`checkBatch` recebe uma lista limitada de pedidos `check` válidos e devolve decisões na mesma ordem lógica, cada uma equivalente a `check` individual. Não aceita resultado de decisão prévio do cliente.

Métricas expõem contadores e histogramas agregados de allow/deny, duração, cache e lote. Não usam IDs, permission keys ou referências de recurso como labels.
