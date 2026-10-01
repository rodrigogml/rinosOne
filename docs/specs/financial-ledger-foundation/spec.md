# Especificação da Feature Fundação de Razão Financeira

**Feature**: `financial-ledger-foundation`
**Criada**: 2026-10-01
**Status**: Draft
**Briefing**: [Módulo Financeiro](../../briefing/20261001-briefing-finance-module.md)

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Financeiro web | Web responsiva | Usuários de tenant com permissões financeiras | FULL | Manter contas, consultar saldo e extrato, registrar fatos manuais, confirmar, reverter e explicar sua origem. | Contas a pagar, contas a receber, conciliação, arquivos de pagamento e relatórios especializados. |
| API financeira | Other | Interfaces e integrações autorizadas | FULL | Consultar e comandar as operações financeiras da capacidade com regras de autorização, concorrência e repetição. | Regras de negócio dependentes de telas ou clientes específicos. |
| Referências econômicas | Other | Capacidade de câmbio futura | DEFERRED | Fornecerá referências globais quando o escopo de câmbio for especificado. | Determinar taxas, converter ou reavaliar valores nesta feature. |

## Cenários de Usuário e Testes

### História 1 — Controlar contas sem editar saldo (Prioridade: P1)

Como usuário financeiro autorizado, quero manter as contas controladas pela organização e consultar seus saldos derivados, para acompanhar recursos sem manipular saldo atual diretamente.

**Por que esta prioridade**: conta e saldo explicável são a base de todo o domínio financeiro.

**Teste independente**: criar uma conta com abertura, consultar seu saldo e verificar que uma tentativa de editar o saldo atual é recusada.

**Cenários de aceite**:

1. **Dado** uma conta financeira ativa com moeda base, **quando** ela recebe um saldo de abertura confirmado, **então** o saldo consultado reflete esse único fato de abertura.
2. **Dado** uma conta com fatos confirmados, **quando** o usuário consulta uma data de corte, **então** recebe o saldo derivado apenas dos fatos efetivos até aquela data.
3. **Dado** uma conta com histórico monetário, **quando** o usuário tenta excluí-la como operação comum, **então** a exclusão é recusada e o ciclo de vida permitido permanece disponível.

---

### História 2 — Registrar e corrigir um fato monetário (Prioridade: P1)

Como usuário financeiro autorizado, quero confirmar um lançamento de entrada ou saída e corrigir um erro por reversão rastreável, para que o saldo e sua história econômica permaneçam confiáveis.

**Por que esta prioridade**: o fato confirmado é a única fonte de alteração de saldo e não pode ser reescrito silenciosamente.

**Teste independente**: confirmar uma saída em uma conta, consultar o saldo e o extrato, reverter o fato e comprovar que ambos os registros explicam o resultado final.

**Cenários de aceite**:

1. **Dado** um lançamento válido com direção e valor positivo, **quando** ele for confirmado, **então** cria exatamente um efeito monetário na conta escolhida e altera o saldo derivado.
2. **Dado** um fato confirmado, **quando** o usuário precisa corrigir seus atributos econômicos, **então** o sistema preserva o original e registra uma reversão ou correção vinculada.
3. **Dado** duas solicitações concorrentes baseadas em uma versão desatualizada, **quando** a segunda tenta confirmar ou alterar o mesmo objeto mutável, **então** ela é recusada sem duplicar efeito monetário.

---

### História 3 — Proteger o período financeiro e explicar uma ação crítica (Prioridade: P2)

Como responsável financeiro, quero fechar um período e consultar o histórico das ações relevantes, para proteger fatos consolidados sem perder capacidade de auditoria.

**Por que esta prioridade**: proteção temporal e explicabilidade impedem correções invisíveis em valores já conferidos.

**Teste independente**: fechar uma data, tentar registrar alteração econômica anterior a ela, reabrir com justificativa autorizada e verificar o histórico imutável das ações.

**Cenários de aceite**:

1. **Dado** um tenant com data de fechamento, **quando** uma operação tenta gravar ou alterar efeito econômico em data protegida, **então** ela é recusada antes de alterar o saldo.
2. **Dado** um usuário sem autorização específica, **quando** tenta reabrir o fechamento, **então** não altera o período nem visualiza dados além de sua permissão.
3. **Dado** uma confirmação, reversão ou mudança de fechamento concluída, **quando** um auditor autorizado consulta o histórico, **então** identifica ator, momento, ação, alvo, origem e correlação da operação.

### Casos de Borda

