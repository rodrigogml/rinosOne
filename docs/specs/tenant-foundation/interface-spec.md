# Interface Specification: Fundação de Tenants

**Feature**: `tenant-foundation`
**Criada em**: 2026-09-24
**Status**: Planejada
**Spec**: [spec.md](spec.md)
**Plano**: [plan.md](plan.md)
**Catálogo de superfícies**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Tipo | Usuários | Cobertura | Escopo incluído | Escopo adiado ou excluído |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | WEB | Usuário autenticado e validado | FULL | Identificação, criação, acompanhamento, seleção, troca, encerramento e desabilitação do contexto de tenant. | Convites, membros, permissões detalhadas, módulos de negócio, imagem e edição completa de perfil de tenant. |
| SURF-FUTURE-CONSUMERS | OTHER | Não definido | DEFERRED | Nenhuma interface nesta entrega. | Aplicações nativas, integrações e paridade visual. |

## Current-State Evidence

| Surface ID | Rota, comando ou componente existente | Evidência | Comportamento atual |
| --- | --- | --- | --- |
| SURF-WEB-ACCESS | `resources/js/design-system/ApplicationTopBar.vue` | Barra contém marca e `UserMenu` à direita. | A barra apresenta somente o avatar pessoal; não há tenant, seletor ou contexto visual. |
| SURF-WEB-ACCESS | `resources/js/design-system/UserAvatar.vue` | Avatar circular e fallback de nome reutilizável. | Exibe imagem futura ou iniciais/símbolo; pode servir à identidade de tenant sem criar componente visual paralelo. |
| SURF-WEB-ACCESS | `resources/js/design-system/MobileNavigationDrawer.vue` | Painel móvel lateral existente. | A marca abre a navegação móvel; não há conteúdo contextual de tenant. |

## Interaction Inventory

| Interaction ID | Surface ID | Tipo | Mudança | Nome | Ponto de entrada |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-001 | SURF-WEB-ACCESS | CONTROL | MODIFIED | Identidade contextual na barra | Avatar de tenant à esquerda do avatar pessoal. |
| INT-WEB-002 | SURF-WEB-ACCESS | POPOVER / SHEET | NEW | Seletor de tenant | Acionamento do avatar de tenant. |
| INT-WEB-003 | SURF-WEB-ACCESS | DIALOG | NEW | Criação e gestão básica de tenants | Ação no seletor de tenant. |

## Interaction Details

### INT-WEB-001 — Identidade contextual na barra

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: permitir reconhecer e alterar o tenant da aba atual sem confundir esse contexto com a identidade pessoal.
**Actors and Permissions**: todo usuário autenticado e validado vê o acionador; somente tenants associados e ativos podem aparecer como contexto selecionado.
**Entry and Navigation**: o acionador fica imediatamente à esquerda do avatar pessoal, em desktop e telefone. Não altera a navegação pessoal, o menu do usuário ou o acionador de navegação móvel.
**Content and Data**: apresenta avatar circular do tenant e rótulo acessível com seu nome; sem seleção, usa `?` e o rótulo “Selecionar organização”. Com nome sem imagem, reaplica a regra existente de iniciais; imagem de tenant permanece adiada.
**Actions and Behavior**: tocar, clicar ou pressionar Enter/Espaço abre INT-WEB-002. Ao selecionar ou encerrar contexto, o avatar muda apenas naquela aba. Ao perder a validade, volta ao estado neutro e informa o motivo sem citar dados internos não autorizados.
**Validation and Feedback**: o estado selecionado só é apresentado após a validação contextual. Falhas preservam o tenant anterior, quando houver, e apresentam feedback transitório acessível.
**Responsive/Adaptive Behavior**: em desktop, os dois avatares permanecem à direita da marca. Em telefone, a marca permanece à esquerda; os avatares usam tamanho compacto e o espaço da marca reduz-se antes de ocultar qualquer contexto. O seletor abre como folha modal móvel, não como popover estreito.
**Accessibility**: o acionador é um botão nomeado; foco visível, alvo tocável conforme tokens existentes, ordem de foco marca, tenant, usuário. Alterações de contexto são anunciadas em região de status.
**Localization**: todos os rótulos, estados e ações pertencem ao catálogo de idiomas existente em português do Brasil, inglês, espanhol e francês. Nomes de tenant não são traduzidos.
**Components and Design System**: reutiliza `ApplicationTopBar`, `UserAvatar`, `UIRinoButton`, tokens de avatar e camadas flutuantes. A evolução é um componente semântico reutilizável de avatar/menu de tenant, não um controle exclusivo da barra.
**Integration and Contracts**: consome a fotografia retornada por `POST /api/v1/tenants/{tenantId}/contexts` e seu encerramento. Não consome nem armazena credencial de infraestrutura.
**Telemetry**: registra abertura do seletor, seleção concluída, troca, encerramento e invalidação, com identificador técnico do tenant somente no canal de segurança; não inclui nomes, schemas ou conteúdo contextual.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/tenant-workspace.md

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | Avatar neutro `?`; workspace pessoal permanece íntegro. | Abrir seletor. | INT-WEB-002. |
| loading | Avatar permanece no último estado válido, sem trocar antecipadamente. | Cancelar abertura quando aplicável. | Pronto ou erro. |
| empty | Igual ao estado inicial; não usa mensagem visual permanente redundante. | Criar ou abrir gestão pelo seletor. | INT-WEB-002. |
| ready | Avatar com iniciais e nome acessível do tenant ativo. | Abrir seletor; encerrar contexto. | Troca, encerramento ou invalidação. |
| processing | Indicador discreto no seletor; avatar anterior continua identificável. | Cancelamento N/A — seleção é curta e atômica. | Sucesso ou erro. |
| success | Região de status anuncia a organização ativa. | Continuar no workspace. | Estado ready. |
| validation-error | N/A — a seleção não possui entrada digitável. | — | — |
| remote-error | Feedback seguro; avatar anterior não é substituído. | Tentar novamente. | Nova seleção. |
| offline | Seletor informa indisponibilidade e não oferece seleção. | Fechar. | Retorno online e nova abertura. |
| access-denied | Avatar neutro ou anterior preservado; mensagem não revela tenant inacessível. | Fechar e escolher outro tenant. | INT-WEB-002. |
| partial-stale | N/A — nenhuma lista ou contexto em cache é usado como autorização. | — | — |

