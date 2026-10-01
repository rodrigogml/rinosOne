# Especificação do Serviço Indicadores Econômicos

**Feature**: `economic-indicators`
**Criada**: 2026-10-01
**Status**: Implementada e validada localmente; pronta para promoção controlada.
**Briefing**: [Módulo Financeiro](../../briefing/20261001-briefing-finance-module.md)

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Serviço interno de indicadores | Other | Capacidades autorizadas da plataforma | FULL | Consultar um indicador ou cotação identificado, sua fonte, data de referência e revisão aplicável. | Conversão de valores, contabilização de variações e edição livre pelo usuário. |
| Administração operacional | Web responsiva | Operadores de plataforma autorizados | DEFERRED | Consultar o resultado seguro de atualização quando a capacidade de operação correspondente estiver aprovada. | Cadastrar ou alterar manualmente valores oficiais. |
| Financeiro web | Web responsiva | Usuários de tenant autorizados | DEFERRED | Consumirá referências validadas quando a capacidade de câmbio estiver aprovada. | Escolher taxa, converter ou registrar variação cambial nesta feature. |

## Clarificações

### Sessão 2026-10-01

- Pergunta: A carga inicial deve usar somente a janela de sobreposição ou todo o histórico publicado? → Resposta: toda série deve ser carregada desde a primeira data disponível na fonte oficial; apenas cargas posteriores usam a janela incremental idempotente.
- Pergunta: Como o acumulado deve evitar deriva por arredondamentos e por revisões? → Resposta: deve ser recomposto, em toda atualização bem-sucedida, exclusivamente a partir dos valores brutos vigentes, sem usar o acumulado persistido como entrada; a base da primeira observação é 100 e só o valor final de cada ponto é arredondado para armazenamento.
- Pergunta: A rentabilidade diária publicada para Poupança pode compor um índice global diário? → Resposta: não. Cada publicação representa remuneração mensal vinculada ao período de aniversário da conta; como os períodos das datas consecutivas se sobrepõem, a série fica disponível como referência oficial, sem acumulado global nem cálculo de correção.

## Cenários de Usuário e Testes

### História 1 — Consultar uma referência econômica verificável (Prioridade: P1)

Como capacidade autorizada da plataforma, quero consultar um indicador econômico por série e data, para fundamentar uma operação futura sem perder a origem do dado utilizado.

**Por que esta prioridade**: referências sem fonte, vigência e revisão não podem sustentar uma decisão financeira auditável.

**Teste independente**: consultar uma observação disponível e comprovar que o resultado identifica série, valor, unidade, fonte, data de referência e versão aplicável.

**Cenários de aceite**:

1. **Dado** uma observação oficial disponível para uma série e data, **quando** um consumidor autorizado a consulta, **então** recebe o valor acompanhado de sua identidade, fonte e contexto temporal.
2. **Dado** que não exista observação aplicável para a data solicitada, **quando** o consumidor a consulta, **então** a ausência é distinguida de um valor igual a zero e nenhuma taxa é inferida.

---

### História 2 — Preservar correções oficiais sem reescrever o passado (Prioridade: P1)

Como responsável por uma capacidade financeira, quero identificar quando uma fonte oficial revisou uma observação, para usar a versão correta em novas decisões e ainda explicar referências usadas anteriormente.

**Por que esta prioridade**: séries econômicas podem ser retificadas; reescrever silenciosamente uma referência já utilizada destrói rastreabilidade.

**Teste independente**: registrar uma revisão oficial de uma observação e comprovar que a revisão vigente é consultável sem apagar a observação anterior ou suas referências históricas.

**Cenários de aceite**:

1. **Dado** uma observação já publicada, **quando** a fonte divulgar uma revisão identificável, **então** o sistema registra a nova versão e preserva a anterior com seu estado histórico.
2. **Dado** uma capacidade que registrou uma referência anterior, **quando** a revisão posterior existir, **então** a referência original continua explicável pela sua identidade e versão.

---

### História 3 — Atualizar dados sem duplicação (Prioridade: P2)

Como operador da plataforma, quero que a atualização de uma série autorizada seja repetível e rastreável, para não multiplicar observações nem ocultar falhas de origem.

**Por que esta prioridade**: a confiabilidade do catálogo é pré-requisito para o uso futuro de PTAX e outros índices.

**Teste independente**: executar duas atualizações equivalentes de uma mesma fonte e verificar que a segunda não cria observações ou revisões duplicadas.

**Cenários de aceite**:

