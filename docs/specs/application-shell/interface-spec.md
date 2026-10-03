# Interface Specification: Casca da Aplicação Autenticada

**Feature**: `application-shell`  
**Created**: 2026-09-24  
**Status**: Implementada  
**Spec**: [spec.md](spec.md)  
**Plan**: [plan.md](plan.md)  
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | WEB | Usuário autenticado e validado | FULL | Barra global, avatar, menu pessoal, utilitários, saída e painel móvel. | Produtos, módulos, itens de produto, perfil, imagem e edição de dados pessoais. |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
| --- | --- | --- | --- |
| SURF-WEB-ACCESS | Área autenticada em `resources/js/App.vue` e `resources/js/design-system/AuthenticatedFrame.vue` | Validação automatizada e inspeção local em 2026-09-24; ver [validation.md](validation.md). | Exibe barra global, marca paisagem compacta em todos os formatos, acionador de navegação sem moldura visual em telefone, avatar, menu pessoal e painel móvel sem destinos de produto. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-SHELL-001 | SURF-WEB-ACCESS | SCREEN | MODIFIED | Casca autenticada e barra superior | Sessão autenticada válida. |
| INT-WEB-SHELL-002 | SURF-WEB-ACCESS | POPUP | NEW | Menu pessoal | Avatar na barra superior. |
| INT-WEB-SHELL-003 | SURF-WEB-ACCESS | PANEL | NEW | Navegação móvel | Acionador de marca em tela estreita. |

## Interaction Details

### INT-WEB-SHELL-001 — Casca autenticada e barra superior

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: dar contexto permanente à pessoa autenticada e hospedar conteúdo presente e futuro sem repetir navegação global.
**Actors and Permissions**: usuário autenticado e validado; a casca não concede permissões novas.
**Entry and Navigation**: aparece após login ou confirmação de e-mail bem-sucedidos e permanece enquanto a sessão for válida. A saída transita para o acesso público existente; não há destino de produto novo nesta fase.
**Content and Data**: landmark de cabeçalho com marca paisagem proporcional à esquerda, avatar à direita e conteúdo principal abaixo. Em telefone, a mesma marca é o acionador de navegação, sem moldura ou aparência visual de botão. O avatar mostra imagem futura quando disponível; sem ela, gera iniciais de `displayName` ou `?` segundo a regra da spec. O conteúdo de segurança existente continua sendo o conteúdo principal inicial.
**Actions and Behavior**: em desktop, a marca identifica a plataforma sem criar navegação fictícia. Em telefone, a marca abre INT-WEB-SHELL-003. O avatar abre INT-WEB-SHELL-002 em qualquer largura. Abrir uma sobreposição estrutural fecha a outra. A casca não solicita dados de perfil nem chama rede adicional.
**Validation and Feedback**: nomes são tratados removendo espaços vazios; letras acentuadas e não latinas permanecem intactas. Falha ou ausência de imagem futura recai no fallback de nome. Perda de sessão fecha sobreposições e segue a transição pública existente, sem exibir dado pessoal residual.
**Responsive/Adaptive Behavior**: desktop a partir de 1024 px apresenta logotipo paisagem com maior hierarquia visual que o avatar nas extremidades da barra. Tablet preserva a barra e reduz espaços sem comprimir alvos. Telefone até 639 px mantém a marca paisagem como acionador de navegação e o avatar visível. Conteúdo usa rolagem natural; a barra não cria rolagem horizontal em escalas confortáveis ou zoom.
**Accessibility**: cabeçalho e principal são landmarks distintos; o heading do conteúdo continua sendo a referência do main. Marca móvel recebe nome acessível “Abrir navegação”; avatar recebe nome que inclua a área pessoal e o nome quando disponível. A ordem de foco é marca ou navegação, avatar, conteúdo principal. Avatar e acionador móvel expõem estado expandido e relação com o painel aberto. Contraste, foco, tamanho mínimo de toque e redução de movimento usam os padrões da fundação visual.
**Localization**: nomes de ações e rótulos acessíveis vêm do catálogo nos quatro idiomas. O nome da pessoa é dado e não é traduzido. Textos variáveis suportam expansão sem corte.
**Components and Design System**: evolui `AuthenticatedFrame`; reutiliza `AppShell`, `BrandMark`, tokens e controles de ícone; introduz `ApplicationTopBar` e `UserAvatar` compartilhados.
**Integration and Contracts**: consome o nome de exibição da resposta de sessão existente descrita em [auth-api.md](../user-auth/contracts/auth-api.md); não altera payload, rota ou contrato.
**Telemetry**: N/A nesta entrega. Não registrar nome, imagem, estado de menu, preferência ou identificador de sessão.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-shell-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Casca ainda não tem sessão confirmada. | Nenhuma ação interna. | loading. |
| loading | Estrutura principal evita conteúdo pessoal até a sessão existir. | Nenhuma ação de usuário. | ready, remote-error ou access-denied. |
| empty | N/A — uma área autenticada sempre possui conteúdo principal. | N/A — motivo: não há estado vazio da casca. | N/A — motivo: não há estado vazio da casca. |
| ready | Barra, avatar e conteúdo autenticado estão visíveis. | Abrir menu pessoal; em telefone abrir navegação. | processing ou access-denied. |
| processing | Uma sobreposição estrutural abre ou fecha sem duplicar animação. | Fechar a sobreposição ativa. | ready ou access-denied. |
| success | N/A — a casca não conclui operação própria. | N/A — motivo: feedback pertence ao conteúdo ou menu. | N/A — motivo: feedback pertence ao conteúdo ou menu. |
| validation-error | N/A — não há formulário ou entrada livre nesta interação. | N/A — motivo: não há formulário ou entrada livre nesta interação. | N/A — motivo: não há formulário ou entrada livre nesta interação. |
| remote-error | Falha de carregamento da sessão usa o feedback existente, sem renderizar dados pessoais. | Retentar pela jornada existente quando disponível. | loading ou access-denied. |
| offline | Conteúdo local preservado; ações que dependem de rede são tratadas pelo conteúdo existente. | Abrir e fechar menus locais. | ready ao reconectar ou access-denied se sessão não for válida. |
| access-denied | Menus são fechados e a área pública substitui a casca. | Usar acesso público. | Jornada pública. |
| partial-stale | N/A — a casca não mantém cópia independente de sessão. | N/A — motivo: fonte é a sessão da aplicação. | N/A — motivo: fonte é a sessão da aplicação. |