### INT-WEB-002 — Seletor de tenant

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: listar organizações disponíveis, explicitar o contexto ativo e permitir escolher, trocar ou encerrar um tenant sem ocultar funcionalidades pessoais.
**Actors and Permissions**: usuário autenticado e validado; a lista contém somente organizações vinculadas à pessoa.
**Entry and Navigation**: abre pelo avatar de tenant. Em desktop é um popover ancorado ao acionador; em telefone é uma folha modal. Escape, toque externo quando aplicável e botão de fechar encerram sem modificar contexto.
**Content and Data**: cabeçalho “Organizações”; ação “Usar somente meu espaço” quando houver tenant ativo; até cinco organizações operacionais, com a selecionada no topo e indicação textual/visual; e o link “Gerenciar organizações”, reservado à tela futura de gestão. A lista vem ordenada pela seleção contextual válida mais recente. Acima de cinco itens, “Mais organizações” abre um diálogo de filtro com todos os vínculos e seus estados.
**Actions and Behavior**: escolher organização inicia validação e substitui o contexto apenas depois de sucesso. “Usar somente meu espaço” encerra o contexto atual. O diálogo de mais organizações aceita filtro, setas, Enter e Escape. Não há seleção automática, inclusive com uma única organização; a recência não restaura contexto em abas.
**Validation and Feedback**: carrega a lista ao abrir. Lista vazia apresenta explicação curta e ação de criar. Falha de rede preserva a seleção ativa e oferece nova tentativa. Uma resposta de acesso negado remove somente a opção afetada da apresentação atual e não revela sua causa.
**Responsive/Adaptive Behavior**: popover respeita bordas da viewport e fica próximo ao avatar em telas largas. Em telefone abre da parte inferior, ocupa largura segura, respeita área segura e permite rolagem interna; lista, ações e botão de fechamento permanecem alcançáveis com teclado virtual aberto.
**Accessibility**: usa diálogo não modal no desktop e diálogo modal no telefone; foco inicial no título/fechamento, ciclo de foco quando modal e retorno ao avatar ao fechar. Itens da lista têm nome, estado e indicação de seleção para leitor de tela; nunca dependem apenas de cor.
**Localization**: pluralização de lista vazia e mensagens de disponibilidade são localizadas nos quatro idiomas; nomes de tenant preservam sua grafia.
**Components and Design System**: reutiliza superfície flutuante, `UserAvatar`, `UIRinoButton`, `UiAlert`, `UiDialog` e tokens de espaçamento; introduz `TenantSelector` reutilizável para futura navegação e páginas de módulos.
**Integration and Contracts**: consome `GET /api/v1/tenants`, início e encerramento de contexto do contrato de tenants. A lista só informa apresentação; a seleção sempre chama validação remota.
**Telemetry**: abertura, lista vazia, seleção tentada, seleção concluída, seleção negada, encerramento e falha de carregamento; sem nomes ou dados da organização.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/tenant-workspace.md

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | Superfície fechada até acionamento explícito. | Abrir pelo avatar. | Loading. |
| loading | Esqueleto de cabeçalho e itens; tenant ativo ainda identificável na barra. | Fechar. | Ready, empty ou erro. |
| empty | “Você não pertence a nenhuma organização” e link de gestão futura. | Fechar. | initial. |
| ready | Lista operacional limitada e acesso a mais organizações/gestão futura. | Selecionar, encerrar, abrir mais organizações, fechar. | Processing ou initial. |
| processing | Item solicitado mostra progresso e lista fica indisponível para nova troca. | Cancelar N/A — não há alteração local antes de sucesso. | Success, erro ou access-denied. |
| success | Anúncio curto confirma a nova organização ou retorno ao espaço pessoal. | Continuar. | Fecha e INT-WEB-001 ready/initial. |
| validation-error | N/A — não há campos nesse seletor. | — | — |
| remote-error | Alerta seguro com nova tentativa; estado contextual anterior preservado. | Tentar novamente; fechar. | Loading ou initial. |
| offline | Alerta de indisponibilidade, lista não é reutilizada como permissão. | Fechar. | Reabrir após conexão. |
| access-denied | Item não é ativado e feedback não diferencia inexistência, vínculo ou estado. | Escolher outro; fechar. | Ready ou initial. |
| partial-stale | N/A — respostas antigas não permitem iniciar contexto. | — | — |

