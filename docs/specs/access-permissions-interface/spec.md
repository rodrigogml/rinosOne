# Especificação da Feature: Interface unificada de acessos e permissões

**Feature**: `access-permissions-interface`
**Criada em**: 2026-09-28
**Status**: Rascunho
**Dependências**: `authorization-administration`, `advanced-authorization-policies`, `file-storage-drive`

## Objetivo

Disponibilizar uma experiência web única para visualizar e administrar acessos no contexto que o usuário está utilizando, sem reduzir as capacidades já existentes do modelo de autorização. A interface deve permitir que um usuário autorizado entenda quem possui acesso, o que cada pessoa pode fazer, por qual razão esse acesso existe e como alterá-lo com segurança.

A experiência deve funcionar para as três esferas de autorização do RinosOne:

- **Pessoal (`PERSONAL`)**: o espaço individual do usuário e os compartilhamentos que ele pode administrar;
- **Tenant (`TENANT`)**: a organização selecionada, seus participantes, papéis e recursos compartilhados;
- **Plataforma (`PLATFORM`)**: a administração central do RinosOne, disponível somente a administradores da plataforma autorizados.

## Limites e decisões de produto

[!IMPORTANT]
O contexto ativo determina a esfera administrada. A tela não deve permitir que alguém encontre, consulte ou altere dados de outro tenant, de outro espaço pessoal ou da plataforma apenas trocando um filtro visual.

- A interface é uma camada de administração e explicação. A decisão efetiva de autorização continua centralizada nos serviços de autorização e deve ser aplicada também pela API.
- O criador de um workspace possui acesso máximo por ser o responsável pelo workspace. Não existe conceito de proprietário por arquivo, pasta ou outro objeto armazenado.
- Papéis do sistema e permissões catalogadas pelo sistema podem ser consultados, mas não podem ser alterados ou excluídos pela interface.
- Papéis personalizados, concessões diretas, delegações, regras de separação de funções, solicitações de acesso, revisões e credenciais de integração permanecem disponíveis conforme as permissões do administrador, mas não devem tornar o fluxo básico mais difícil.
- A primeira entrega não cria novas chaves de permissão de negócio. Ela consome o catálogo e os mecanismos de autorização existentes; novas telas de negócio passarão a registrar suas chaves gradualmente.
- SCIM não integra o escopo desta funcionalidade nem deve ser apresentado como opção futura da tela.

## Cobertura de superfícies

| ID | Superfície | Tecnologia | Cobertura | Papel nesta feature |
|---|---|---|---|---|
| SURF-WEB-ADMIN | Administração de acesso | Web responsiva | Completa | Administração contextual de participantes, papéis, permissões, regras e histórico para `TENANT` e `PLATFORM`. |
| SURF-WEB-SHARING | Compartilhamento de recursos | Web responsiva | Completa | Consulta e alteração do acesso a recursos de workspaces `PERSONAL` e `TENANT`, respeitando a herança do compartilhamento. |
| SURF-WEB-ACCESS | Shell autenticado | Web responsiva | Parcial | Exibe o contexto ativo e oferece entrada segura para a administração de acessos quando o usuário a possuir. |
| SURF-API-AUTH | APIs protegidas | API HTTP | Parcial | Mantém contratos de autorização coerentes com as alterações efetuadas pela interface, sem criar uma experiência de administração fora da web. |

## Cenários de usuário e testes

### História 1 — Administrar participantes no contexto atual (prioridade P1)

Como administrador autorizado de um tenant ou da plataforma, quero abrir uma única área de acessos já vinculada ao contexto em que estou trabalhando para conceder, revisar ou remover os acessos de participantes sem o risco de atuar em outra esfera.

**Teste independente**: no contexto de um tenant, um administrador abre a área, identifica visualmente o tenant, atribui a uma pessoa um papel compatível e confirma o resultado. A mesma pessoa não recebe esse papel em outro tenant.

**Critérios de aceitação**:

