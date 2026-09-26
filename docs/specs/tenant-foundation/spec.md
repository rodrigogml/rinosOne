# Especificação da Feature: Fundação de Tenants

**Feature**: `tenant-foundation`
**Criada em**: 2026-09-24
**Status**: Planejada

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Workspace autenticado | Web responsiva | Usuário autenticado e validado | FULL | Criar, identificar, selecionar, trocar e encerrar o contexto de um tenant sem perder as funcionalidades pessoais. | Convites, gestão de membros, papéis avançados, módulos de negócio e edição completa do tenant. |
| Consumidores futuros autorizados | Other | Não definido | PARTIAL | As regras de contexto e isolamento são aplicáveis a consumidores futuros. | Experiência, autorização e contratos específicos de integrações ou aplicações nativas. |

## Direção de Produto

O workspace representa uma única experiência para a pessoa autenticada. Suas funcionalidades pessoais permanecem sempre
disponíveis. A seleção de um tenant acrescenta, apenas naquela área de trabalho, os módulos e definições pertencentes à
organização selecionada; ela nunca substitui a identidade pessoal nem remove suas opções.

Um tenant representa uma organização com identidade estável, ciclo de vida próprio e dados isolados. A plataforma deve
permitir que a mesma pessoa trabalhe com organizações distintas em abas distintas, sem misturar dados, permissões,
ações ou estados transitórios.

## Clarifications

### Session 2026-09-24

- Q: A lista de organizações deve lembrar a última organização usada entre sessões e abas? -> A: Sim, por usuário e vínculo, após seleção contextual validada.

## Cenários de Usuário e Testes

### User Story 1 - Criar uma organização de trabalho (Prioridade: P1)

Como usuário validado, quero criar uma organização com um nome para poder começar a utilizá-la quando estiver pronta.

**Por que esta prioridade**: sem criação segura de tenant, não existe base para os futuros módulos organizacionais.

**Teste independente**: uma pessoa autenticada cria uma organização, acompanha sua disponibilidade e, quando ela fica
ativa, encontra-se com membership ativa e a atribuição administrativa inicial.

**Cenários de aceitação**:

1. **Dado** que sou usuário autenticado e validado, **quando** informo um nome válido para uma nova organização,
   **então** o sistema aceita uma única solicitação de criação, cria minha membership ativa e me atribui a role administrativa protegida.
2. **Dado** que criei uma organização, **quando** sua preparação ainda não terminou, **então** vejo seu estado sem poder
   utilizá-la como contexto de trabalho.
3. **Dado** que a preparação terminou com sucesso, **quando** a organização é habilitada, **então** ela se torna
   selecionável para mim.
4. **Dado** que a preparação falha, **quando** consulto a organização, **então** ela permanece indisponível para uso e
   recebo uma indicação segura de que não está pronta.

---

### User Story 2 - Acrescentar um tenant ao workspace (Prioridade: P1)

Como usuário autenticado, quero selecionar conscientemente uma organização no meu workspace para acessar seus futuros
módulos sem perder os recursos pessoais que já utilizo.

**Por que esta prioridade**: explicitar o contexto reduz o risco de operar na organização errada e preserva uma
experiência única para a pessoa usuária.

**Teste independente**: uma pessoa com uma organização ativa abre o seletor, escolhe-a e observa sua identificação
persistente no workspace, seus recursos pessoais preservados e apenas os recursos contextuais adicionais disponíveis.

**Cenários de aceitação**:

1. **Dado** que não tenho tenant selecionado, **quando** utilizo o workspace, **então** encontro somente funcionalidades
   pessoais e globais que não dependem de tenant.
2. **Dado** que possuo uma organização ativa, **quando** a seleciono explicitamente, **então** o workspace a identifica
   de forma persistente e acrescenta somente as funcionalidades a que tenho acesso nesse contexto.
3. **Dado** que seleciono uma organização, **quando** acesso uma funcionalidade pessoal, **então** ela permanece
   disponível e não passa a depender da organização selecionada.
4. **Dado** que não tenho associação ativa com uma organização, **quando** tento selecioná-la por qualquer caminho,
   **então** o contexto é negado sem expor dados internos da organização.
5. **Dado** que tenho apenas uma organização disponível, **quando** entro no workspace, **então** ela não é selecionada
   automaticamente.
6. **Dado** que seleciono uma organização com sucesso, **quando** volto a abrir o seletor nesta ou em outra aba,
   **então** ela aparece primeiro na lista pessoal sem restaurar nem modificar o contexto das abas.