### INT-WEB-003 — Criação e gestão básica de tenants

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: permitir criar uma organização, acompanhar sua preparação e alterar a disponibilidade de organizações quando a pessoa possuir a capability administrativa aplicável.
**Actors and Permissions**: usuário autenticado e validado cria organizações; somente quem possui a capability de administrar disponibilidade vê essa ação. Nesta fase toda organização listada pertence ao seu criador.
**Entry and Navigation**: abre pelo seletor; fecha de volta ao seletor, mantendo sua lista atualizada. Não cria uma página pessoal separada nem remove a área pessoal.
**Content and Data**: título “Organizações”; formulário com campo obrigatório “Nome da organização” e ação “Criar organização”; lista de tenants próprios com avatar, nome, estado e ação compatível. Estados em preparação e falha são exibidos aqui, mas não no seletor operacional.
**Actions and Behavior**: criar envia uma única intenção; o botão bloqueia reenvio enquanto processa. Após aceitação, mantém o nome e mostra o item em preparação. A pessoa pode fechar o diálogo enquanto a preparação continua. Em tenant ativo, “Desabilitar” pede confirmação clara; em inativo, “Habilitar” reativa sem criar novo tenant. Não há exclusão, edição de nome, convite ou administração de membros.
**Validation and Feedback**: nome vazio ou longo mostra erro junto ao campo e preserva o texto. Conflito de intenção repete o resultado existente. Falha técnica exibe estado seguro e ação de atualizar; detalhes internos não são mostrados. A listagem é atualizada ao abrir e em intervalo curto somente enquanto o diálogo estiver aberto e houver preparação pendente.
**Responsive/Adaptive Behavior**: em desktop usa diálogo central com largura de leitura e lista rolável. Em telefone ocupa área segura com rolagem interna, formulário empilhado e ações de disponibilidade em largura total. Fechar pelo teclado virtual preserva os dados digitados.
**Accessibility**: diálogo modal com título, descrição e foco inicial no campo de nome. Erro associado ao campo e anunciado; confirmação de desabilitação exige foco explícito na ação segura. O estado de preparação usa texto e ícone, não somente cor.
**Localization**: título, rótulos, estados, confirmações e erros existem nos quatro idiomas. Nome é conteúdo do usuário e não é traduzido.
**Components and Design System**: reutiliza `UiDialog`, `UiField`, `UIRinoButton`, `UiAlert`, avatar e tokens. Introduz um item de estado de tenant reutilizável em futuras listas, sem criar cards exclusivos desta tela.
**Integration and Contracts**: consome criação, listagem e disponibilidade definidos em [tenant-context.md](contracts/tenant-context.md). A chave de intenção nasce no envio e é reutilizada somente na repetição daquela mesma criação.
**Telemetry**: abertura, criação iniciada/aceita/falha, mudança de disponibilidade solicitada/concluída e atualização de estado; nomes, chaves de intenção, schemas e detalhes de falha são excluídos.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/tenant-workspace.md

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | Diálogo fechado até a ação do seletor. | Abrir. | Loading. |
| loading | Estrutura do formulário disponível e lista em carregamento. | Fechar; digitar nome. | Ready ou erro. |
| empty | Formulário e mensagem de que a primeira organização ainda não existe. | Criar; fechar. | Processing ou selector. |
| ready | Formulário e lista com estado/ações por tenant. | Criar, atualizar, habilitar, desabilitar, fechar. | Processing, confirmação ou selector. |
| processing | Botão de criação e item afetado indicam progresso; demais itens não são alterados. | Fechar; cancelar N/A após aceitação. | Success, erro ou ready. |
| success | Confirmação curta e item atualizado. | Selecionar pelo seletor; continuar gerenciando. | Ready ou INT-WEB-002. |
| validation-error | Erro associado ao campo Nome; conteúdo digitado preservado. | Corrigir e reenviar. | Processing. |
| remote-error | Alerta seguro junto ao item/formulário; nenhum dado interno exposto. | Atualizar ou tentar novamente. | Loading, processing ou ready. |
| offline | Criação e mudança de disponibilidade indisponíveis; texto digitado fica preservado. | Fechar. | Nova tentativa após reconexão. |
| access-denied | Ação de disponibilidade some ou é negada com feedback seguro. | Fechar; atualizar lista. | Ready. |
| partial-stale | Estado de preparação identificado como possivelmente desatualizado até nova consulta. | Atualizar. | Loading. |