### INT-WEB-SHELL-002 — Menu pessoal

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: concentrar ações pessoais globais sem misturá-las à futura navegação de produtos.
**Actors and Permissions**: usuário autenticado e validado; somente a própria área pessoal é exibida.
**Entry and Navigation**: aberto pelo avatar da barra, em qualquer largura. Fecha por Escape, acionador, clique/toque externo quando aplicável ou perda de sessão. A ação de saída segue o acesso público existente.
**Content and Data**: painel ancorado no avatar, com “Configurações do usuário” primeiro como destino reservado. A região inferior tem divisor e três ações por ícone: preferências visuais, idioma e sair. O item reservado não navega, não solicita dados e comunica indisponibilidade futura de modo não enganoso.
**Actions and Behavior**: preferências visuais e idioma reutilizam seus componentes e persistem somente suas escolhas locais existentes. Sair emite intenção para a aplicação raiz, que executa o encerramento de sessão existente. Ações internas não fecham a sessão; o menu pai só fecha quando seus controles devolvem o foco ou quando a pessoa o dispensa.
**Validation and Feedback**: item reservado não produz requisição. Preferências inválidas são normalizadas pelos controles existentes. Se a saída falhar, o menu permanece disponível e o feedback seguro é exibido pelo conteúdo autenticado; não limpar estado visual antes de confirmação do fluxo de saída.
**Responsive/Adaptive Behavior**: em desktop e tablet, alinha ao avatar sem ultrapassar a viewport. Em telefone, mantém largura legível e pode usar painel elevado acima do conteúdo, respeitando safe areas e teclado virtual. Os três utilitários inferiores nunca ficam ocultos por escala ampliada; podem rolar internamente se necessário.
**Accessibility**: papel de menu ou diálogo é aplicado conforme o contrato final do componente, com rótulo acessível “Menu pessoal”. Foco inicial vai para o primeiro item acionável; Escape fecha e devolve foco ao avatar. Tab não permite alcançar conteúdo visualmente bloqueado quando o painel é modal. Cada ícone tem rótulo textual acessível; o divisor não é a única indicação da separação. O item reservado informa que ainda não está disponível, sem simular link funcional.
**Localization**: traduz título, item reservado, estado de indisponibilidade, preferências, idioma e saída nos quatro idiomas. Bandeira não é a única identificação de idioma.
**Components and Design System**: introduz `UserMenu`; reutiliza `UserAvatar`, `VisualPreferencesPopover`, `LanguageSelector`, `UIRinoButton`, tokens de popup e estilos de menu compartilhados.
**Integration and Contracts**: a saída chama a intenção existente da aplicação, que usa o contrato de sessão em [auth-api.md](../user-auth/contracts/auth-api.md). Preferências e idioma continuam locais e não consomem API.
**Telemetry**: N/A nesta entrega. Não registrar escolha de preferência, idioma, nome, saída ou estado de menu.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-shell-002.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Avatar está disponível; menu fechado. | Abrir menu. | loading. |
| loading | N/A — não há leitura remota para abrir o painel. | N/A — motivo: abertura usa dados já carregados. | ready. |
| empty | Painel mostra item reservado e utilitários mesmo sem imagem de avatar. | Abrir controles e sair. | ready. |
| ready | Itens do menu estão visíveis e operáveis. | Preferências, idioma, saída, fechar. | processing, success, remote-error ou access-denied. |
| processing | Saída solicitada fica indisponível para repetição. | Aguardar conclusão; fechar somente se saída ainda não foi enviada. | success, remote-error ou access-denied. |
| success | Preferência ou idioma escolhido é anunciado pelo controle filho; saída concluída remove a casca. | Fechar ou continuar. | ready ou access-denied. |
| validation-error | Preferência local inválida é normalizada silenciosamente pelo controle filho. | Escolher valor válido. | ready. |
| remote-error | Saída falhou; painel e sessão permanecem visíveis com feedback seguro no conteúdo. | Tentar saída novamente ou fechar. | processing ou ready. |
| offline | Preferências e idioma locais permanecem operáveis; saída fica indisponível. | Alterar apresentação ou fechar. | ready ao reconectar. |
| access-denied | Sessão deixa de ser válida e o painel fecha. | Retornar ao acesso público. | Jornada pública. |
| partial-stale | N/A — menu não mantém dado remoto próprio. | N/A — motivo: depende do estado da sessão raiz. | N/A — motivo: depende do estado da sessão raiz. |