1. A tela identifica em linguagem clara se a administração é pessoal, do tenant atual ou da plataforma, incluindo o nome do contexto quando aplicável.
2. A lista de participantes mostra apenas sujeitos que podem ser vistos e administrados naquele contexto.
3. Cada participante apresenta um resumo legível de acesso, distinguindo papéis, grupo, concessão direta, compartilhamento e delegação quando forem a origem do acesso.
4. Toda ação de alterar acesso confirma o alvo, o contexto e o impacto antes de ser concluída.
5. A tela não oferece ação que o usuário atual não tenha autorização para executar.

### História 2 — Conceder acesso por papéis compreensíveis (prioridade P1)

Como administrador, quero conceder responsabilidades por papéis descritos em linguagem de negócio, para não precisar conhecer todas as chaves técnicas de permissão para executar uma tarefa comum.

**Teste independente**: um administrador localiza um papel por nome ou finalidade, visualiza as capacidades que ele concede e o atribui a uma pessoa. Depois, consulta o resultado efetivo dessa pessoa.

**Critérios de aceitação**:

1. O catálogo apresenta nome, finalidade, esfera aplicável e resumo de capacidades de cada papel disponível.
2. A tela distingue papéis do sistema, somente leitura, de papéis personalizados administráveis no contexto.
3. O fluxo padrão recomenda papéis e grupos antes de concessões diretas.
4. Concessões diretas permanecem possíveis apenas nos controles avançados, com explicação do impacto e dos limites aplicáveis.
5. A consulta de acesso efetivo explica quais fontes contribuíram para cada capacidade exibida, sem expor detalhes internos desnecessários.

### História 3 — Compartilhar recursos de um workspace (prioridade P2)

Como responsável por um workspace pessoal ou administrador de tenant, quero compartilhar uma pasta ou recurso com outra pessoa de forma compreensível, para delegar leitura, escrita ou administração apenas no limite necessário.

**Teste independente**: em um workspace pessoal, o responsável compartilha uma pasta com leitura para outra pessoa; a pessoa destinatária visualiza somente o recurso permitido. O compartilhamento não fornece acesso a outros recursos do workspace nem a outro tenant.

**Critérios de aceitação**:

1. A interface separa compartilhamento de recursos das permissões administrativas do contexto, ainda que ambos sejam consultáveis na mesma área.
2. A tela informa se o acesso decorre diretamente do recurso ou foi herdado de uma pasta ou compartilhamento superior.
3. A tela permite ajustar ou revogar apenas o compartilhamento que o administrador pode controlar, sem produzir efeito implícito fora de seu escopo.
4. O responsável pelo workspace é apresentado como tal; a interface não cria ou sugere proprietários individuais para arquivos e pastas.
5. A alteração apresenta ao usuário o nível de acesso concedido e a quem ele se aplica antes da confirmação.

### História 4 — Entender e auditar um acesso (prioridade P2)

Como administrador ou auditor autorizado, quero investigar por que uma pessoa pode executar uma ação e ver as alterações relevantes de acesso, para resolver dúvidas e incidentes sem tentar reproduzir permissões manualmente.

**Teste independente**: para uma pessoa com acesso por grupo e uma concessão direta temporária, o administrador consulta o acesso efetivo, identifica ambas as fontes e abre o registro das alterações relacionadas.

**Critérios de aceitação**:

1. A tela oferece as perguntas “quem tem acesso?” e “o que esta pessoa pode fazer?” como caminhos de consulta distintos e claramente nomeados.
2. A explicação de acesso efetivo mostra a origem, a abrangência e uma eventual expiração de cada acesso aplicável.
3. O histórico apresenta quem realizou a alteração, quando, qual contexto foi afetado e o antes/depois adequado ao tipo de operação.
4. Filtros por pessoa, recurso, papel, tipo de evento e período permitem restringir a análise sem extrapolar o contexto ativo.
5. Dados sensíveis ou detalhes técnicos não necessários à decisão permanecem protegidos segundo as permissões do solicitante.

### História 5 — Utilizar controles avançados sem esconder capacidades (prioridade P3)

Como administrador experiente, quero acessar mecanismos avançados de autorização quando necessários, sem que eles dificultem a manutenção cotidiana de participantes e papéis.

**Teste independente**: um administrador encontra os controles avançados a partir da área unificada, configura uma concessão temporária ou revisa uma solicitação de acesso, enquanto um administrador que somente atribui papéis não precisa interagir com esses recursos.

