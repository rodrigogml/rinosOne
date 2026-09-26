# Especificação da Feature Catálogo de Instituições Financeiras

**Feature**: `financial-institution-catalog`  
**Criada**: 2026-09-25  
**Status**: Implementada

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Seleção de instituição em cadastros de negócio | Web responsiva | Usuários de tenant autorizados | DEFERRED | Consumirá somente instituições disponíveis para novos vínculos. | A tela de Pessoas e a seleção ainda não fazem parte desta feature. |
| Manutenção do sistema | Operação administrativa | Administradores do sistema | PARTIAL | Permite solicitar uma atualização oficial e consultar seu resultado seguro. | Agenda, painel e política centralizada de manutenções serão definidos na feature futura de agendamentos e manutenções. |

## Cenários de Usuário e Testes

### História 1 - Catálogo oficial reutilizável (Prioridade: P1)

Como usuário de um módulo de negócio, quero consultar um catálogo único de instituições financeiras disponíveis para novos vínculos, para selecionar uma instituição oficial sem duplicação entre tenants.

**Por que esta prioridade**: o catálogo é a dependência comum para contas bancárias de Pessoas e futuros módulos financeiros.

**Teste independente**: carregar uma referência oficial e consultar o catálogo a partir de consumidores distintos, verificando que ambos recebem a mesma instituição global disponível.

**Cenários de aceite**:

1. **Dado** um registro oficial em atividade, **quando** um consumidor autorizado consultar o catálogo, **então** ele poderá encontrá-lo por nome, CNPJ, ISPB ou COMPE quando tais atributos estiverem disponíveis.
2. **Dado** um registro oficial sem atividade, **quando** um consumidor iniciar um novo vínculo, **então** o registro não será oferecido por padrão, mas continuará preservado para referências históricas.

---

### História 2 - Atualização oficial idempotente (Prioridade: P1)

Como administrador do sistema, quero solicitar a atualização do catálogo a partir do Banco Central do Brasil, para manter os dados oficiais atualizados sem edição manual ordinária.

**Por que esta prioridade**: o catálogo só é confiável se refletir uma fonte oficial e puder ser atualizado sem duplicidades.

**Teste independente**: executar duas vezes a atualização sobre a mesma referência oficial e verificar que a quantidade de instituições não aumenta e que a identidade interna é preservada.

**Cenários de aceite**:

1. **Dado** uma instituição já carregada, **quando** a fonte oficial alterar seus atributos, **então** o mesmo registro interno será atualizado.
2. **Dado** uma instituição ainda não presente, **quando** ela vier na fonte oficial, **então** será criada uma única vez.
3. **Dado** uma instituição ausente de uma resposta pontual, **quando** a atualização terminar, **então** sua disponibilidade não será alterada apenas pela ausência.

---

### História 3 - Operação segura pela central de manutenções (Prioridade: P2)

Como responsável pela operação do sistema, quero que a atualização seja chamada de modo controlado pela central de manutenções e permaneça testável, para que a agenda diária não duplique regras de negócio.

**Por que esta prioridade**: a atualização diária foi aprovada, mas sua programação pertence à futura capacidade central de manutenção.

**Teste independente**: invocar explicitamente a atualização em um ambiente de teste e verificar resultado, registros de log e tratamento de falha da fonte.

**Cenários de aceite**:

1. **Dado** uma falha temporária da fonte oficial, **quando** a atualização for chamada, **então** o catálogo previamente válido continuará disponível e o resultado da falha será registrado.
2. **Dado** a entrega desta feature, **quando** o sistema iniciar, **então** nenhuma atualização será iniciada autonomamente.

---

### Casos de Borda