1. **Dado** uma atualização já concluída para determinada fonte e conteúdo, **quando** ela for repetida, **então** o catálogo converge para o mesmo conjunto de observações.
2. **Dado** uma falha de origem, **quando** a atualização terminar sem sucesso, **então** as observações previamente válidas permanecem consultáveis e o resultado seguro da falha fica disponível à operação autorizada.

---

### História 4 — Corrigir um valor por índice acumulado reproduzível (Prioridade: P1)

Como capacidade autorizada da plataforma, quero consultar o acumulado e projetar um valor entre duas datas publicadas de um índice compatível, para aplicar a mesma regra de correção de modo auditável e sem deriva de arredondamento.

**Por que esta prioridade**: um acumulado que encadeia valores previamente arredondados pode divergir quando a série é revisada ou atualizada repetidamente.

**Teste independente**: sincronizar valores percentuais, alterar uma observação publicada e comprovar que todos os acumulados vigentes posteriores são recompostos a partir dos valores brutos, sem depender do acumulado anteriormente gravado.

**Cenários de aceite**:

1. **Dado** um índice de composição aprovada, **quando** uma atualização bem-sucedida concluir, **então** a primeira observação vigente possui base 100 e cada ponto posterior representa a composição cronológica dos valores brutos vigentes.
2. **Dado** um acumulado armazenado que foi alterado indevidamente, **quando** a atualização idempotente da série concluir, **então** o valor correto é reconstruído dos valores brutos, sem utilizar aquele valor armazenado como entrada.
3. **Dado** duas datas exatas com publicação vigente, **quando** um consumidor solicitar a projeção de um valor, **então** recebe fator, taxa percentual e valor atualizado calculados a partir das mesmas observações vigentes.

### Casos de Borda

- Uma referência inexistente, indisponível ou ainda não publicada não pode ser substituída por zero, última cotação ou estimativa sem uma política explícita da série.
- Uma revisão oficial não pode alterar silenciosamente o contexto de uma decisão financeira que já registrou a versão usada.
- Repetição, concorrência ou interrupção de atualização não pode produzir duas observações economicamente equivalentes para a mesma identidade de fonte e versão.
- Uma organização não pode criar, alterar ou consultar séries globais fora das autorizações da plataforma.
- O acumulado de uma versão histórica não pode ser apresentado como acumulado vigente: somente a observação corrente de cada data recebe a projeção materializada.
- Datas sem observação publicada não são interpoladas para cálculo de correção; a consulta deve informar ausência segura.
- A rentabilidade de Poupança não pode ser composta entre datas consecutivas, pois seus períodos mensais de aniversário se sobrepõem.

## Requisitos

### Requisitos Funcionais