### INT-WEB-SHELL-003 — Navegação móvel

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: fornecer a estrutura lateral responsiva onde opções aprovadas de produtos poderão aparecer, sem sobrecarregar a barra em telefone.
**Actors and Permissions**: usuário autenticado e validado em tela estreita; não apresenta opções adicionais de autorização nesta fase.
**Entry and Navigation**: aberto pela marca paisagem na barra em tela estreita. Fecha por controle de fechar, Escape, acionador ou camada externa quando aplicável. Não muda rota, conteúdo nem sessão.
**Content and Data**: painel vindo da esquerda com marca paisagem em destaque, controle de fechar discreto ao lado e região de conteúdo intencionalmente sem itens de produto. “Navegação” é mantido apenas como rótulo acessível do painel, sem título visual redundante. Uma mensagem discreta informa que não há áreas adicionais disponíveis nesta fase, sem representar atalho ou promessa de produto.
**Actions and Behavior**: abrir e fechar preserva conteúdo principal. Somente um painel estrutural fica aberto; abrir o menu pessoal fecha este painel. Não há navegação, API, carregamento de módulos ou estado persistido.
**Validation and Feedback**: não há formulário. Se o layout não tiver largura suficiente, o painel reduz para largura segura sem exceder viewport. Sessão perdida fecha o painel antes da jornada pública.
**Responsive/Adaptive Behavior**: aparece somente até 639 px; tablet e desktop não mostram o acionador funcional de navegação. Painel ocupa largura suficiente para leitura, preserva margem de contexto e usa rolagem vertical própria quando necessário. Suporta toque, ponteiro e teclado físico; safe areas e teclado virtual não encobrem o controle de fechar.
**Accessibility**: painel recebe rótulo “Navegação”, foco inicial no controle de fechar e foco devolvido à marca ao fechar. Escape funciona; a camada externa tem comportamento equivalente e não é a única forma de fechar. Enquanto aberto, o conteúdo atrás não entra na ordem de foco. Transição é dispensável quando redução de movimento estiver ativa.
**Localization**: título, texto de ausência de destinos e rótulo de fechar estão disponíveis nos quatro idiomas. O nome de marca não é traduzido.
**Components and Design System**: introduz `MobileNavigationDrawer`; reutiliza `BrandMark`, `UIRinoButton`, tokens de sobreposição e estilos compartilhados de painel.
**Integration and Contracts**: não consome contrato externo; preserva o contrato de sessão já carregado pela casca.
**Telemetry**: N/A nesta entrega. Não registrar abertura, largura de tela, sessão ou conteúdo visual.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-shell-003.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Acionador móvel visível; painel fechado. | Abrir painel. | loading. |
| loading | N/A — painel não busca dados. | N/A — motivo: estrutura local. | ready. |
| empty | Painel aberto sem destinos de produto, com explicação discreta. | Fechar painel. | ready. |
| ready | Painel aberto e foco no controle de fechar. | Fechar por botão, Escape, acionador ou camada externa. | processing ou access-denied. |
| processing | Abertura ou fechamento em curso; impede duplicação visual. | Aguardar conclusão. | ready ou initial. |
| success | N/A — não há operação de negócio concluída. | N/A — motivo: não há operação de negócio. | N/A — motivo: não há operação de negócio. |
| validation-error | N/A — não há entrada de dados. | N/A — motivo: não há entrada de dados. | N/A — motivo: não há entrada de dados. |
| remote-error | N/A — não há dependência remota. | N/A — motivo: não há dependência remota. | N/A — motivo: não há dependência remota. |
| offline | Painel abre e fecha normalmente; não há ação de rede. | Abrir ou fechar. | ready. |
| access-denied | Painel fecha ao perder a sessão. | Continuar para acesso público. | Jornada pública. |
| partial-stale | N/A — não carrega lista remota de destinos. | N/A — motivo: não carrega lista remota de destinos. | N/A — motivo: não carrega lista remota de destinos. |

