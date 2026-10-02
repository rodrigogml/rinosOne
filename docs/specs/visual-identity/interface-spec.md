# Interface Specification: Identidade Visual e Preferências de Interface

**Feature**: `visual-identity`
**Created**: 2026-09-23
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | WEB | Visitante e usuário validado | FULL | Entrada, criação de conta, confirmação, área autenticada, preferências visuais e seleção de idioma. | Sincronização de preferências com perfil e interfaces fora da web. |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
| --- | --- | --- | --- |
| SURF-WEB-ACCESS | Rota raiz e `resources/js/App.vue` | Inspeção local em 2026-09-23: cartão único com marca textual, descrição e barra de três modos. | Cadastro, senha e acesso sem senha são alternados dentro do mesmo cartão; não há logotipo, temas, idioma ou preferências de densidade. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-VIS-001 | SURF-WEB-ACCESS | SCREEN | MODIFIED | Entrada de acesso | Rota pública raiz e retorno após encerrar sessão |
| INT-WEB-VIS-002 | SURF-WEB-ACCESS | SCREEN | NEW | Criação de conta | Ação “Criar conta” na entrada |
| INT-WEB-VIS-003 | SURF-WEB-ACCESS | SCREEN | MODIFIED | Confirmação por e-mail | Continuação de cadastro ou acesso sem senha; link de e-mail |
| INT-WEB-VIS-004 | SURF-WEB-ACCESS | SCREEN | MODIFIED | Área autenticada | Conclusão de acesso autenticado |
| INT-WEB-VIS-005 | SURF-WEB-ACCESS | POPUP | NEW | Preferências visuais e idioma | Controles utilitários no rodapé das telas |

## Interaction Details

### INT-WEB-VIS-001 — Entrada de acesso

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: permitir acesso por senha ou sem senha de maneira clara, com identidade visual da plataforma e acesso às preferências de apresentação.

**Actors and Permissions**: visitante ou pessoa sem sessão válida; nenhuma ação requer autenticação.

**Entry and Navigation**: aberta na rota pública raiz e após encerrar a sessão. A ação “Criar conta” abre INT-WEB-VIS-002. Login por senha bem-sucedido abre INT-WEB-VIS-004; acesso sem senha abre INT-WEB-VIS-003.

**Content and Data**: fundo da aplicação; grupo vertical centralizado contendo logotipo paisagem sem cartão, cartão de acesso e linha final de utilitários. O logotipo tem largura de 80% do cartão e altura automática. O cartão contém heading “Acesse sua conta”, e-mail, senha, “Manter-me conectado”, ação principal e link “Criar conta”. Não exibe marca textual, descrição introdutória nem a barra de três modos anterior.

**Actions and Behavior**: senha vazia apresenta “Entrar sem senha” e solicita o fluxo existente por e-mail; qualquer valor de senha apresenta “Entrar” e executa o login por senha. “Criar conta” não reaproveita campos ou estado de senha da entrada. As preferências visuais e o idioma alteram a apresentação sem interromper campos seguros em edição.

**Validation and Feedback**: e-mail é validado antes da ação. Senha só é exigida para login por senha. Erros permanecem neutros, não expõem existência de conta e preservam e-mail, preferência e seleção de persistência; senha só permanece no controle ativo da página e não entra em preferências locais.

**Responsive/Adaptive Behavior**: desktop e tablet mantêm cartão de largura legível em coluna central; telefone usa largura disponível com margem de segurança. O conjunto logo + cartão fica centralizado verticalmente quando a altura permitir e passa a rolar sem recorte quando teclado virtual ou escala confortável reduzem o espaço. Ações têm área de toque mínima e permanecem acima do teclado quando em foco.

**Accessibility**: `main` único, heading de nível 1, rótulos persistentes, ordem de foco logo ignorado → heading → e-mail → senha → manter conectado → ação → criar conta → preferências → idioma. Mensagens usam região de status ou alerta; foco vai ao primeiro campo inválido. Ícones dos utilitários têm nome acessível.