**Critérios de aceitação**:

1. A área básica prioriza participantes, papéis e compartilhamentos, e oferece navegação progressiva para os controles avançados autorizados.
2. Solicitações de acesso, delegações, regras de separação de funções, políticas de autorização, revisões periódicas e identidades de serviço possuem entradas claras quando habilitadas para o contexto.
3. Cada controle avançado explica finalidade, escopo, risco e consequência da ação antes da confirmação.
4. A interface não remove nem mascara uma capacidade existente apenas por ser avançada; ela a organiza por nível de intenção e autorização.
5. A ausência de permissão para um recurso avançado não revela dados do recurso nem causa falha na navegação básica.

## Requisitos funcionais

- **FR-API-001**: o sistema DEVE disponibilizar uma entrada de administração de acesso vinculada ao contexto autenticado ativo, classificado como `PERSONAL`, `TENANT` ou `PLATFORM`.
- **FR-API-002**: a interface DEVE indicar de forma persistente a esfera e a identificação humana do contexto administrado durante fluxos de consulta, criação, edição e revogação.
- **FR-API-003**: o sistema DEVE restringir pessoas, papéis, recursos, eventos e operações exibidos à autorização efetiva do usuário e ao contexto ativo.
- **FR-API-004**: a interface DEVE permitir consultar participantes e seu resumo de acesso no escopo em que o usuário atual possui capacidade administrativa.
- **FR-API-005**: a interface DEVE permitir atribuir, alterar e remover papéis somente quando a operação for autorizada e compatível com a esfera administrada.
- **FR-API-006**: a interface DEVE apresentar o catálogo de papéis e permissões com nome, finalidade, escopo e resumo compreensíveis, sem exigir o conhecimento da chave técnica.
- **FR-API-007**: o sistema DEVE preservar a imutabilidade dos papéis e permissões do sistema, exibindo-os como leitura quando aplicável.
- **FR-API-008**: a interface DEVE orientar a concessão por papéis e grupos como caminho padrão e posicionar concessões diretas em controles avançados, mantendo-as disponíveis aos administradores autorizados.
- **FR-API-009**: a interface DEVE permitir consultar o acesso efetivo de uma pessoa e explicar, em linguagem clara, as fontes que o compõem.
- **FR-API-010**: a interface DEVE permitir administrar compartilhamentos de recursos de workspaces `PERSONAL` e `TENANT` dentro das capacidades do usuário atual, incluindo nível de acesso, origem e herança.
- **FR-API-011**: o sistema NÃO DEVE introduzir proprietário individual de arquivo, pasta ou objeto. A responsabilidade máxima sobre recursos do workspace DECORRE do responsável pelo workspace correspondente.
- **FR-API-012**: antes de confirmar uma alteração material de acesso, a interface DEVE mostrar a pessoa ou recurso afetado, o contexto, a capacidade concedida ou removida e a eventual duração.
- **FR-API-013**: a interface DEVE bloquear ações que deixariam um tenant sem ao menos um administrador apto, apresentando explicação e alternativa segura ao usuário.
- **FR-API-014**: a interface DEVE tornar acessíveis, de forma progressiva e somente a usuários autorizados, os mecanismos de solicitações, delegações, regras de separação de funções, políticas, revisões e identidades de serviço já suportados pela fundação de autorização.
- **FR-API-015**: a interface DEVE disponibilizar histórico filtrável das mudanças de autorização que o usuário pode auditar, preservando a separação entre contextos.
- **FR-API-016**: uma remoção, expiração, revogação ou alteração de acesso realizada pela interface DEVE produzir o mesmo efeito nos pontos de decisão de autorização utilizados pela aplicação e suas APIs.
- **FR-API-017**: a interface DEVE tratar perda de autorização, conflito de alteração, contexto alterado e resultado desatualizado de forma segura, sem confirmar uma operação em contexto diferente do apresentado.
- **FR-API-018**: a interface DEVE ser responsiva e navegável por teclado, com rótulos, estado de foco, mensagens de erro e confirmação acessíveis para as ações críticas.

## Entidades e conceitos

