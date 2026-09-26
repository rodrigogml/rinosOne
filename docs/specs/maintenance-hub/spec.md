# Especificação da Feature Central de Manutenções

**Feature**: `maintenance-hub`  
**Criada**: 2026-09-26  
**Status**: Draft

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Central de Manutenções | Área web administrativa responsiva | Administradores da Plataforma | FULL | Exibe rotinas conhecidas, estados, histórico, relatórios, logs e as ações permitidas por cada rotina. | Não cria interfaces genéricas para substituir apresentações específicas de rotina. |
| API administrativa | API JSON autenticada | Central web e consumidores futuros autorizados | PARTIAL | Lista, detalhe, auditoria e disparo da rotina financeira com autorização por action. | Não há API pública nem integração externa no MVP. |

## Cenários de Usuário e Testes

### História 1 - Visibilidade operacional centralizada (Prioridade: P1)

Como administrador da Plataforma, quero visualizar em uma única área as rotinas de manutenção conhecidas e seus estados, para acompanhar a saúde operacional sem procurar funcionalidades dispersas.

**Por que esta prioridade**: a centralização de visibilidade é a base para administrar as rotinas existentes e futuras com segurança.

**Teste independente**: disponibilizar uma rotina integrada com execuções registradas e confirmar que o administrador autorizado visualiza seu estado, última execução, resultados e histórico permitido.

**Cenários de aceite**:

1. **Dado** uma rotina conhecida pela central, **quando** o administrador abrir a área de manutenções, **então** poderá identificá-la, consultar seu estado e acessar os dados operacionais que ela autoriza exibir.
2. **Dado** uma rotina sem execução anterior, **quando** ela for exibida, **então** a central informará essa condição sem apresentar falha inexistente.
3. **Dado** uma rotina indisponível ou com última falha, **quando** o administrador consultá-la, **então** verá a condição e o histórico técnico permitido, sem expor segredos ou dados sensíveis.

---

### História 2 - Ações administrativas respeitando cada rotina (Prioridade: P1)

Como administrador da Plataforma, quero disparar ou controlar uma rotina a partir da central somente quando ela permitir essa ação, para operar o sistema sem violar regras próprias de execução, agenda ou concorrência.

**Por que esta prioridade**: uma ação forçada ou uma mudança de agenda fora das regras de uma rotina pode causar efeitos operacionais incorretos.

**Teste independente**: integrar uma rotina que permita disparo manual e outra que não permita, verificando que somente a ação autorizada é oferecida e que o resultado é refletido no histórico.

**Cenários de aceite**:

1. **Dado** uma rotina que permite disparo manual, **quando** um administrador autorizado solicitar a ação, **então** a central encaminhará o pedido ao motor da rotina e exibirá o resultado ou o estado de acompanhamento definido por ela.
2. **Dado** uma rotina que não permite disparo manual, **quando** o administrador a consultar, **então** a ação não será disponibilizada.
3. **Dado** uma rotina cuja agenda possa ser controlada, **quando** o administrador autorizado solicitar a alteração prevista, **então** a central aplicará somente as opções definidas para aquela rotina.
4. **Dado** uma rotina disparada por evento ou sem agenda temporal, **quando** ela for exibida, **então** a central mostrará essa característica sem inventar controles de calendário.

---

### História 3 - Auditoria administrativa e histórico técnico (Prioridade: P1)

Como responsável pela Plataforma, quero distinguir ações tomadas por administradores dos registros técnicos produzidos pela rotina, para investigar ocorrências e comprovar quem realizou cada operação.

**Por que esta prioridade**: logs de execução não substituem a auditoria de ações administrativas.

**Teste independente**: solicitar uma ação administrativa autorizada e confirmar que a trilha de auditoria contém autor, data/hora, ação, rotina e parâmetros seguros, separadamente do histórico técnico da execução.

**Cenários de aceite**:

1. **Dado** um administrador que dispara ou altera uma capacidade permitida, **quando** a ação for aceita ou recusada, **então** será registrada em auditoria com o resultado e dados seguros suficientes para rastreio.
2. **Dado** um histórico técnico de execução, **quando** o administrador o consultar, **então** poderá analisá-lo sem que ele seja confundido com a auditoria administrativa.
3. **Dado** que o período de retenção tenha expirado, **quando** a limpeza aplicável ocorrer, **então** os registros técnicos e administrativos serão tratados conforme suas retenções configuradas independentemente.

---

### História 4 - Primeira rotina: instituições financeiras (Prioridade: P2)

Como administrador da Plataforma, quero acompanhar e solicitar a atualização diária do catálogo de instituições financeiras a partir da central, para que uma manutenção global seja governada sem executar por conta própria.

**Por que esta prioridade**: essa rotina é a primeira dependência de cadastro já preparada para integração central.

**Teste independente**: configurar a capacidade diária da rotina de instituições financeiras na central, solicitar uma execução permitida e confirmar que a atualização é chamada uma única vez pelo motor da rotina, com resultado exibido e auditado.

**Cenários de aceite**:

1. **Dado** a rotina de instituições financeiras integrada, **quando** a agenda diária definida para ela vencer, **então** o motor da rotina será disparado segundo suas regras, sem uma segunda agenda concorrente.
2. **Dado** uma sincronização de instituições financeiras em andamento, **quando** ocorrer outra solicitação programada ou manual, **então** a nova execução será recusada; a recusa manual será auditada.
3. **Dado** que o administrador solicite uma atualização permitida, **quando** a rotina aceitar a solicitação, **então** a central exibirá o acompanhamento e registrará a auditoria correspondente.