- Valor zero ou valor negativo informado como entrada não pode produzir um fato monetário confirmado.
- Uma conta inativa ou encerrada não pode receber novos fatos ordinários incompatíveis com seu estado.
- Uma repetição de comando mutável não pode criar uma segunda abertura, lançamento, reversão ou mudança de fechamento.
- O extrato deve distinguir data efetiva, criação e confirmação quando elas forem diferentes.
- A consulta de uma conta, saldo ou identificador bancário não pode atravessar a organização ativa nem exceder a permissão aplicável.

## Requisitos

### Requisitos Funcionais

- **FR-FLF-001**: O sistema DEVE manter contas financeiras pertencentes exclusivamente à organização ativa, cada uma com nome, natureza, estado e exatamente uma moeda base.
- **FR-FLF-002**: Contas bancárias DEVEM poder referenciar uma instituição financeira global e preservar seus identificadores conforme as permissões aplicáveis.
- **FR-FLF-003**: O saldo de uma conta DEVE ser calculado a partir de sua abertura e dos fatos monetários confirmados; o saldo atual não pode ser editado livremente.
- **FR-FLF-004**: O saldo de abertura DEVE ser um fato financeiro específico e uma conta não pode possuir mais de uma abertura ativa.
- **FR-FLF-005**: Cada fato monetário confirmado DEVE afetar exatamente uma conta e declarar direção de entrada ou saída com valor absoluto positivo.
- **FR-FLF-006**: Fatos confirmados DEVEM preservar data efetiva distinta de momentos de criação e confirmação quando aplicável.
- **FR-FLF-007**: Um fato confirmado não pode ser silenciosamente reescrito; correções DEVEM preservar relação explícita com a reversão ou o fato corretivo correspondente.
- **FR-FLF-008**: O sistema DEVE permitir consultar saldo por data de corte e extrato detalhado de uma conta, respeitando autorização para valores e identificadores bancários.
- **FR-FLF-009**: Operações que possam aplicar efeito monetário em duplicidade DEVEM usar a política geral de idempotência da plataforma e rejeitar conflito de concorrência sem aplicar resultado parcial.
- **FR-FLF-010**: O fechamento financeiro DEVE impedir operações ordinárias que alterem fatos com data efetiva protegida; reabertura exige autorização específica e justificativa auditável.
- **FR-FLF-011**: Confirmações, reversões, ajustes, mudanças de fechamento e demais ações financeiras relevantes DEVEM manter histórico com ator, momento, ação, alvo, origem e correlação, sem replicar dados bancários sensíveis desnecessariamente.
- **FR-FLF-012**: A feature DEVE reutilizar Pessoas como contrapartes quando aplicável, a autorização de tenant, os arquivos privados e o catálogo de instituições financeiras existentes; não deve criar equivalentes locais.
- **FR-FLF-013**: A classificação econômica de fatos independentes DEVE ser exigida quando a capacidade de classificação financeira estiver disponível; a confirmação não pode ignorar regras de classificação aplicáveis.
- **FR-FLF-014**: Cartão de crédito DEVE ser admitido apenas como natureza de conta nesta fundação; fatura, fechamento, vencimento, pagamento e conciliação próprios ficam adiados para `finance-credit-card-cycle`.
- **FR-FLF-015**: Esta fundação DEVE preservar uma única moeda base por conta, mas NÃO DEVE converter valores, selecionar cotações nem apurar diferenças cambiais; essas regras pertencem a `finance-currency-and-valuation`, dependente de `economic-indicators`.

### Entidades Principais

- **Conta financeira**: recurso financeiro controlado ou acompanhado pela organização, com moeda base, estado e identificadores bancários opcionais.
- **Fato financeiro**: efeito monetário confirmado, com direção, valor, conta, data efetiva, origem e vínculo opcional a uma correção ou origem de negócio.
- **Saldo de abertura**: fato financeiro único que inicia a composição do saldo de uma conta.
- **Fechamento financeiro**: marco temporal do tenant que protege fatos econômicos anteriores ou iguais à sua data.
- **Evento de auditoria financeira**: registro explicativo de uma ação crítica, sem substituir a história econômica dos fatos.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-FLF-001**: 100% dos cenários aprovados calculam o saldo como abertura mais entradas confirmadas menos saídas confirmadas até a data solicitada.
- **SC-FLF-002**: 100% dos cenários aprovados de correção preservam o fato original e uma relação explícita com sua reversão ou correção.
- **SC-FLF-003**: 100% dos cenários aprovados de repetição e conflito de concorrência produzem no máximo um efeito monetário confirmado.
- **SC-FLF-004**: 100% dos cenários aprovados de período fechado recusam a alteração econômica protegida antes de qualquer mudança de saldo.