- **Contexto de administração de acesso**: esfera ativa (`PERSONAL`, `TENANT` ou `PLATFORM`) à qual a consulta ou alteração está limitada.
- **Sujeito de acesso**: pessoa, grupo, identidade de serviço ou outro principal que pode receber uma capacidade, conforme os modelos de autorização existentes.
- **Fonte de acesso**: vínculo que explica uma capacidade efetiva, como papel, grupo, concessão direta, compartilhamento, delegação ou política.
- **Papel**: conjunto nomeado de permissões, nativo do sistema ou personalizado, associado a uma esfera compatível.
- **Item de catálogo de permissão**: capacidade registrada pela aplicação, apresentada com finalidade humana, chave técnica e esfera aplicável quando a consulta detalhada for autorizada.
- **Compartilhamento de recurso**: vínculo de leitura, escrita ou administração limitado a um recurso de workspace e eventualmente herdado de seu ancestral.
- **Registro de auditoria de acesso**: evento consultável que evidencia alteração de acesso, contexto, responsável, instante e efeito relevante.

## Casos de borda

- Se o contexto ativo mudar durante um formulário, o sistema deve exigir nova confirmação no contexto atual ou descartar a operação pendente; nunca deve reaproveitá-la silenciosamente.
- Se uma pessoa possuir acesso por mais de uma fonte, a interface deve apresentar todas as fontes aplicáveis, sem prometer que remover uma delas eliminará as demais.
- Se uma concessão estiver expirada ou for revogada durante a consulta, a interface deve atualizar o estado e impedir uma alteração baseada em informação obsoleta.
- Se não houver resultado no contexto, a tela deve explicar a ausência sem sugerir dados de outros contextos.
- Se a remoção de um papel ou participante tornar o último administrador de tenant indisponível, a operação deve ser recusada com orientação para indicar outro administrador primeiro.
- Se um compartilhamento for herdado, a tela deve impedir a alteração direta da herança quando ela só puder ser modificada no recurso de origem.
- Se o usuário perder a capacidade administrativa enquanto está na tela, a navegação pode permanecer visível, mas ações de leitura e alteração devem ser reavaliadas e protegidas pelo servidor.

## Critérios de sucesso

- Um administrador autorizado consegue atribuir ou revogar um papel no tenant ativo sem navegar por telas específicas de cada módulo e sem afetar outro tenant.
- Em testes de aceitação, 100% das operações de alteração exibem o contexto e o alvo antes da confirmação.
- Em testes de integração, 100% das tentativas de administrar contexto diferente do ativo são recusadas pelo contrato de autorização correspondente.
- Em testes de aceitação, um administrador consegue identificar a origem de uma capacidade efetiva de uma pessoa a partir da área unificada.
- Em testes de integração, 100% das tentativas de remover o último administrador apto de um tenant são recusadas.
- Papéis e permissões do sistema permanecem imutáveis em todos os fluxos apresentados pela interface.
- Os controles avançados continuam localizáveis a administradores autorizados, sem serem obrigatórios para o fluxo básico de atribuição de papéis e compartilhamento.
- Os fluxos críticos podem ser concluídos por teclado e apresentam mensagens de erro e confirmação associadas à ação executada.
- Em homologação, as consultas `context`, `subjects` e `roles`, com página padrão de 25 itens em um contexto com até 1.000 sujeitos ativos, têm p95 de até 500 ms; `effective-access` e `explain` têm p95 de até 1 s.

## Fora de escopo desta SDD

- Criar novas regras de negócio, chaves ou papéis para módulos funcionais que ainda não os possuam.
- Alterar a semântica central de decisão de autorização, o modelo de escopos ou os contratos de API já definidos pelas SDDs de autorização.
- Implementar sincronização de diretórios, provisionamento SCIM ou integração equivalente.
- Criar uma interface nativa móvel, desktop ou de linha de comando.
- Definir a identidade visual final; a superfície seguirá o design system da aplicação, que será detalhado no planejamento de interface.

## Decisões de infraestrutura

Não se aplica nesta etapa. A feature não exige agendamento, alteração de serviço operacional, novo segredo ou mudança de infraestrutura; esses aspectos só poderão ser incluídos se o planejamento técnico demonstrar necessidade concreta.