- **FR-EI-001**: O core global DEVE manter indicadores econômicos como dados reutilizáveis, sem cópia operacional por tenant.
- **FR-EI-002**: Cada série DEVE declarar sua finalidade, unidade, calendário de referência e fonte autorizada antes de disponibilizar observações a consumidores.
- **FR-EI-003**: Cada observação DEVE preservar valor decimal exato, data de referência, momento de publicação ou captura quando disponível, fonte e identidade de versão ou revisão.
- **FR-EI-004**: O sistema DEVE distinguir uma observação ausente de um valor econômico igual a zero.
- **FR-EI-005**: Uma atualização repetida com a mesma identidade de origem e versão DEVE ser idempotente.
- **FR-EI-006**: Correções oficiais DEVEM preservar a observação anterior e indicar qual versão é vigente para novas consultas, sem reescrever referências históricas já registradas por consumidores.
- **FR-EI-007**: O sistema DEVE disponibilizar consultas somente a consumidores internos autorizados e registrar a origem de atualizações sem expor segredos ou conteúdo desnecessário de fonte externa.
- **FR-EI-008**: O serviço NÃO DEVE converter moedas, calcular equivalências, lançar fatos financeiros, aceitar edição ordinária de valores ou criar uma interface de configuração para tenants.
- **FR-EI-009**: A primeira entrega DEVE disponibilizar as séries SELIC, IPCA, IGP-M, INCC e remuneração da poupança, com sua periodicidade, unidade, fonte e regra de acumulação declaradas pela própria série.
- **FR-EI-010**: A primeira entrega DEVE disponibilizar PTAX de fechamento para USD e EUR como referência de câmbio, preservando compra e venda publicadas e identificando de modo explícito qualquer cotação derivada.
- **FR-EI-011**: A remuneração da poupança DEVE ser tratada como série de referência publicada; o serviço NÃO DEVE calcular remuneração individual de uma conta de poupança.
- **FR-EI-INFRA-SCHED**: A atualização DEVE ser centralizada na operação de manutenções da plataforma: séries diárias são verificadas ao menos a cada dia útil após a janela configurada de publicação, séries mensais ao menos uma vez por mês após sua publicação, e uma reexecução manual autorizada pode recuperar período definido. Toda atualização periódica deve usar uma janela de sobreposição configurável.
- **FR-EI-INFRA-IDEMP**: Atualizações DEVEM usar uma identidade de origem estável por série, referência temporal e versão, de modo que repetição e recuperação após interrupção não publiquem duplicidades.
- **FR-EI-012**: A primeira entrega de PTAX DEVE ser limitada a USD e EUR; uma moeda adicional exige especificação aprovada de série, fonte, data inicial e política de atualização.
- **FR-EI-013**: Quando uma série ainda não possuir histórico persistido, a sincronização DEVE carregar todas as observações publicadas desde a primeira data disponível configurada para a série. A carga inicial DEVE ser particionada em janelas compatíveis com os limites da fonte, executada sob a mesma política idempotente e somente marcar a série como inicializada após persistir dados válidos.
- **FR-EI-014**: Cada série DEVE declarar explicitamente seu modo de acumulação. Para `COMPOUND_PUBLISHED_RATE`, a primeira observação vigente recebe base decimal `100`; cada observação vigente posterior é `acumuladoAnterior × (1 + valorBruto/100)`, na ordem de `referenceDate`.
- **FR-EI-015**: Após toda sincronização bem-sucedida de uma série `COMPOUND_PUBLISHED_RATE`, o sistema DEVE recompor todos os acumulados vigentes desde a primeira observação, usando apenas valores brutos vigentes em memória. O acumulado persistido NUNCA pode ser usado como entrada de outra recomposição.
- **FR-EI-016**: A recomposição DEVE calcular com pelo menos 36 casas decimais internas e persistir cada ponto em `DECIMAL(38,18)`, com arredondamento decimal `HALF_UP` somente na materialização final do ponto. Uma revisão invalida o acumulado da versão anterior e recompõe os pontos vigentes afetados.
- **FR-EI-017**: SELIC, IPCA, IGP-M e INCC DEVEM usar `COMPOUND_PUBLISHED_RATE`. A série de Poupança DEVE usar `NO_GLOBAL_ACCUMULATION`: permanece consultável como valor publicado, mas não oferece acumulado nem projeção de correção global.
- **FR-EI-018**: A porta interna DEVE permitir projetar um valor entre duas datas exatas de uma série acumulável, retornando fator, percentual de atualização e valor projetado. Datas sem publicação, ordem temporal invertida ou série sem acumulação DEVEM falhar de modo explícito e não estimar valores.

### Entidades Principais

- **Série econômica**: definição global de uma referência consultável, sua semântica, unidade, calendário e fonte aprovada.
- **Observação econômica**: valor publicado para uma série em uma data de referência, com identidade de origem e contexto temporal.
- **Acumulado de índice**: projeção materializada somente da sequência de observações vigentes de uma série acumulável; não é a fonte de verdade e é reconstruída a cada atualização.
- **Revisão de observação**: versão posterior oficialmente identificada que substitui a vigência para novas consultas sem apagar o registro anterior.
- **Resultado de atualização**: resultado seguro de uma tentativa de carregar ou revisar observações, com origem, período, contagens e estado.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-EI-001**: 100% das consultas aprovadas retornam série, valor, unidade, fonte e data de referência, ou uma ausência explícita.
- **SC-EI-002**: 100% dos cenários aprovados de repetição de carga produzem zero observações ou revisões duplicadas.
- **SC-EI-003**: 100% dos cenários aprovados de revisão preservam a identidade da versão originalmente consultada.
- **SC-EI-004**: Uma falha simulada de atualização preserva 100% dos indicadores previamente válidos para consulta autorizada.
- **SC-EI-005**: A primeira sincronização solicita a primeira janela a partir da data inicial da série e percorre todo o intervalo até a data de execução, sem exceder o limite de período da fonte; sincronizações posteriores solicitam somente a janela incremental configurada.
- **SC-EI-006**: 100% dos cenários aprovados de repetição, revisão ou corrupção de valor materializado recompõem o mesmo acumulado a partir das observações brutas vigentes, com base inicial 100 e precisão persistida de 18 casas.
