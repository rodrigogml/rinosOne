# Interface Specification: Área de Trabalho da Aplicação

**Feature**: `workspace-shell`
**Created**: 2026-09-24
**Status**: Implementada
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | WEB | Usuário autenticado e validado | FULL | Área de trabalho, navegação, superfícies, taskbar, diálogos e notificações, adaptação móvel. | Produtos, módulos, restauração entre sessões, painéis acopláveis e janelas livres. |
| SURF-FUTURE-CONSUMERS | OTHER | Consumidores autorizados futuros | DEFERRED | Conceitos documentados para integração futura. | Interface e paridade de outros clientes. |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
| --- | --- | --- | --- |
| SURF-WEB-ACCESS | Área autenticada em `resources/js/App.vue` e `resources/js/design-system/AuthenticatedFrame.vue` | Validação automatizada em 2026-09-25 | A top bar, o workspace, a navegação desktop/móvel, superfícies efêmeras, taskbar, diálogos e notificações estão integrados. O catálogo contém somente fixtures visuais sem dados, rotas ou regras de negócio. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-WORKSPACE-001 | SURF-WEB-ACCESS | SCREEN | MODIFIED | Palco da Área de trabalho | Sessão autenticada válida. |
| INT-WEB-WORKSPACE-002 | SURF-WEB-ACCESS | PANEL | NEW | Navegação lateral e mega menu | Categoria no menu lateral. |
| INT-WEB-WORKSPACE-003 | SURF-WEB-ACCESS | PANEL | NEW | Taskbar, diálogos e notificações | Superfície aberta ou ação do shell. |
| INT-WEB-WORKSPACE-004 | SURF-WEB-ACCESS | PANEL | MODIFIED | Navegação e tarefas em telefone | Marca móvel ou controle de superfícies. |

## Interaction Details

### INT-WEB-WORKSPACE-001 — Palco da Área de trabalho

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: substituir o conteúdo demonstrativo por uma área neutra que apresente uma única superfície ativa e preserve as demais abertas na mesma aba.
**Actors and Permissions**: usuário autenticado e validado; o palco nunca concede permissão e só apresenta superfícies vindas de destinos autorizados.
**Entry and Navigation**: aparece após a top bar e permanece enquanto a sessão for válida. Sem superfície aberta, mostra estado inicial neutro; destino, taskbar ou atalho ativam uma superfície. Atualização da página inicia novamente no estado neutro.
**Content and Data**: o canvas usa a altura visível do navegador. A top bar fica fora do `main`; o `main` abaixo dela contém menu lateral integral à esquerda, área de janelas à direita e taskbar no rodapé dessa coluna. Sem superfície, a área de janelas permanece neutra, sem título, mensagem, cartão ou moldura. A moldura pertence somente à janela aberta; sua sombra usa token próprio e permanece contida na margem da área de janelas, evitando uma divisão visual acima da taskbar transparente.
**Actions and Behavior**: abrir destino cria ou foca superfície conforme sua política; somente a ativa recebe foco e interação. Abrir superfície recolhe o menu lateral; fechar a última reabre a descoberta. Trocar ou encerrar organização remove somente superfícies contextuais da aba.
**Validation and Feedback**: o shell rejeita destino não autorizado, removido ou incompatível com o contexto sem revelar detalhes internos; preserva a superfície ativa anterior e anuncia indisponibilidade segura. Não há formulário nem requisição nova.
**Responsive/Adaptive Behavior**: desktop e tablet largo usam rail, área de janelas e taskbar. Em telefone, a área de janelas ocupa toda a largura segura e navegação/tarefas passam a INT-WEB-WORKSPACE-004. Não existe rolagem geral ou horizontal do shell; somente conteúdo interno que a exigir pode rolar.
**Accessibility**: a top bar permanece `header`; a Área de trabalho é o `main`; superfície ativa possui título único e recebe foco programático somente após abertura solicitada. Superfícies inativas não entram na ordem de foco. Tema, contraste, zoom, foco visível e redução de movimento usam os tokens existentes.
**Localization**: estado neutro, títulos, avisos e instruções têm chaves nos quatro idiomas; títulos fornecidos por módulos futuros são localizados pelo destino, não por texto literal do shell.
**Components and Design System**: evolui `AuthenticatedFrame` e `AppShell`; introduz `WorkspaceShell` e `WorkspaceStage`; reutiliza tokens de superfície, borda, sombra, tipografia e movimento reduzido.
**Integration and Contracts**: usa [workspace-runtime.md](contracts/workspace-runtime.md); observa o contexto já mantido pela fundação de tenants, sem alterar contrato HTTP.
**Telemetry**: N/A nesta entrega. Não registrar títulos, organização, estado de superfícies ou atalhos.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/workspace-desktop.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Top bar e shell carregados; nenhuma superfície ativa. | Abrir navegação. | loading. |
| loading | Estrutura do palco visível sem conteúdo residual. | Nenhuma ação duplicada. | empty ou ready. |
| empty | Área neutra e orientação discreta, sem módulo fictício. | Abrir navegação. | ready ao abrir superfície. |
| ready | Uma superfície ativa e taskbar coerente. | Alternar, fechar, abrir navegação. | processing, access-denied ou empty. |
| processing | Abertura, fechamento ou limpeza contextual em curso. | Evitar repetição da mesma intenção. | ready, empty ou remote-error. |
| success | Anúncio curto de abertura, foco ou fechamento quando necessário. | Continuar. | ready ou empty. |
| validation-error | N/A — não há entrada livre no palco. | N/A — motivo: validação pertence à superfície futura. | N/A — motivo: validação pertence à superfície futura. |
| remote-error | Aviso seguro de destino indisponível; superfície anterior preservada. | Fechar aviso ou tentar destino permitido. | ready ou empty. |
| offline | Superfícies locais abertas permanecem operáveis; ações de rede pertencem ao módulo. | Alternar e fechar. | ready ao reconectar. |
| access-denied | Shell limpa superfícies e segue à jornada pública. | Usar acesso público. | Jornada pública. |
| partial-stale | Superfície contextual é removida se seu contexto deixa de ser válido. | Continuar em superfície pessoal ou estado neutro. | ready ou empty. |

