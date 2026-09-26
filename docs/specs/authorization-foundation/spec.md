# Especificação da Feature: Fundação de Autorização

**Feature**: `authorization-foundation`  
**Criada em**: 2026-09-25  
**Status**: Ready

## Direção de Produto

O rinosOne deve possuir uma única fundação de autorização que responda, para cada operação protegida, quem pode executar qual ação e em qual esfera ela ocorre. Estar autenticado, conhecer um identificador ou participar de um tenant nunca basta para conceder acesso.

Esta fundação estabelece três esferas-base mutuamente distintas:

- **PLATFORM**: ações sobre capacidades e dados centralizados do rinosOne, independentes de tenant, como catálogos compartilhados, administração da plataforma e operações de manutenção.
- **PERSONAL**: ações sobre capacidades e objetos próprios de uma pessoa usuária, independentes de tenant, como um espaço pessoal de arquivos.
- **TENANT**: ações sobre capacidades e dados de uma organização, sempre dentro de um tenant explícito, válido e ativo.

Um recurso específico não é uma quarta esfera-base. Ele será uma restrição complementar de uma ação em uma dessas esferas e será tratado na feature `resource-authorization`. Assim, uma pasta compartilhada continuará sendo um objeto pessoal e uma conta financeira continuará sendo um objeto de tenant.

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| API protegida da plataforma | Other | Usuário autenticado e consumidor futuro autorizado | FULL | Determina se uma ação de plataforma, pessoal ou de tenant pode ser executada. | Contratos públicos específicos e aplicações externas. |
| Workspace autenticado | Web responsiva | Usuário autenticado | PARTIAL | Recebe somente as capacidades necessárias para apresentar destinos pessoais e do tenant selecionado. | Administração de autorização, editor de roles e explicação de acessos. |
| Administração de segurança | Web responsiva | Administrador autorizado | DEFERRED | Nenhuma nesta fase. | Gestão de membros, grupos, roles, assignments, restrictions e auditoria. |

## Clarificações

### Sessão 2026-09-25

- Q: Qual padrão de identificador deve ser adotado pelas entidades persistidas de autorização? -> A: `BIGINT UNSIGNED`, conforme a Constituição do rinosOne; menções anteriores a ULID são obsoletas.
- Q: Como as ações são separadas entre a plataforma, o espaço pessoal e as organizações? -> A: Toda permission pertence a exatamente uma esfera-base: `PLATFORM`, `PERSONAL` ou `TENANT`. A autorização por recurso será complementar e posterior.
- Q: Quais permissions compõem `tenant.administrator`? -> A: Todas as permissions da esfera TENANT, inclusive as registradas futuramente.
- Q: Qual é a retenção da auditoria de autorização? -> A: 90 dias por padrão, configurável por ambiente.
- Q: Como é atribuído o primeiro administrador PLATFORM? -> A: Diretamente no banco pela equipe de infraestrutura; registros de usuário nunca recebem privilégio de plataforma automaticamente.

## Cenários de Usuário e Testes

### User Story 1 - Proteger uma ação pela esfera correta (Prioridade: P1)

Como pessoa usuária autenticada, quero que cada ação protegida seja avaliada na esfera a que pertence para que acessos de plataforma, pessoais e organizacionais não se confundam.

**Por que esta prioridade**: a separação de esferas é a base para que os módulos futuros possam proteger seus dados sem criar regras incompatíveis entre si.

**Teste independente**: uma pessoa com concessões distintas para plataforma, espaço pessoal e dois tenants executa ações de cada tipo e somente recebe o resultado permitido pela concessão aplicável.

**Cenários de aceitação**:

1. **Dado** que possuo autorização de plataforma para administrar um catálogo central, **quando** executo essa ação, **então** a decisão não exige nem utiliza um tenant.
2. **Dado** que possuo autorização para usar uma capacidade pessoal, **quando** a utilizo sem tenant selecionado, **então** ela continua disponível e não passa a conceder acesso a objetos de outra pessoa.
3. **Dado** que tenho uma autorização no Tenant A e nenhuma no Tenant B, **quando** tento executar a mesma ação no Tenant B, **então** o acesso é negado.
4. **Dado** que uma ação pertence à esfera TENANT, **quando** ela é solicitada sem um tenant explícito, inativo ou sem associação ativa da pessoa, **então** o acesso é negado.

---

### User Story 2 - Receber acesso por papéis e grupos (Prioridade: P1)

Como administrador responsável pela configuração inicial, quero conceder permissões de negócio por papéis e grupos para evitar regras isoladas e inconsistentes por pessoa usuária.

**Por que esta prioridade**: roles e grupos permitem que a autorização cresça sem repetir a mesma decisão para cada pessoa.

**Teste independente**: uma pessoa recebe um papel diretamente e outra o recebe por um grupo; ambas obtêm somente as ações previstas naquele papel e na esfera correspondente.

**Cenários de aceitação**:

1. **Dado** que um papel possui uma permission aplicável à sua esfera, **quando** o papel é atribuído diretamente a uma pessoa elegível, **então** a pessoa pode executar a ação concedida.
2. **Dado** que um grupo recebe um papel e uma pessoa integra esse grupo, **quando** ela executa uma ação concedida pelo papel, **então** recebe a mesma autorização enquanto a associação estiver ativa.
3. **Dado** que uma pessoa pertence a mais de um grupo, **quando** os papéis desses grupos concedem ações diferentes, **então** ela recebe a união das concessões válidas.
4. **Dado** que um papel, grupo ou atribuição pertence ao Tenant A, **quando** tentam associá-lo a uma pessoa, papel ou grupo do Tenant B, **então** a alteração é recusada.
5. **Dado** que um grupo participa de uma hierarquia válida, **quando** uma pessoa integra um grupo descendente de outro que recebeu um papel, **então** ela recebe as permissões herdadas pela hierarquia.
6. **Dado** que uma alteração de grupos criaria um ciclo, **quando** ela é solicitada, **então** o sistema a recusa sem alterar a hierarquia existente.

---

### User Story 3 - Revogar acesso sem encerrar a sessão (Prioridade: P1)

Como responsável pela segurança, quero que uma remoção de papel, grupo ou associação de tenant afete a próxima ação protegida para limitar rapidamente acessos que deixaram de ser necessários.

**Por que esta prioridade**: uma sessão autenticada não pode manter autorização que já foi removida.

**Teste independente**: uma pessoa autenticada perde uma atribuição ou associação e tem a próxima operação protegida negada sem precisar sair ou entrar novamente.

**Cenários de aceitação**:

1. **Dado** que tenho acesso por um papel direto, **quando** esse papel é removido, **então** minha próxima tentativa da ação correspondente é negada.
2. **Dado** que tenho acesso por um grupo, **quando** sou removido do grupo ou o grupo perde o papel, **então** minha próxima tentativa da ação correspondente é negada.
3. **Dado** que tenho acesso em um tenant por uma associação ativa, **quando** essa associação se torna inativa, **então** minha próxima ação nesse tenant é negada, ainda que minha sessão permaneça autenticada.

---

### User Story 4 - Preservar a administração do tenant (Prioridade: P1)

Como administrador de uma organização, quero que ela preserve ao menos uma pessoa administradora elegível para que não fique sem responsável administrativo.

**Por que esta prioridade**: a perda do último responsável impede a governança segura da organização.

**Teste independente**: uma organização com um único administrador rejeita sua remoção ou desativação; após a atribuição de outro administrador elegível, a transferência pode ser concluída.

**Cenários de aceitação**:

1. **Dado** que sou o único administrador ativo de um tenant, **quando** tento remover ou desativar minha atribuição administrativa, **então** a alteração é recusada.
2. **Dado** que outro membro elegível se tornou administrador ativo, **quando** transfiro ou removo minha atribuição administrativa, **então** a organização continua com ao menos um administrador ativo.
3. **Dado** que uma pessoa não possui associação ativa com um tenant, **quando** tentam torná-la responsável por aquele tenant, **então** a alteração é recusada.

### Casos de Borda

- A ausência de uma permission aplicável deve negar a ação, inclusive para pessoa autenticada, membro de tenant ou titular de um recurso pessoal.
- Conhecer o identificador de um tenant, objeto pessoal ou dado centralizado não concede autorização.
- Uma permission, role, grupo ou assignment inativo não pode conceder acesso.
- Uma hierarquia de grupos não pode conter ciclos, mesmo de forma indireta.
- A troca de tenant em uma aba não altera autorizações, contexto ou dados expostos em outra aba.
- A remoção de uma associação, role ou grupo deve ser atômica com a atualização que impede sua utilização subsequente.
- A role administrativa protegida não é um bypass de autorização: suas ações decorrem das permissions que ela recebe.

## Requisitos

### Requisitos Funcionais