---

### User Story 3 - Trabalhar com organizações diferentes em paralelo (Prioridade: P1)

Como usuário que participa de mais de uma organização, quero trabalhar com um tenant diferente em cada aba para alternar
ou comparar atividades sem alterar o contexto das demais abas.

**Por que esta prioridade**: o isolamento por área de trabalho impede a exposição e a gravação de dados na organização
errada.

**Teste independente**: uma pessoa abre duas abas, seleciona uma organização diferente em cada uma e confirma que a
troca, dados e recursos de uma não modificam nem aparecem na outra.

**Cenários de aceitação**:

1. **Dado** que tenho duas abas autenticadas, **quando** seleciono a organização A em uma e a organização B na outra,
   **então** cada aba preserva seu próprio contexto.
2. **Dado** que altero a organização selecionada em uma aba, **quando** a troca é concluída, **então** somente o estado
   contextual daquela aba é substituído e as demais permanecem inalteradas.
3. **Dado** que uma ação contextual foi iniciada na organização A, **quando** mudo outra aba para a organização B,
   **então** a ação original continua vinculada somente à organização A.
4. **Dado** que uma aba é restaurada depois de o navegador ser fechado ou reiniciado, **quando** ela volta a ficar
   disponível, **então** ela inicia sem tenant selecionado e exige uma nova escolha explícita.

---

### User Story 4 - Trocar, encerrar ou desabilitar um contexto (Prioridade: P2)

Como usuário, quero trocar ou encerrar o tenant atual de forma clara e, quando possuir a capability necessária, quero poder
desabilitar a organização sem apagar seus dados.

**Por que esta prioridade**: a mudança precisa deixar o workspace coerente e impedir que uma organização indisponível
continue sendo usada por engano.

**Teste independente**: com uma organização selecionada, a pessoa volta ao estado sem tenant ou troca para outra; uma
organização desabilitada deixa de poder ser selecionada, enquanto suas informações permanecem preservadas.

**Cenários de aceitação**:

1. **Dado** que uso a organização A, **quando** seleciono a organização B com sucesso, **então** os dados e recursos
   contextuais da A deixam de estar disponíveis naquela aba antes de os da B serem exibidos.
2. **Dado** que a validação da organização B falha, **quando** a troca é recusada, **então** o contexto A continua
   íntegro e nenhum estado parcial de B permanece disponível.
3. **Dado** que uso uma organização, **quando** encerro seu contexto, **então** retorno ao workspace sem tenant e
   mantenho minhas funcionalidades pessoais.
4. **Dado** que uma organização ativa é desabilitada, **quando** tento selecioná-la ou executar nova ação contextual,
   **então** o uso é bloqueado sem apagar a organização ou seus dados.

### Casos de Borda

- Duas solicitações equivalentes de criação não podem produzir duas organizações ou dois contextos para a mesma
  intenção confirmada.
- Nome de exibição pode ser alterado futuramente sem alterar a identidade estável da organização.
- Uma organização em preparação, com falha ou desabilitada não pode disponibilizar módulos contextuais.
- A perda de autenticação, da associação ou da habilitação da organização encerra o uso contextual na próxima ação,
  sem afetar funcionalidades pessoais que ainda estejam autorizadas.
- Dados, resultados temporários, arquivos, recursos com identificadores semelhantes e mensagens de uma organização
  não podem ser reutilizados ou expostos em outra.
- Uma troca solicitada enquanto existirem alterações locais não confirmadas deve advertir a pessoa antes de descartar
  esse trabalho contextual.

## Requisitos

### Requisitos Funcionais

- **FR-TEN-001**: O sistema DEVE permitir que qualquer usuário autenticado e validado inicie a criação de um tenant
  informando seu nome de exibição obrigatório.
- **FR-TEN-002**: O sistema DEVE atribuir a cada tenant uma identidade estável e independente de seu nome de exibição.
- **FR-TEN-003**: Ao criar um tenant, o sistema DEVE criar uma membership ativa para o criador e atribuir-lhe diretamente a role administrativa protegida.
- **FR-TEN-004**: O sistema DEVE manter o tenant indisponível para operações contextuais até que sua preparação seja
  concluída com sucesso.
- **FR-TEN-005**: O sistema DEVE informar de maneira clara e segura se um tenant está em preparação, ativo,
  desabilitado ou indisponível por falha.
- **FR-TEN-006**: O sistema DEVE manter as informações pessoais, a autenticação e as funcionalidades não contextuais
  acessíveis independentemente de haver um tenant selecionado.
