# Especificação da Feature: Restrições de Autorização

**Feature**: `authorization-restrictions`  
**Criada em**: 2026-09-26  
**Status**: Ready

## Direção de Produto

O rinosOne deve permitir limitar explicitamente uma capacidade que uma pessoa poderia receber por role direta ou por grupo. Uma restriction não concede acesso: ela somente impede uma permission enquanto estiver ativa e for aplicável.

Quando uma restriction aplicável e uma concessão coexistirem, a restriction sempre prevalece. A regra de precedência é:

```text
DENY explícito
    >
ALLOW por role ou grupo
    >
DENY por ausência de grant
```

Esta feature trata restrictions gerais por permission em PLATFORM, PERSONAL e TENANT, atribuídas diretamente a pessoas ou a grupos. Restrictions por recurso específico e condições contextuais permanecem para as features que introduzirem esses conceitos.

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| API protegida da plataforma | Other | Pessoa autenticada e consumidor futuro autorizado | FULL | Aplica restriction antes de permitir uma operação protegida. | Contratos públicos específicos e consumidores externos. |
| Workspace autenticado | Web responsiva | Pessoa autenticada | PARTIAL | Recebe capabilities reavaliadas que deixam de indicar ações bloqueadas. | Editor e lista de restrictions. |
| Administração de segurança | Web responsiva | Administrador autorizado | DEFERRED | Nenhuma nesta fase. | Criar, consultar, remover e auditar restrictions por interface. |

## Cenários de Usuário e Testes

### User Story 1 - Bloquear uma permission já concedida (Prioridade: P1)

Como responsável pela segurança, quero restringir explicitamente uma permission para impedir uma ação que a pessoa receberia por role ou grupo.

**Por que esta prioridade**: negar pontualmente um acesso excessivo é o valor central da feature e deve prevalecer sobre qualquer grant normal.

**Teste independente**: uma pessoa recebe uma permission por role e, após receber restriction ativa para a mesma permission, tem a operação negada sem sair da sessão.

**Cenários de aceitação**:

1. **Dado** que recebo uma permission por role direta, **quando** uma restriction ativa se aplica a mim e àquela permission, **então** a operação é negada.
2. **Dado** que recebo uma permission por grupo, **quando** uma restriction ativa se aplica a mim e àquela permission, **então** a operação é negada.
3. **Dado** que não recebo a permission por grant algum, **quando** uma restriction existe para ela, **então** a operação continua negada sem produzir concessão implícita.

---

### User Story 2 - Aplicar restrição recebida por grupo (Prioridade: P1)

Como responsável pela segurança, quero restringir uma permission para um grupo inteiro para que todos os seus membros ativos recebam a mesma limitação sem configurações individuais repetidas.

**Por que esta prioridade**: grupos são a forma escalável de administrar políticas comuns; a restriction precisa acompanhar sua associação ativa.

**Teste independente**: um grupo com membros ativos recebe uma restriction e todos os membros são bloqueados; a pessoa removida do grupo deixa de sofrer aquela restriction, salvo outra aplicável.

**Cenários de aceitação**:

1. **Dado** que participo de um grupo com restriction ativa, **quando** tento executar a permission restringida, **então** a operação é negada.
2. **Dado** que participo de grupos com grants e de outro grupo com restriction para a mesma permission, **quando** tento executar a operação, **então** a restriction prevalece.
3. **Dado** que deixo de participar do grupo restringido e não possuo outra restriction aplicável, **quando** executo a permission concedida, **então** a decisão volta a considerar somente os grants ativos.

---

### User Story 3 - Respeitar esfera e vigência da restriction (Prioridade: P1)

Como pessoa responsável pela segurança, quero que uma restriction somente afete a esfera e o período para os quais foi criada para evitar bloqueios indevidos.

**Por que esta prioridade**: uma negação explícita tem alto impacto e precisa ser limitada de forma previsível.

**Teste independente**: uma restriction TENANT ativa no Tenant A bloqueia somente naquele tenant; antes da vigência e depois de sua expiração, ela não bloqueia.

**Cenários de aceitação**:

1. **Dado** que há restriction para uma permission TENANT no Tenant A, **quando** tento a mesma permission no Tenant B, **então** a restriction não produz efeito no Tenant B.
2. **Dado** que uma restriction possui início futuro, **quando** tento a operação antes do início, **então** ela não bloqueia a decisão.
3. **Dado** que uma restriction possui encerramento, **quando** tento a operação depois do encerramento, **então** ela não bloqueia a decisão.
4. **Dado** que uma restriction está dentro de sua vigência, **quando** tento a operação, **então** ela bloqueia independentemente de eu possuir role administrativa protegida.

---

### User Story 4 - Revogar e auditar uma restriction (Prioridade: P2)

Como responsável pela segurança, quero remover uma restriction e manter uma trilha da alteração para recuperar o acesso legítimo e compreender o que ocorreu.