## Cross-Surface Rules

### Navigation and Parity

Somente a web responsiva está em escopo. Desktop e tablet usam a marca como identidade; telefone converte a mesma região em acionador de navegação. A navegação de produtos permanece sem itens até feature própria. Avatar é a única porta para o menu pessoal em todos os formatos.

### Shared Content and Terminology

Os termos canônicos são “Configurações do usuário”, “Preferências visuais”, “Idioma”, “Sair”, “Menu pessoal”, “Abrir navegação”, “Navegação” e “Fechar navegação”. Configurações do usuário é sempre apresentada como destino reservado nesta fase e não se comporta como perfil pronto.

### Shared Accessibility and Input

Controles operam por teclado, toque e leitor de tela; estado, seleção e indisponibilidade não dependem de ícone ou cor. Escalas ampliadas mantêm alvos tocáveis e usam rolagem natural. Sobreposições preservam foco previsível, Escape e retorno ao originador, e redução de movimento remove transição não essencial.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
| --- | --- | --- | --- | --- |
| INT-WEB-SHELL-001 | US-001, US-002 | FR-001–006, FR-013–015 | SC-001, SC-002, SC-004, SC-005 | ../user-auth/contracts/auth-api.md |
| INT-WEB-SHELL-002 | US-003 | FR-005, FR-007–010, FR-013–015 | SC-003–005 | ../user-auth/contracts/auth-api.md |
| INT-WEB-SHELL-003 | US-004 | FR-011–015 | SC-004, SC-005 | N/A — sem contrato novo |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
| --- | --- | --- | --- |
| INT-WEB-SHELL-001 | REQUIRED | wireframes/int-web-shell-001.md | Barra desktop e telefone. |
| INT-WEB-SHELL-002 | REQUIRED | wireframes/int-web-shell-002.md | Menu pessoal e utilitários inferiores. |
| INT-WEB-SHELL-003 | REQUIRED | wireframes/int-web-shell-003.md | Painel lateral móvel sem módulos. |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
