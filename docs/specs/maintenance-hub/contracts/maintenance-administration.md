# Contrato — Administração de Manutenções

## Convenções

- Base: `/api/v1/platform/maintenance`.
- Autenticação obrigatória; autorização por action declarada em cada integração e avaliada pela fundação de permissões.
- Corpo, consulta e respostas usam `camelCase`.
- Dados técnicos, parâmetros e mensagens são filtrados por cada integração antes de compor a resposta.

## Operações previstas

| Operação | Método e caminho | Resultado |
| --- | --- | --- |
| Listar rotinas conhecidas | `GET /routines` | Estado, capacidades permitidas e resumo seguro por rotina. |
| Consultar rotina | `GET /routines/{routineKey}` | Detalhe, histórico técnico e capacidades dessa integração. |
| Consultar auditoria | `GET /audit` | Auditorias administrativas permitidas, com filtros por rotina e período. |
| Solicitar ação | `POST /routines/{routineKey}/actions/{action}` | Aceitação, recusa ou falha segura; cria auditoria. |

### Integração entregue: `financial-institution-catalog`

| Permission PLATFORM | Efeito |
| --- | --- |
| `platform.maintenance.financial-institution.read` | Permite descobrir a rotina, consultar detalhe, histórico e auditoria. |
| `platform.maintenance.financial-institution.synchronize` | Permite solicitar a action `SYNCHRONIZE`. |

`GET /routines` retorna `routines`; `GET /routines/{routineKey}` retorna `routine` com `lastExecution`, `executionHistory` e `administrativeAudits`; `GET /audit` retorna `administrativeAudits`. A leitura negada retorna uma resposta de rotina indisponível, sem enumerar rotinas. A action aceita retorna `execution`; uma recusa por autorização retorna `403 MAINTENANCE_ACTION_NOT_ALLOWED`, e uma execução já em curso retorna `409 MAINTENANCE_ALREADY_RUNNING`.

## Regras de resposta

- Uma capacidade ausente não é oferecida pela listagem nem pelo detalhe.
- A ausência da permission de leitura não revela a rotina. A ausência da permission de ação recusa a solicitação, não inicia execução e registra uma auditoria administrativa segura.
- Uma solicitação repetida retorna o resultado definido pela rotina e sempre deixa evidência de auditoria.
- Ações, parâmetros e estados não permitidos retornam erro administrativo seguro, sem iniciar execução.
- A alteração de agenda, quando existir, pertence à ação específica da integração; não há endpoint universal de agenda.