### INT-WEB-WORKSPACE-002 — Navegação lateral e mega menu

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: permitir descoberta rápida de categorias e destinos autorizados sem ocupar permanentemente o palco.
**Actors and Permissions**: usuário autenticado e validado; destinos pessoais sempre elegíveis conforme catálogo; destinos de organização somente com contexto válido.
**Entry and Navigation**: no desktop, passar o ponteiro por cada categoria do rail abre o mega menu correspondente; clique e teclado continuam disponíveis. O popover ocupa a largura disponível da área de janelas sem deslocá-la. Clique externo, Escape, abertura de superfície, perda de contexto ou sessão fecham-no.
**Content and Data**: rail mostra controle de recolhimento e categorias com ícone/nome; mega menu apresenta título de categoria, colunas e grupos. As fixtures de demonstração validam o shell com densidade realista, mas não expõem dados, rota, permissão, integração ou regra de um módulo de negócio.
**Actions and Behavior**: recolher mostra somente ícones com nome acessível; expandir recupera nomes. Selecionar destino envia intenção ao runtime e fecha o menu somente após a intenção ser aceita. Um menu aberto substitui outro, nunca acumula sobreposições.
**Validation and Feedback**: destinos incompatíveis com contexto não são apresentados; se a elegibilidade mudar enquanto aberto, o menu fecha e o foco retorna à categoria. Não há falha de rede própria.
**Responsive/Adaptive Behavior**: rail é visível a partir de tablet largo; mega menu tem altura intrínseca com mínimo estético e é alinhado verticalmente ao item acionador. Seu topo é limitado à área de janelas, para nunca encobrir topbar ou taskbar, sair do canvas, ocultar conteúdo ou criar rolagem própria. Em telefone, ambos são substituídos pelo painel de INT-WEB-WORKSPACE-004.
**Accessibility**: categorias são botões com `aria-expanded` e relação com o painel; Escape retorna foco à categoria; menu aberto recebe foco no primeiro destino ou no título quando vazio. Itens têm nome textual, ícone complementar e navegação por Tab; clique externo nunca é a única forma de fechar.
**Localization**: os componentes aceitam chaves localizadas para categoria, grupos, vazio e rótulos acessíveis; as fixtures temporárias podem prover rótulos de teste sem se tornarem conteúdo de produto. Texto longo quebra linha sem truncar identificação essencial.
**Components and Design System**: introduz `WorkspaceNavigationRail` e `WorkspaceMegaMenu`; reutiliza `IconButton`, superfícies flutuantes, tokens de transição e ícones SVG.
**Integration and Contracts**: consome somente o catálogo e o contrato interno [workspace-runtime.md](contracts/workspace-runtime.md); nenhuma chamada é feita para montar menu nesta fase.
**Telemetry**: N/A nesta entrega. Não registrar categoria, destino, organização ou preferências de navegação.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/workspace-desktop.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Rail expandido e mega menu fechado. | Abrir categoria; recolher rail. | loading. |
| loading | Catálogo local sendo avaliado; rail não exibe destino incorreto. | Fechar ou aguardar. | empty ou ready. |
| empty | Mega menu informa ausência de destinos autorizados. | Fechar; trocar categoria. | initial ou ready. |
| ready | Categoria e grupos autorizados visíveis. | Abrir destino, trocar categoria, recolher, fechar. | processing ou initial. |
| processing | Intenção de abertura encaminhada; categoria não dispara duplicação. | Aguardar; Escape quando ainda aplicável. | success, remote-error ou initial. |
| success | Mega menu fecha após superfície ganhar foco. | Trabalhar na superfície. | INT-WEB-WORKSPACE-001 ready. |
| validation-error | N/A — não há formulário. | N/A — motivo: sem entrada livre. | N/A — motivo: sem entrada livre. |
| remote-error | Destino recusado mostra aviso seguro sem expor motivo. | Fechar aviso; escolher outro. | ready ou initial. |
| offline | Menu local continua navegável; disponibilidade de operações pertence ao módulo. | Abrir destino local. | ready. |
| access-denied | Menu fecha ao perder sessão ou contexto obrigatório. | Retornar ao acesso público ou estado pessoal. | Jornada pública ou initial. |
| partial-stale | Categoria contextual é removida após troca de organização. | Abrir categoria pessoal. | initial ou ready. |