**Localization**: usa chaves do catálogo para todos os rótulos e mensagens. Textos longos podem expandir em até duas linhas sem cortar controles. `lang` do documento acompanha o idioma ativo.

**Components and Design System**: `AppShell`, `BrandMark`, `UiCard`, `UiField`, `UiButton`, `UiAlert`, `VisualPreferencesPopover` e `LanguageSelector`; usa tokens semânticos e calculados, sem valores locais. O contrato visual e os exemplos de `UiButton` ficam no [guia vivo de Botões](/dev), para evitar padrões duplicados nesta spec.

**Integration and Contracts**: consome operações existentes de login por senha e solicitação de acesso sem senha descritas em [auth-api.md](../user-auth/contracts/auth-api.md). Não altera payloads, contratos nem a política de sessão.

**Telemetry**: N/A nesta entrega — não há capacidade de telemetria aprovada. Não registrar e-mail, senha, código, token, texto livre ou valores das preferências.

**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-vis-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Preferências restauradas e campo de e-mail pronto. | Informar e-mail, senha, persistência ou abrir utilitários. | empty ou ready. |
| loading | N/A — não há leitura inicial bloqueante. | N/A. | N/A. |
| empty | E-mail e senha vazios; ação sem senha visível. | Preencher ou criar conta. | ready. |
| ready | E-mail válido; ação reflete presença de senha. | Entrar, entrar sem senha ou criar conta. | processing ou INT-WEB-VIS-002. |
| processing | Ação ativa mostra progresso e bloqueia duplicação. | Alterar preferências; cancelar antes de envio efetivo. | success, validation-error, remote-error ou access-denied. |
| success | Confirmação segura de envio ou acesso concluído. | Continuar automaticamente. | INT-WEB-VIS-003 ou INT-WEB-VIS-004. |
| validation-error | Erro junto ao campo e resumo anunciado. | Corrigir e reenviar. | ready. |
| remote-error | Aviso recuperável sem dado sensível. | Tentar novamente. | processing ou ready. |
| offline | Aviso de conexão; submissões indisponíveis. | Ajustar preferências ou aguardar conexão. | ready ao reconectar. |
| access-denied | Mensagem neutra de credencial ou limite. | Tentar alternativa permitida. | ready. |
| partial-stale | N/A — não há dado remoto parcial. | N/A. | N/A. |

### INT-WEB-VIS-002 — Criação de conta

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: iniciar o cadastro em uma jornada própria e direta, sem as opções de login da entrada.

**Actors and Permissions**: visitante sem sessão; nenhuma permissão adicional.

**Entry and Navigation**: iniciada pelo link “Criar conta” em INT-WEB-VIS-001. Após emissão bem-sucedida segue para INT-WEB-VIS-003; voltar retorna à entrada sem transportar a senha eventualmente digitada antes.

**Content and Data**: mesma composição logo + cartão + utilitários. O cartão contém heading “Criar conta”, campo obrigatório “Nome de exibição”, campo obrigatório “E-mail”, ação “Criar conta” e link de retorno “Já tenho uma conta”.

**Actions and Behavior**: a ação valida os dois campos e inicia a emissão de validação existente. Nome e e-mail podem ser preservados durante alteração de tema ou idioma, quando seguros; nenhuma credencial é solicitada nesta tela.

**Validation and Feedback**: nome e e-mail obrigatórios, com erros associados e anunciados. A resposta pública continua neutra sobre existência prévia de e-mail. O botão bloqueia reenvio enquanto processa.

**Responsive/Adaptive Behavior**: aplica as mesmas regras de centralização e reflow da entrada; em telefone os campos e ações ficam em uma coluna com espaçamento derivado da preferência.

**Accessibility**: heading de nível 1, foco inicial no nome, sequência previsível entre campos, ação, retorno e utilitários. Erro leva o foco ao primeiro campo inválido e não apaga valores válidos.