- A fonte oficial pode apresentar CNPJ alfanumérico de 14 posições; registros numéricos legados continuam válidos.
- CNPJ, ISPB e COMPE podem estar ausentes, mudar ou coincidir em contextos históricos; nenhum deles identifica sozinho o registro interno.
- A fonte pode publicar situação não conhecida; o valor oficial deve ser preservado e o registro não deve ser disponibilizado para novos vínculos até existir regra explícita.
- Uma carga repetida, parcial ou sem alterações não pode criar instituições duplicadas.
- Falha externa não pode impedir consultas e vínculos que usem o último catálogo válido.

## Requisitos

### Requisitos Funcionais

- **FR-FI-001**: O sistema DEVE manter um catálogo global de instituições financeiras reutilizável por todos os tenants.
- **FR-FI-002**: O catálogo DEVE ser preenchido e atualizado exclusivamente a partir da fonte oficial BcBase do Banco Central do Brasil.
- **FR-FI-003**: Cada instituição DEVE possuir uma identidade interna imutável e uma identidade de reconciliação baseada no identificador da entidade publicado pelo BCB.
- **FR-FI-004**: CNPJ, ISPB, COMPE e código Sisbacen DEVEM ser tratados como atributos pesquisáveis e não como identidade do catálogo; CNPJ não pode possuir restrição de unicidade.
- **FR-FI-005**: O CNPJ DEVE aceitar o formato alfanumérico de 14 posições e preservar registros numéricos existentes.
- **FR-FI-006**: A atualização DEVE preservar a identidade interna ao alterar atributos oficiais de uma instituição e DEVE ser idempotente para a mesma referência da fonte.
- **FR-FI-007**: O sistema DEVE disponibilizar para novos vínculos somente instituições cuja situação oficial esteja classificada como autorizada em atividade.
- **FR-FI-008**: Instituições não disponíveis para novos vínculos DEVEM permanecer acessíveis para referências históricas; a atualização não pode excluí-las fisicamente.
- **FR-FI-009**: A ausência de instituição em uma resposta não pode inativar nem excluir o registro existente sem uma situação oficial explícita.
- **FR-FI-010**: A atualização DEVE registrar em log a origem, início, término, resultado, quantidades afetadas e falhas, sem criar tabela de rastreabilidade de cargas.
- **FR-FI-011**: Campos oficiais não DEVEM aceitar edição manual ordinária; administradores do sistema podem somente solicitar atualização à fonte oficial.
- **FR-FI-012**: O catálogo não DEVE conter atributos de participação Pix nesta fase.
- **FR-FI-INFRA-SCHED**: A atualização diária DEVE ser programada exclusivamente pelo ponto específico da Central de Manutenções; o catálogo não pode registrar agenda própria ou paralela.
- **FR-FI-INFRA-CONCURRENCY**: A rotina DEVE ser singleton: uma solicitação concorrente, manual ou programada, não pode iniciar outra carga. A solicitação manual recusada DEVE gerar auditoria administrativa com o motivo da recusa.
- **FR-FI-INFRA-IDEMP**: Reexecuções da mesma atualização DEVEM convergir para o mesmo catálogo, sem registros duplicados ou mudança de identidade interna.

### Entidades Principais

- **Instituição Financeira**: referência global de uma entidade supervisionada pelo BCB, com identidade interna, identificador oficial de reconciliação, nomes, códigos pesquisáveis, situação e disponibilidade para novos vínculos.
- **Resultado de Atualização**: resultado efêmero e registrável em log da consulta à fonte oficial, contendo início, fim, origem, quantidades afetadas e falhas seguras; não constitui histórico persistido da feature.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-FI-001**: Duas atualizações consecutivas da mesma referência oficial resultam em zero instituições duplicadas.
- **SC-FI-002**: 100% dos registros retornados como autorizados em atividade ficam disponíveis para novos vínculos ao término de uma atualização bem-sucedida.
- **SC-FI-003**: Uma indisponibilidade da fonte oficial mantém 100% das instituições do último catálogo válido consultáveis para referências históricas.
- **SC-FI-004**: A agenda central diária inicia no máximo uma carga; solicitações concorrentes não iniciam uma segunda carga.