### INT-WEB-WORKSPACE-003 — Taskbar, diálogos e notificações

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: permitir alternância produtiva entre superfícies e fornecer feedback global previsível sem perder foco ou trabalho pendente.
**Actors and Permissions**: usuário autenticado e validado; taskbar mostra somente superfícies da aba atual; diálogo de descarte é solicitado apenas pela superfície que declarou alteração pendente.
**Entry and Navigation**: taskbar aparece com a primeira superfície aberta; ativação ocorre por item, atalho ou sequência de fechamento. Diálogo é aberto pelo runtime; notificação pode decorrer de qualquer ação autorizada.
**Content and Data**: taskbar inferior centraliza somente os ícones SVG das superfícies. O ativo tem escala maior e marcador pill abaixo; hover amplia discretamente qualquer ícone. Nome permanece disponível por semântica e tooltip, mas não é exibido. A taskbar é transparente e não cria linha divisória: flutua sobre o fundo da área à direita do menu. Cada janela repete seu ícone no cabeçalho e mostra X no extremo direito para solicitar fechamento. O diálogo de área de trabalho cobre e bloqueia menu, área de janelas e taskbar, mas não a topbar. Cada janela hospeda sua própria pilha efêmera de diálogos locais: somente o topo recebe interação, um novo diálogo pode abrir sobre o anterior e a pilha permanece intacta quando outra janela recebe foco. Esse escopo bloqueia apenas a janela, mantendo menu, taskbar e demais janelas acessíveis; ele só é descartado quando sua própria janela é fechada. Notificação temporária exibe uma mensagem por vez, tipo semântico e área de anúncio sem bloquear foco.
**Actions and Behavior**: ativar item troca o palco sem destruir outras superfícies. O X da janela solicita fechamento; se houver alteração pendente, abre confirmação; cancelar preserva estado; confirmar remove só a instância solicitada. Próxima/anterior e fechar ativa usam atalhos seguros documentados e ignoram eventos originados em campos editáveis. Notificações seguem fila e não bloqueiam tarefa, salvo mensagem explicitamente persistente.
**Validation and Feedback**: confirmação de descarte não é fechada por clique externo quando o descarte exige decisão explícita. Falha em fechar preserva item e foco; não há endpoint novo. Ícone nunca é a única indicação de ativo, tipo ou erro.
**Responsive/Adaptive Behavior**: desktop e tablet usam taskbar horizontal de ícones centralizados, com rolagem interna somente quando a quantidade exceder a largura. Telefone troca taskbar por lista modal de superfícies em INT-WEB-WORKSPACE-004.
**Accessibility**: item ativo expõe estado por `aria-current` ou equivalente; setas podem mover foco dentro da taskbar, Enter ativa e Delete/controle explícito solicita fechamento. Diálogo da área de trabalho prende foco dentro da área abaixo da topbar, oferece Escape apenas quando dispensável e devolve foco ao originador. Diálogo de janela prende foco somente no limite da própria janela. Notificações usam anúncio educado; erros críticos usam anúncio assertivo sem roubar foco.
**Localization**: títulos de controles, confirmação, tipos e mensagens são localizados; contador ou pluralização de superfícies usa o idioma ativo.
**Components and Design System**: introduz `WorkspaceTaskbar`, `WorkspaceOverlayHost`, `WorkspaceWindowDialogHost` e `WorkspaceNotificationHost`; reutiliza `UiDialog`, `UiAlert`, `UiButton`, tokens de superfície, foco e movimento.
**Integration and Contracts**: usa [workspace-runtime.md](contracts/workspace-runtime.md); superfícies futuras reportam estado pendente e solicitam operações ao runtime.
**Telemetry**: N/A nesta entrega. Não registrar títulos, conteúdo de mensagens, atalhos ou alterações pendentes.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/workspace-taskbar-dialog.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Taskbar oculta; fila e pilha vazias. | Nenhuma. | ready após abrir superfície. |
| loading | N/A — estado em memória é síncrono. | N/A — motivo: não há consulta remota. | ready. |
| empty | Taskbar some após última superfície; diálogo e fila vazios. | Abrir navegação. | initial. |
| ready | Taskbar mostra superfícies; nenhuma confirmação pendente. | Ativar, solicitar fechamento, usar atalhos. | processing ou success. |
| processing | Confirmação, fechamento ou troca em andamento. | Cancelar ou confirmar quando aplicável. | ready, success ou remote-error. |
| success | Item atualizado/removido ou notificação apresentada. | Continuar trabalho. | ready ou empty. |
| validation-error | N/A — shell não valida dados de formulário. | N/A — motivo: pertence à superfície. | N/A — motivo: pertence à superfície. |
| remote-error | Fechamento ou ação contextual recusada preserva item e foco. | Fechar aviso; retentar ação permitida. | ready. |
| offline | Alternância e fechamento local funcionam; ações de rede são responsabilidade da superfície. | Ativar, fechar, dispensar aviso. | ready. |
| access-denied | Pilha, fila e taskbar são limpas antes da jornada pública. | Usar acesso público. | Jornada pública. |
| partial-stale | Itens contextuais invalidados somem e geram aviso seguro enfileirado. | Continuar em item restante. | ready ou empty. |