**Por que esta prioridade**: restrictions precisam ser reversíveis quando deixam de ser necessárias, sem apagar a evidência administrativa.

**Teste independente**: uma restriction é removida por operação autorizada; a próxima decisão volta a permitir o grant aplicável e a mudança fica registrada em auditoria.

**Cenários de aceitação**:

1. **Dado** que uma restriction ativa bloqueia uma permission concedida, **quando** ela é removida, **então** a próxima operação volta a permitir a permission se houver grant válido.
2. **Dado** que uma restriction é criada ou removida, **quando** a alteração é concluída, **então** a auditoria registra ator, momento, esfera, tenant quando aplicável, alvo, razão e mudança segura.

### Casos de Borda

- Mais de uma restriction aplicável continua resultando em negação, sem depender da ordem de criação.
- Restriction inativa, ainda não vigente ou expirada não bloqueia a decisão.
- Uma restriction TENANT não pode apontar para permission, pessoa ou grupo de outro tenant.
- Uma restriction nunca transforma uma ausência de grant em acesso permitido.
- Uma restriction geral não recebe identificador de recurso nesta feature; restrições por recurso serão adicionadas junto da autorização por recurso.
- A remoção, a ativação, a desativação ou a alteração de vigência deve afetar a próxima operação protegida, sem exigir novo login.

## Requisitos

### Requisitos Funcionais

- **FR-AR-001**: O sistema DEVE permitir registrar uma restriction explícita de negação para uma permission e uma pessoa ou grupo compatível.
- **FR-AR-002**: Toda restriction DEVE pertencer a exatamente uma esfera `PLATFORM`, `PERSONAL` ou `TENANT`, compatível com a permission restringida.
- **FR-AR-003**: Uma restriction TENANT DEVE exigir tenant explícito e só pode referenciar pessoa, grupo e permission compatíveis com esse tenant.
- **FR-AR-004**: Uma restriction PLATFORM ou PERSONAL NÃO DEVE exigir, inferir ou usar tenant.
- **FR-AR-005**: O sistema DEVE negar a operação quando existir ao menos uma restriction ativa e vigente aplicável à pessoa e à permission avaliada, antes de considerar grants por role ou grupo.
- **FR-AR-006**: O sistema DEVE considerar restrictions diretas da pessoa e restrictions recebidas por seus grupos ativos.
- **FR-AR-007**: O sistema DEVE aplicar a restriction a todas as pessoas, inclusive quem possui `tenant.administrator`, sem criar bypass implícito de role.
- **FR-AR-008**: Uma restriction DEVE poder ter início e encerramento de vigência; ausência de início significa vigência imediata e ausência de encerramento significa vigência sem prazo definido.
- **FR-AR-009**: O sistema DEVE considerar a restriction vigente a partir de seu início e até antes de seu encerramento, quando esses limites existirem.
- **FR-AR-010**: O sistema DEVE permitir desativar ou remover uma restriction sem apagar o evento de auditoria correspondente.
- **FR-AR-011**: O sistema DEVE registrar evento de auditoria para criação, alteração de vigência, ativação, desativação e remoção de restriction, sem segredo ou conteúdo confidencial desnecessário.
- **FR-AR-012**: O sistema DEVE reavaliar restrictions em toda nova operação protegida, sem depender de novo login ou de capabilities previamente armazenadas.
- **FR-AR-013**: O sistema NÃO DEVE aceitar identificador de recurso, condição contextual, relationship ou regra de delegação em uma restriction desta feature.
- **FR-AR-014**: A feature NÃO DEVE depender de agendamento, credencial externa, rotação de chaves, sincronização externa ou persistência de decisão na sessão.

### Entidades Principais

- **Restriction**: negação explícita e auditável de uma permission para uma pessoa ou grupo, limitada a uma esfera e, quando TENANT, a uma organização.
- **Vigência da restriction**: intervalo no qual uma restriction é aplicável; pode iniciar imediatamente e/ou permanecer sem encerramento definido.
- **Sujeito restringido**: pessoa ou grupo que recebe a limitation; seus membros ativos herdam restriction de grupo.
- **Decisão com restriction**: resultado negado quando uma restriction aplicável é encontrada antes dos grants normais.
- **Evento de auditoria de restriction**: registro seguro de criação, mudança de estado, vigência ou remoção de restriction.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-AR-001**: 100% dos cenários automatizados com grant aplicável e restriction ativa resultam em negação.
- **SC-AR-002**: 100% dos cenários automatizados de restriction TENANT comprovam que ela não produz efeito em outro tenant.
- **SC-AR-003**: 100% dos cenários automatizados de início, expiração, ativação, desativação ou remoção comprovam que a próxima operação reflete o estado atual da restriction.
- **SC-AR-004**: 100% das alterações de restrictions abrangidas pela feature produzem evento de auditoria seguro e não editável.