## Cross-Surface Rules

### Navegação e paridade

O workspace pessoal é a base permanente. Selecionar tenant acrescenta contexto à mesma área e não cria espaço separado. A marca mantém sua função de abrir a navegação móvel; o avatar de tenant concentra a seleção contextual; o avatar pessoal continua abrindo apenas o menu pessoal. Não há paridade automática com aplicações nativas ou integrações.

Ao trocar tenant, a interface limpa o contexto da aba antes de apresentar o próximo. Se uma futura tela contextual declarar alterações não confirmadas, a troca deverá pedir confirmação antes do descarte; a fundação atual não possui formulário contextual que produza esse estado.

### Conteúdo e terminologia compartilhados

Usar “organização” como termo de interface e “tenant” apenas em documentação técnica, contratos e mensagens de suporte. Estados visíveis são “Preparando”, “Ativa”, “Desabilitada” e “Indisponível”. “Usar somente meu espaço” encerra o contexto sem encerrar a sessão autenticada.

### Acessibilidade e entrada compartilhadas

Todas as interações funcionam por mouse, toque e teclado. Popovers e diálogos preservam foco, permitem Escape e exibem foco visível. Mudanças de contexto e estado usam anúncios textuais. Zoom, preferência de movimento reduzido, temas e as três escalas visuais permanecem aplicáveis por tokens existentes.

## Traceability

| Interaction ID | User Stories | Requisitos Funcionais | Critérios de Sucesso | Contratos |
| --- | --- | --- | --- | --- |
| INT-WEB-001 | US-002, US-003, US-004 | FR-TEN-005, FR-TEN-006, FR-TEN-008, FR-TEN-011, FR-TEN-012, FR-TEN-014 | SC-TEN-002, SC-TEN-003, SC-TEN-004 | [tenant-context.md](contracts/tenant-context.md) |
| INT-WEB-002 | US-002, US-003, US-004 | FR-TEN-007 a FR-TEN-014, FR-TEN-018 | SC-TEN-002 a SC-TEN-004 | [tenant-context.md](contracts/tenant-context.md) |
| INT-WEB-003 | US-001, US-004 | FR-TEN-001 a FR-TEN-005, FR-TEN-015 a FR-TEN-019 | SC-TEN-001, SC-TEN-004, SC-TEN-005 | [tenant-context.md](contracts/tenant-context.md) |

## Wireframes

| Interaction ID | Exigência | Artefato | Notas |
| --- | --- | --- | --- |
| INT-WEB-001 | REQUIRED | [tenant-workspace.md](wireframes/tenant-workspace.md) | Barra desktop e telefone. |
| INT-WEB-002 | REQUIRED | [tenant-workspace.md](wireframes/tenant-workspace.md) | Popover desktop e folha móvel. |
| INT-WEB-003 | REQUIRED | [tenant-workspace.md](wireframes/tenant-workspace.md) | Diálogo de criação e estados. |

## Validation Summary

- Matriz de cobertura revisada: sim.
- Todos os itens do inventário detalhados: sim.
- Estados canônicos resolvidos: sim.
- Wireframes obrigatórios presentes: sim.
- Requisitos de acessibilidade resolvidos: sim.
- Mapeamentos de contratos verificados: sim.
- Placeholders ou decisões abertas restantes: 0.