**Localization**: todos os textos vêm do catálogo; o nome do idioma selecionado permanece legível junto da bandeira.

**Components and Design System**: mesmos componentes de INT-WEB-VIS-001, com `UiField` para nome e e-mail.

**Integration and Contracts**: consome a criação de cadastro descrita em [auth-api.md](../user-auth/contracts/auth-api.md), sem alteração de payload.

**Telemetry**: N/A nesta entrega — não há capacidade de telemetria aprovada. Não registrar nome, e-mail ou texto livre.

**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-vis-002.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Nome recebe foco e preferências estão aplicadas. | Preencher, retornar ou abrir utilitários. | empty ou ready. |
| loading | N/A — não há leitura bloqueante. | N/A. | N/A. |
| empty | Campos vazios e ação disponível após preenchimento. | Informar nome e e-mail. | ready. |
| ready | Campos válidos e ação habilitada. | Criar conta ou retornar. | processing ou INT-WEB-VIS-001. |
| processing | Ação mostra progresso; evita duplicação. | Alterar apresentação somente. | success, validation-error, remote-error ou access-denied. |
| success | Confirmação neutra da mensagem enviada. | Continuar. | INT-WEB-VIS-003. |
| validation-error | Erros por campo e resumo acessível. | Corrigir e enviar. | ready. |
| remote-error | Falha temporária sem exposição de conta. | Tentar novamente ou voltar. | processing ou INT-WEB-VIS-001. |
| offline | Aviso e ação de criação indisponível. | Ajustar preferências ou aguardar. | ready ao reconectar. |
| access-denied | Limite temporário apresentado de forma segura. | Aguardar ou voltar. | ready ou INT-WEB-VIS-001. |
| partial-stale | N/A — não há leitura parcial. | N/A. | N/A. |

### INT-WEB-VIS-003 — Confirmação por e-mail

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: concluir a validação ou acesso sem senha com a mesma fundação visual e os mesmos utilitários de preferência.

**Actors and Permissions**: visitante com uma emissão de confirmação; não exige sessão anterior.

**Entry and Navigation**: segue a emissão de cadastro ou acesso sem senha; links recebidos abrem a mesma jornada. Confirmação bem-sucedida chega a INT-WEB-VIS-004.

**Content and Data**: logo, cartão, heading contextual, instrução segura, contador textual, campo de código quando necessário, nome de exibição quando exigido pelo cadastro, ações de confirmar/reenvio e utilitários no rodapé. Não exibe token, identificador da emissão ou e-mail completo.

**Actions and Behavior**: confirmar código ou link usa os fluxos existentes. Alterar idioma ou preferências não consome, reemite ou invalida a emissão; código já digitado pode permanecer somente na memória da página e nunca é persistido.

**Validation and Feedback**: mantém validação de seis dígitos e regras existentes de erro, expiração, reenvio e tentativa. O aviso de expiração é anunciado uma vez, não a cada segundo.

**Responsive/Adaptive Behavior**: mesma coluna; o campo numérico admite teclado virtual e colagem. Contador e ação não ficam sob teclado virtual; escala ampla pode deslocar a centralização para rolagem natural.

**Accessibility**: heading contextual, foco no código após envio, leitores de tela não recebem anúncio contínuo do contador, retorno de foco ao campo após falha e operação integral por teclado.

**Localization**: traduz instruções, ações e mensagens sem traduzir valores de código ou dados de e-mail. A formatação do contador é própria de cada idioma.

**Components and Design System**: `AppShell`, `BrandMark`, `UiCard`, `UiField`, `UiButton`, `UiAlert`, contador textual, `VisualPreferencesPopover` e `LanguageSelector`.

**Integration and Contracts**: mantém as confirmações de código e link do [contrato de acesso](../user-auth/contracts/auth-api.md); não cria novo contrato.

**Telemetry**: N/A nesta entrega — não há capacidade de telemetria aprovada. Não registrar código, link, token, e-mail ou nome.