### INT-WEB-WORKSPACE-004 — Navegação e tarefas em telefone

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: oferecer descoberta e alternância de superfícies em telefone sem reduzir a Área de trabalho a mini-janelas.
**Actors and Permissions**: usuário autenticado e validado em tela estreita; os mesmos filtros pessoais e contextuais do desktop são aplicados.
**Entry and Navigation**: a marca existente abre o painel de navegação; quando houver superfície aberta, o ícone de alternância de janelas à esquerda do seletor de organização abre a lista de superfícies. Painéis fecham por botão, Escape, acionador ou camada externa quando não bloqueantes.
**Content and Data**: painel de navegação mostra marca, fechar, categorias e destinos; o seletor de superfícies é um diálogo centralizado sobre a aplicação, com até 95% da largura e altura seguras do viewport, título, lista de instâncias abertas, indicação da ativa e controle de fechar por item. O palco preserva apenas a superfície ativa em largura total.
**Actions and Behavior**: selecionar destino ou superfície fecha o painel e focaliza o palco. Solicitar fechamento de item pendente abre a confirmação de INT-WEB-WORKSPACE-003. Abrir uma sobreposição estrutural fecha a outra; diálogo bloqueante fica acima dos dois painéis.
**Validation and Feedback**: destinos sem contexto não aparecem. Se não houver superfícies, o controle de tarefas não se apresenta como ação falsa. Mudança de organização fecha painel aberto e remove itens contextuais antes de listar o novo estado.
**Responsive/Adaptive Behavior**: até 639 px, a navegação ocupa largura segura e o seletor de superfícies ocupa até 95% do viewport, sempre respeitando safe areas, teclado virtual e rolagem interna. Nenhum acionador estrutural participa do fluxo vertical do palco. A orientação paisagem mantém palco prioritário. A partir de tablet largo, o rail e taskbar desktop substituem os painéis.
**Accessibility**: cada painel é diálogo modal com foco inicial no controle de fechar, ciclo de Tab e retorno ao acionador. A lista de superfícies comunica item ativo e pendente sem depender de cor. Alvos de toque respeitam a escala de componentes; redução de movimento elimina deslocamento não essencial.
**Localization**: todos os rótulos, vazio, ações e descrições usam os quatro idiomas; nomes de superfície podem expandir em duas linhas sem ocultar a ação de fechar.
**Components and Design System**: evolui `MobileNavigationDrawer`; introduz `WorkspaceMobileNavigationPanel` e `WorkspaceMobileTaskPanel`; reutiliza `BrandMark`, ícones, tokens de overlay e superfícies.
**Integration and Contracts**: usa [workspace-runtime.md](contracts/workspace-runtime.md) e o contexto em memória da aba; não cria rota, payload ou sessão adicional.
**Telemetry**: N/A nesta entrega. Não registrar largura, painel aberto, organização ou superfícies.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/workspace-mobile.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Palco ocupa tela; painéis fechados. | Abrir navegação; abrir tarefas quando existente. | loading. |
| loading | Painel avalia catálogo local sem mostrar itens indevidos. | Fechar painel. | empty ou ready. |
| empty | Navegação informa ausência de destinos; tarefa não abre lista vazia. | Fechar; usar área pessoal. | initial. |
| ready | Um painel modal mostra destinos ou superfícies válidas. | Selecionar, fechar, solicitar fechamento. | processing ou initial. |
| processing | Seleção ou fechamento em curso; duplicação é evitada. | Aguardar; cancelar confirmação quando aplicável. | success, remote-error ou initial. |
| success | Painel fecha e palco anuncia a superfície ativa. | Trabalhar no palco. | INT-WEB-WORKSPACE-001 ready. |
| validation-error | N/A — não há formulário no painel estrutural. | N/A — motivo: sem entrada livre. | N/A — motivo: sem entrada livre. |
| remote-error | Aviso seguro preserva painel ou palco anterior. | Fechar aviso; selecionar outro item. | ready ou initial. |
| offline | Navegação e alternância locais continuam disponíveis. | Selecionar superfície local; fechar painel. | ready. |
| access-denied | Painéis fecham e a casca deixa a sessão pública assumir. | Usar acesso público. | Jornada pública. |
| partial-stale | Item contextual removido da lista após troca ou perda de organização. | Escolher item pessoal restante. | ready ou empty. |