- **FR-AF-001**: O sistema DEVE manter um catálogo único de permissions que represente ações de negócio estáveis, com nome, descrição e esfera-base.
- **FR-AF-002**: Cada permission DEVE pertencer a exatamente uma das esferas `PLATFORM`, `PERSONAL` ou `TENANT`.
- **FR-AF-003**: O sistema DEVE negar uma ação protegida quando não existir uma concessão ativa e aplicável; autenticação, existência da pessoa, conhecimento de identificador, contexto visual ou associação isolada não constituem concessão.
- **FR-AF-004**: Uma decisão na esfera PLATFORM NÃO DEVE exigir, inferir ou usar um tenant.
- **FR-AF-005**: Uma decisão na esfera PERSONAL NÃO DEVE exigir, inferir ou usar um tenant, nem conceder acesso a objeto pessoal pertencente a outra pessoa sem uma autorização específica aplicável.
- **FR-AF-006**: Uma decisão na esfera TENANT DEVE receber um tenant explícito, confirmar sua atividade e confirmar a associação ativa da pessoa antes de avaliar concessões.
- **FR-AF-007**: O sistema DEVE manter roles como conjuntos reutilizáveis de permissions compatíveis com uma única esfera-base.
- **FR-AF-008**: O sistema DEVE distinguir roles gerenciadas pelo sistema, roles fornecidas pela plataforma e roles próprias de tenant, sem permitir que uma role de tenant incorpore permission de plataforma.
- **FR-AF-009**: O sistema DEVE permitir atribuir roles diretamente a pessoas elegíveis e a grupos compatíveis com a mesma esfera-base.
- **FR-AF-010**: O sistema DEVE permitir que grupos reúnam pessoas, grupos compatíveis e roles, sem misturar membros, roles ou atribuições entre tenants distintos e sem permitir ciclos de grupo.
- **FR-AF-011**: O sistema DEVE calcular as permissões efetivas pela união das roles diretas e das roles recebidas por grupos ativos, inclusive por uma hierarquia válida de grupos, observada a esfera da decisão.
- **FR-AF-012**: O sistema DEVE tratar membership de tenant e autorização como conceitos separados: a membership habilita a elegibilidade contextual, mas não concede automaticamente permissions de negócio.
- **FR-AF-013**: O sistema DEVE preservar a regra de ao menos uma pessoa com membership ativa e atribuição direta da role administrativa protegida por tenant, rejeitando alteração que viole essa regra.
- **FR-AF-014**: O sistema DEVE reavaliar a autorização em toda nova operação protegida, de modo que remoções ou desativações relevantes tenham efeito sem exigir novo login.
- **FR-AF-015**: O sistema DEVE registrar uma trilha de auditoria imutável para criação, alteração, ativação, desativação e remoção de roles, grupos, memberships e assignments, com ator, data, esfera, tenant quando aplicável, alvo e resumo seguro da alteração.
- **FR-AF-016**: O sistema DEVE fornecer ao workspace somente informações de capacidade necessárias à experiência; essas informações não substituem a nova decisão no backend para cada operação protegida.
- **FR-AF-017**: O sistema DEVE manter autorização por recurso específico, compartilhamento de objetos pessoais, restrictions explícitas, implicação de permissions, regras contextuais, delegação administrativa e interfaces de gestão fora do escopo desta feature.
- **FR-AF-018**: A feature NÃO DEVE depender de credenciais externas, rotação de chaves, sincronização externa ou persistência de decisão na sessão. Alterações devem refletir-se pela nova avaliação de cada operação protegida.
- **FR-AF-019**: O sistema DEVE reter eventos de auditoria de autorização por 90 dias por padrão, com prazo configurável por ambiente, e excluir eventos vencidos por rotina diária.
- **FR-AF-020**: A role protegida `tenant.administrator` DEVE conter todas as permissions da esfera TENANT, inclusive as adicionadas posteriormente ao catálogo.
- **FR-AF-021**: O sistema NÃO DEVE atribuir roles PLATFORM automaticamente durante cadastro, autenticação ou criação de tenant; a atribuição inicial de administrador PLATFORM é uma operação controlada da infraestrutura diretamente no banco.

### Entidades Principais

- **Permission**: ação de negócio estável protegida pelo sistema e classificada em uma esfera-base.
- **Esfera de autorização**: delimitação funcional em que uma ação ocorre: plataforma, capacidade pessoal ou organização.
- **Role**: conjunto reutilizável de permissions compatíveis com uma mesma esfera-base.
- **Grupo de autorização**: conjunto de pessoas e grupos compatíveis que pode receber roles na esfera e contexto a que pertence, sem ciclos de associação.
- **Atribuição de role**: concessão ativa de um role a uma pessoa ou grupo, com escopo compatível.
- **TenantMembership**: vínculo entre uma pessoa e uma organização que determina elegibilidade para ações de tenant, sem substituir permissions.
- **Role administrativa protegida**: role de tenant atribuída diretamente a uma pessoa com membership ativa e que não pode ser removida do último administrador ativo.
- **Decisão de autorização**: resultado de permitir ou negar uma ação após considerar pessoa, esfera, tenant quando aplicável e concessões ativas.
- **Evento de auditoria de autorização**: registro seguro e não editável de mudança administrativa relevante para a segurança.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-AF-001**: 100% dos cenários automatizados de decisão sem concessão aplicável resultam em negação.
- **SC-AF-002**: 100% dos cenários automatizados comprovam que uma concessão de tenant não habilita a mesma ação em outro tenant.
- **SC-AF-003**: 100% dos cenários automatizados de remoção de role, grupo ou membership negam a próxima operação protegida sem novo login.
- **SC-AF-004**: 100% dos cenários automatizados de alteração administrativa rejeitam a remoção ou desativação do último administrador ativo.
- **SC-AF-005**: 100% das alterações administrativas abrangidas pela feature produzem evento de auditoria com ator, momento, alvo e esfera, sem segredo ou credencial.