**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-vis-003.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Instrução e utilitários prontos. | Digitar código, abrir link ou reenviar. | ready ou loading. |
| loading | Link em verificação ou emissão sendo preparada. | Preferências e idioma permanecem disponíveis. | ready, success, remote-error ou access-denied. |
| empty | Código e nome, quando aplicável, ausentes. | Preencher campos. | ready. |
| ready | Dados válidos e confirmação disponível. | Confirmar ou reenviar. | processing. |
| processing | Evita confirmação duplicada. | Alterar apresentação; voltar somente antes de consumo. | success, validation-error, remote-error ou access-denied. |
| success | Confirmação concluída, sem segredos visíveis. | Continuar automaticamente. | INT-WEB-VIS-004. |
| validation-error | Erro junto ao campo e foco devolvido. | Corrigir ou reenviar quando permitido. | ready. |
| remote-error | Falha temporária com nova tentativa segura. | Tentar ou voltar. | processing ou INT-WEB-VIS-001. |
| offline | Confirmação e reenvio suspensos. | Manter entrada local e aguardar rede. | ready ao reconectar. |
| access-denied | Emissão vencida, usada ou bloqueada. | Solicitar nova emissão ou voltar. | INT-WEB-VIS-001 ou initial. |
| partial-stale | N/A — a emissão tem consumo único. | N/A. | N/A. |

### INT-WEB-VIS-004 — Área autenticada

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: apresentar o estado de acesso e ações já aprovadas, aplicando a fundação visual sem introduzir perfil ou módulos novos.

**Actors and Permissions**: usuário autenticado e validado; pode gerir somente a própria senha e sessões.

**Entry and Navigation**: destino de login ou confirmação bem-sucedida. Encerrar sessão retorna à entrada; a sessão perdida retorna ao mesmo destino público.

**Content and Data**: marca reduzida, heading de acesso, saudação com nome de exibição, grupos de senha e sessão, feedback, diálogo de confirmação para invalidar outras sessões e utilitários de preferências e idioma. O ícone reduzido pode aparecer como marca funcional, mas não substitui heading textual.

**Actions and Behavior**: mantém definição de senha, encerramento da sessão atual e invalidação das demais. Alterar apresentação não muda sessão, formulários ou confirmação aberta; o foco retorna à ação que abriu um diálogo quando ele é fechado.

**Validation and Feedback**: preserva as regras de senha e confirmações existentes. Estados destrutivos usam texto, ícone e cor sem depender apenas de cor.

**Responsive/Adaptive Behavior**: desktop agrupa as ações em cartões; tablet reduz colunas; telefone empilha tudo, inclusive diálogo em painel de largura disponível. Ações destrutivas permanecem separadas.

**Accessibility**: landmarks e headings distinguem senha e sessões; diálogo prende foco enquanto aberto e devolve foco ao originador. Controles globais ficam após o conteúdo principal na ordem de leitura.

**Localization**: todos os textos, inclusive diálogo, são chaves de idioma; nome de exibição é dado do usuário e não é traduzido.

**Components and Design System**: `AppShell`, `BrandMark`, `UiCard`, `UiField`, `UiButton`, `UiAlert`, diálogo reutilizável, `VisualPreferencesPopover` e `LanguageSelector`.

**Integration and Contracts**: mantém consulta e ações de sessão e senha do [contrato de acesso](../user-auth/contracts/auth-api.md).

**Telemetry**: N/A nesta entrega — não há capacidade de telemetria de interface aprovada. Não registrar preferências, sessão ou dados pessoais.