- **FR-TEN-007**: Sem tenant selecionado, o sistema NÃO DEVE exibir nem permitir funcionalidades que dependam de tenant.
- **FR-TEN-008**: O sistema DEVE exigir seleção explícita de um tenant ativo antes de disponibilizar suas
  funcionalidades contextuais, mesmo quando o usuário possuir somente uma opção disponível.
- **FR-TEN-009**: O sistema DEVE validar a identidade autenticada, a associação vigente e a disponibilidade do tenant
  antes de estabelecer ou usar um contexto contextual.
- **FR-TEN-010**: O sistema NÃO DEVE inferir um tenant pela identidade do usuário, pela última seleção, pela existência
  de uma única associação ou por uma área aberta anteriormente.
- **FR-TEN-011**: Cada aba ou área de trabalho DEVE manter seu contexto de tenant de forma independente, embora a
  autenticação do usuário possa ser compartilhada.
- **FR-TEN-012**: A troca, o encerramento ou a invalidação de um tenant DEVE limpar somente o estado contextual da área
  de trabalho afetada e preservar os recursos pessoais autorizados.
- **FR-TEN-013**: O sistema DEVE impedir que dados, permissões, ações, resultados temporários ou recursos de um tenant
  sejam apresentados, reutilizados ou aplicados em outro tenant.
- **FR-TEN-014**: O sistema DEVE impedir uma nova ação contextual quando o usuário, sua associação ou o tenant deixar
  de satisfazer as condições de uso.
- **FR-TEN-015**: A pessoa que possui a capability administrativa aplicável DEVE poder desabilitar o tenant sem apagar sua identidade, membership ou
  dados, e um tenant desabilitado NÃO DEVE aceitar novos contextos operacionais.
- **FR-TEN-016**: O sistema DEVE iniciar a preparação de uma criação aceita sem exigir que o usuário execute uma etapa
  manual posterior.
- **FR-TEN-017**: O sistema DEVE tratar reenvios da mesma solicitação de criação de forma idempotente, sem criar
  tenants, identidades ou preparações duplicadas.
- **FR-TEN-018**: O sistema DEVE registrar eventos de criação, mudança de disponibilidade, seleção, troca,
  encerramento e tentativa negada de contexto sem incluir dados do tenant além do necessário para segurança e suporte.
- **FR-TEN-019**: A feature NÃO DEVE introduzir, nesta fase, convites, gestão de membros, roles além da administrativa protegida
  inicial, permissões detalhadas, módulos de negócio ou exclusão definitiva de tenant.
- **FR-TEN-020**: O sistema DEVE registrar a recência de uma seleção contextual somente após validação bem-sucedida e
  usá-la para ordenar a lista pessoal de organizações, sem armazenar ou restaurar o tenant ativo da aba.

### Entidades Principais

- **Tenant**: organização identificada de forma estável, com nome de exibição, estado de disponibilidade e ciclo de
  vida próprio.
- **Associação ao tenant**: vínculo entre uma pessoa usuária e uma organização, que determina se ela pode estabelecer
  seu contexto. Na criação, a membership ativa e a atribuição administrativa protegida são criadas para a mesma pessoa.
- **Contexto de tenant**: seleção temporária e explícita de uma organização em uma única área de trabalho, adicional
  às funcionalidades pessoais da pessoa autenticada.
- **Preparação do tenant**: processo que torna a organização apta ao uso e comunica seu estado até que possa ser
  ativada ou seja identificada uma falha.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-TEN-001**: 100% das criações concluídas com sucesso resultam em um tenant ativo associado ao seu criador como
  membership ativa e atribuição administrativa protegida ao criador, sem associação adicional criada automaticamente.
- **SC-TEN-002**: 100% dos testes aprovados sem tenant selecionado demonstram que funcionalidades pessoais continuam
  disponíveis e funcionalidades contextuais permanecem indisponíveis.
- **SC-TEN-003**: 100% dos testes aprovados com duas abas e dois tenants distintos demonstram que uma seleção, troca ou
  encerramento não altera o contexto da outra aba.
- **SC-TEN-004**: 100% dos testes aprovados de troca, revogação ou desabilitação demonstram que nenhuma nova ação usa
  dados ou permissões do tenant anterior ou indisponível.
- **SC-TEN-005**: 100% dos testes aprovados de reenvio da mesma criação demonstram que não há tenant ou preparação
  duplicados.