## Convenções de Layout de Janelas

Esta seção consolida somente as convenções já aplicadas. Novos padrões de listagem, edição e fluxos de negócio serão definidos quando houver uma superfície real que os exija.

### Canvas e Estrutura Permanente

- O canvas autenticado usa exclusivamente a área visível do navegador. A topbar permanece fixa, com fundo preto e tokens internos da variante escura da paleta ativa; a rolagem geral do canvas não é permitida.
- A área abaixo da topbar contém o menu lateral e, à direita, a área de janelas com taskbar. O fundo dessa área é contínuo; a taskbar não possui faixa, linha ou cor própria.
- Menu, mega menu, taskbar e janelas são componentes estruturais distintos. Um mega menu sobrepõe a área de janelas, sem deslocar seu conteúdo.

### Janela Geral

- A moldura, borda arredondada e sombra pertencem à janela aberta, nunca ao layout vazio da área de janelas.
- Toda janela possui ícone SVG reutilizável, repetido na taskbar e no cabeçalho compacto. O cabeçalho contém ícone e título à esquerda e o controle de fechamento à direita.
- O conteúdo usa toda a largura e altura disponível. Quando exceder a área útil, somente o conteúdo interno rola; conteúdo curto fica alinhado ao início, sem centralização artificial.
- A taskbar desktop centraliza ícones de janelas abertas: o item ativo é maior e usa um marcador pill inferior; hover amplia discretamente os demais itens. O nome continua disponível por semântica acessível, sem texto visível na barra.

### Janela de Configurações