**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-vis-004.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Casca visual e marca prontas. | Nenhuma ação dependente de sessão. | loading. |
| loading | Esqueleto dos grupos de acesso. | Abrir utilitários de apresentação. | ready, remote-error ou access-denied. |
| empty | Sem senha definida; sessões sem detalhe adicional. | Definir senha ou encerrar sessão. | ready ou processing. |
| ready | Estado de senha e sessões carregado. | Definir senha, encerrar ou invalidar outras. | processing. |
| processing | Ação conflitante indisponível. | Alterar apresentação; cancelar diálogo antes de enviar. | success, validation-error, remote-error ou access-denied. |
| success | Feedback contextual anunciado. | Continuar. | ready ou INT-WEB-VIS-001. |
| validation-error | Requisito de senha associado ao campo. | Corrigir. | ready. |
| remote-error | Falha sem detalhes de sessão. | Tentar novamente ou cancelar. | processing ou ready. |
| offline | Ações de segurança indisponíveis. | Alterar apresentação ou aguardar rede. | ready ao reconectar. |
| access-denied | Sessão não é mais válida. | Retornar à entrada. | INT-WEB-VIS-001. |
| partial-stale | Estado de outras sessões pode ter mudado. | Atualizar ou cancelar. | loading ou ready. |

### INT-WEB-VIS-005 — Preferências visuais e idioma

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: oferecer controles reutilizáveis de apresentação sem adicionar uma tela ou interromper a jornada atual.

**Actors and Permissions**: visitante e usuário autenticado; sempre disponível, sem permissão adicional.

**Entry and Navigation**: dois botões de ícone ficam lado a lado e centralizados após os controles principais nas telas públicas e na região utilitária das demais telas. O primeiro, “Preferências visuais”, abre popup; o segundo abre o seletor de idioma. Escape, clique externo ou retorno ao botão originador fecham o popup sem alterar valores não selecionados.

**Content and Data**: o popup de preferências possui exatamente quatro linhas: tema com dois botões “Claro” e “Escuro”; densidade de texto com três botões “Compacta”, “Padrão” e “Confortável”; espaçamento com três botões “Compacto”, “Padrão” e “Confortável”; tamanho de elementos com três botões “Compacto”, “Padrão” e “Amplo”. O seletor de idioma apresenta a bandeira, nome e estado atual para português do Brasil, inglês, espanhol e francês.

**Actions and Behavior**: cada escolha é aplicada imediatamente, preserva as três dimensões restantes e é persistida após validação. Escolher idioma atualiza textos e atributo do documento sem trocar rota, autenticação ou dados seguros já digitados. O popup de idioma fecha após seleção; o de preferências pode permanecer aberto durante seleções e fecha por ação explícita ou perda de foco controlada.

**Validation and Feedback**: apenas valores pertencentes às listas fechadas são aceitos; configuração inválida é substituída pelo padrão sem alerta intrusivo. O estado selecionado é textual e semanticamente exposto, além do tratamento visual.

**Responsive/Adaptive Behavior**: em desktop, popups alinham-se ao botão e não ultrapassam a área visível; em telefone, tornam-se painel ancorado com largura disponível, respeitando safe areas e teclado. Os grupos de botões quebram apenas entre grupos, nunca sobrepondo rótulos.

**Accessibility**: cada acionador usa botão com nome acessível; popup usa papel e relações apropriadas, foco inicial no título e seleção atual, navegação por Tab e setas quando aplicável, Escape para fechar e retorno de foco ao acionador. Cada grupo anuncia rótulo e opção selecionada; bandeiras têm texto alternativo vazio quando o nome está adjacente para evitar repetição.

**Localization**: nomes de idiomas são exibidos no próprio idioma e, quando necessário, no idioma atual; rótulos das preferências são traduzidos. Nenhuma bandeira é a única forma de identificar idioma.

**Components and Design System**: `IconButton`, `VisualPreferencesPopover`, `SegmentedChoiceGroup` e `LanguageSelector`; todos reutilizáveis e baseados nos tokens do design system.

**Integration and Contracts**: usa somente o modelo local de preferências definido em [data-model.md](data-model.md). Não chama APIs nem lê dados de sessão.

**Telemetry**: N/A nesta entrega — não há capacidade de telemetria aprovada. Não registrar preferências, conteúdo de formulário, e-mail, senha, código ou identificadores de sessão.