---

### Casos de Borda

- Duas solicitações próximas para a mesma rotina devem ser tratadas pelas regras de concorrência, singleton ou instâncias da própria rotina; a central não pode inventar uma política conflitante.
- Uma rotina pode estar temporariamente indisponível, não ter histórico ou não aceitar qualquer ação manual.
- Falhas, parâmetros e relatórios podem conter dados sensíveis; a central só pode mostrar os campos permitidos pela rotina e pela autorização aplicável.
- A alteração de uma configuração permitida deve ser auditada mesmo que a rotina a recuse por sua regra própria.
- A remoção por retenção não pode ocorrer fora do período configurado e deve manter a separação entre histórico técnico e auditoria administrativa.

## Requisitos

### Requisitos Funcionais

- **FR-MH-001**: O sistema DEVE disponibilizar uma central única de manutenções para administradores da Plataforma.
- **FR-MH-002**: A central DEVE conhecer cada rotina por integração específica e NÃO DEVE exigir que rotinas adotem um contrato, cadastro ou interface genérica.
- **FR-MH-003**: Uma rotina integrada NÃO DEVE depender da central para executar suas próprias regras, eventos, agenda, concorrência, instâncias, retentativas ou parâmetros.
- **FR-MH-004**: A central DEVE exibir somente as informações, estados, relatórios, logs e ações que cada rotina tenha definido para sua própria integração.
- **FR-MH-005**: A central DEVE permitir disparo manual somente para rotinas que o autorizem e DEVE encaminhar a solicitação ao motor da própria rotina.
- **FR-MH-006**: A central DEVE disponibilizar controles de agenda somente quando a rotina correspondente os definir; rotinas por evento ou sem agenda temporal não devem receber controles de calendário artificiais.
- **FR-MH-007**: A central DEVE persistir uma auditoria administrativa separada do histórico técnico de execução, registrando pelo menos rotina, ação, resultado, autor, instante e parâmetros seguros.
- **FR-MH-008**: A central DEVE manter histórico técnico e logs de rotina por 90 dias como padrão, com período configurável independentemente no arquivo de configuração do sistema.
- **FR-MH-009**: A central DEVE manter auditorias administrativas imutáveis por 90 dias como padrão, com período configurável independentemente no arquivo de configuração do sistema; elas não podem ser editadas ou removidas manualmente.
- **FR-MH-010**: A central DEVE aplicar cada retenção configurada sem expor, excluir ou misturar indevidamente registros de auditoria administrativa e registros técnicos.
- **FR-MH-011**: Cada rotina DEVE definir suas próprias actions autorizáveis no modelo de permissões. A rotina financeira usa as permissions PLATFORM `platform.maintenance.financial-institution.read` para descoberta e consulta, e `platform.maintenance.financial-institution.synchronize` para o disparo manual.
- **FR-MH-012**: A primeira integração da central DEVE governar a atualização diária das instituições financeiras, sem criar execução autônoma adicional fora das regras dessa rotina.
- **FR-MH-014**: A rotina de instituições financeiras DEVE executar como singleton e recusar solicitações simultâneas; a central deve respeitar esse resultado sem enfileirar ou iniciar nova instância.
- **FR-MH-013**: A central DEVE apresentar mensagens de sucesso, indisponibilidade, recusa ou falha que expliquem a condição ao administrador sem revelar segredos ou conteúdo técnico não autorizado.
- **FR-MH-INFRA-SCHED**: A política de agenda é `delegated`: a central apresenta e aciona capacidades de agenda definidas por cada rotina, mas não possui agenda genérica ou política global de disparo.
- **FR-MH-INFRA-IDEMP**: Solicitações administrativas repetidas DEVEM ser encaminhadas de forma rastreável; a prevenção ou aceitação de duplicidade é definida pela rotina destinatária e seu resultado deve ficar registrado em auditoria.

### Entidades Principais

- **Integração de manutenção**: conhecimento específico da central sobre uma rotina, suas informações permitidas, ações, apresentação e pontos de disparo.
- **Histórico técnico de execução**: registros operacionais da rotina disponíveis na central para análise, sujeitos à retenção configurada.
- **Auditoria administrativa**: evidência separada das ações feitas por administradores, incluindo a intenção, o resultado e os parâmetros seguros.
- **Capacidade de rotina**: ação ou informação que uma integração pode oferecer, como consulta, agenda, disparo manual ou relatório.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-MH-001**: 100% das rotinas integradas ficam identificáveis e consultáveis por administradores autorizados na central.
- **SC-MH-002**: 100% das ações administrativas aceitas ou recusadas produzem uma evidência de auditoria separada do histórico técnico.
- **SC-MH-003**: 100% das ações não permitidas por uma rotina deixam de ser oferecidas pela central.
- **SC-MH-004**: Registros técnicos e administrativos com mais de 90 dias são tratados segundo suas retenções configuradas, sem reter dados além da política definida.
- **SC-MH-005**: A integração de instituições financeiras mantém no máximo uma execução em curso; solicitações concorrentes são recusadas, e as manuais ficam rastreadas na auditoria.