- A variante de configurações mantém a navegação de seções à esquerda e o painel selecionado à direita. A lista de seções usa somente rótulos textuais, sem ícones.
- O painel da seção ocupa o espaço restante, é responsivo e alinha o conteúdo ao início quando houver espaço excedente.
- Em largura reduzida, a navegação e o painel passam a uma única coluna, preservando a ordem das seções e a rolagem interna necessária.
- Controles de escolha agrupados permanecem horizontais quando houver espaço e só refluem de modo seguro em telas estreitas; uma escolha persistida imediatamente aplica sua apresentação sem alterar a sessão ou a janela ativa.

### Camadas e Responsividade

- Um diálogo do workspace bloqueia somente a área abaixo da topbar. Uma janela mantém sua própria pilha de diálogos locais, que bloqueia somente essa instância, pode ter camadas e sobrevive à alternância de janelas; a pilha é descartada apenas com sua janela.
- Em telefone não há taskbar no rodapé nem acionador dentro do palco. O ícone de alternância de janelas fica na topbar, antes do seletor de organização, e abre o diálogo de superfícies centralizado. A marca continua sendo o acionador de navegação móvel sem receber aparência de botão destacado.

## Cross-Surface Rules

### Navigation and Parity

Desktop e telefone oferecem os mesmos destinos autorizados e a mesma coleção por aba, mas não a mesma composição visual. Desktop usa rail, mega menu e taskbar; telefone usa painéis modais e uma única superfície visível. A marca mantém a função de acionador de navegação móvel; avatar e seletor de organização continuam independentes da Área de trabalho.

### Shared Content and Terminology

Termos canônicos: “Área de trabalho”, “Navegação”, “Superfícies abertas”, “Fechar”, “Alterações não salvas”, “Descartar alterações”, “Cancelar” e “Organização”. “Janela” pode identificar uma superfície em contexto explicativo, mas a interface prioriza “superfície” para não simular um sistema operacional.

### Shared Accessibility and Input

Teclado físico, toque, ponteiro e leitor de tela operam todos os fluxos críticos. Alt+Shift+setas e Alt+Shift+W são candidatos documentados para alternância e fechamento, mas não disparam em campos editáveis ou quando conflitarem com comandos assistivos do navegador. Escape fecha apenas camadas dispensáveis. Todo estado ativo, pendente ou indisponível usa texto/semântica além de cor e ícone.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
| --- | --- | --- | --- | --- |
| INT-WEB-WORKSPACE-001 | US-001, US-002 | FR-WS-001, FR-WS-002, FR-WS-004, FR-WS-006, FR-WS-014, FR-WS-015 | SC-WS-001, SC-WS-002, SC-WS-005 | [workspace-runtime.md](contracts/workspace-runtime.md) |
| INT-WEB-WORKSPACE-002 | US-001 | FR-WS-003, FR-WS-004, FR-WS-005, FR-WS-007, FR-WS-014 | SC-WS-001, SC-WS-005 | [workspace-runtime.md](contracts/workspace-runtime.md) |
| INT-WEB-WORKSPACE-003 | US-002, US-003, US-004 | FR-WS-006 to FR-WS-012, FR-WS-014 | SC-WS-002 to SC-WS-004, SC-WS-006 | [workspace-runtime.md](contracts/workspace-runtime.md) |
| INT-WEB-WORKSPACE-004 | US-001, US-002, US-005 | FR-WS-003, FR-WS-004, FR-WS-006, FR-WS-008, FR-WS-013, FR-WS-014 | SC-WS-001, SC-WS-002, SC-WS-005 | [workspace-runtime.md](contracts/workspace-runtime.md) |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
| --- | --- | --- | --- |
| INT-WEB-WORKSPACE-001 | REQUIRED | wireframes/workspace-desktop.md | Estrutura desktop e palco neutro. |
| INT-WEB-WORKSPACE-002 | REQUIRED | wireframes/workspace-desktop.md | Rail e mega menu. |
| INT-WEB-WORKSPACE-003 | REQUIRED | wireframes/workspace-taskbar-dialog.md | Taskbar e confirmação de descarte. |
| INT-WEB-WORKSPACE-004 | REQUIRED | wireframes/workspace-mobile.md | Painéis móveis e palco único. |

## Validation Summary

- Coverage matrix reviewed: yes.
- All inventory items detailed: yes.
- Canonical states resolved: yes.
- Required wireframes present: yes.
- Accessibility requirements resolved: yes.
- Contract mappings verified: yes.
- Placeholders or open decisions remaining: 0.