**Wireframe Requirement**: OPTIONAL
**Wireframe**: wireframes/int-web-vis-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Botões de ícone disponíveis; popup fechado. | Abrir preferências ou idioma. | loading. |
| loading | Preferência local é validada rapidamente antes de abrir. | Nenhuma seleção até validar. | ready. |
| empty | N/A — sempre há valores padrão válidos. | N/A. | N/A. |
| ready | Popup mostra seleção atual e opções. | Selecionar, fechar ou trocar de utilitário. | processing ou initial. |
| processing | Aplicação imediata de uma preferência. | Evita clique duplicado na mesma opção. | success ou ready. |
| success | Opção selecionada é anunciada sem alerta intrusivo. | Continuar alterando ou fechar. | ready ou initial. |
| validation-error | Valor local inválido foi normalizado silenciosamente. | Selecionar opção válida. | ready. |
| remote-error | N/A — não há dependência remota. | N/A. | N/A. |
| offline | Preferências permanecem disponíveis. | Selecionar normalmente. | ready. |
| access-denied | N/A — não depende de autorização. | N/A. | N/A. |
| partial-stale | N/A — a fonte é local e atômica. | N/A. | N/A. |

## Cross-Surface Rules

### Navigation and Parity

Somente a web responsiva está em escopo. A entrada separa login e cadastro em telas próprias; confirmação e área autenticada mantêm seus fluxos de negócio atuais. Preferências e idioma não são uma rota e não podem mudar autenticação, contratos, emissão ou conteúdo sensível.

### Shared Content and Terminology

Os termos canônicos são “Entrar sem senha”, “Entrar”, “Criar conta”, “Manter-me conectado”, “Preferências visuais”, “Tema”, “Densidade do texto”, “Espaçamento” e “Tamanho dos elementos”. O seletor de idioma sempre exibe nome além de bandeira. Textos de credenciais e emissões não são traduzidos como dados nem incluídos em telemetria.

### Shared Accessibility and Input

Todos os controles suportam teclado, toque, leitor de tela e ampliação. Tema, idioma, foco, erro e seleção nunca dependem somente de cor, ícone ou bandeira. Contraste, área de toque e transições respeitam tokens e WCAG 2.2 AA; redução de movimento é respeitada. Escalas ampliadas priorizam reflow e rolagem natural em vez de cortar conteúdo.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
| --- | --- | --- | --- | --- |
| INT-WEB-VIS-001 | US-001, US-002, US-005 | FR-001–003, FR-007–009, FR-011, FR-013–015 | SC-001, SC-002, SC-005 | ../user-auth/contracts/auth-api.md |
| INT-WEB-VIS-002 | US-001, US-003, US-005 | FR-001, FR-004–009, FR-012–015 | SC-001, SC-002, SC-004, SC-005 | ../user-auth/contracts/auth-api.md |
| INT-WEB-VIS-003 | US-002–005 | FR-001–008, FR-014–015 | SC-001–005 | ../user-auth/contracts/auth-api.md |
| INT-WEB-VIS-004 | US-002–005 | FR-001–008, FR-010, FR-014–015 | SC-001–005 | ../user-auth/contracts/auth-api.md |
| INT-WEB-VIS-005 | US-002–005 | FR-002–008, FR-013–015 | SC-002–005 | data-model.md |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
| --- | --- | --- | --- |
| INT-WEB-VIS-001 | REQUIRED | wireframes/int-web-vis-001.md | Entrada e popup de preferências. |
| INT-WEB-VIS-002 | REQUIRED | wireframes/int-web-vis-002.md | Cadastro em jornada própria. |
| INT-WEB-VIS-003 | REQUIRED | wireframes/int-web-vis-003.md | Confirmação com utilitários globais. |
| INT-WEB-VIS-004 | REQUIRED | wireframes/int-web-vis-004.md | Área autenticada e ações de segurança. |
| INT-WEB-VIS-005 | OPTIONAL | wireframes/int-web-vis-001.md | Popup compartilhado ilustrado na entrada. |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
