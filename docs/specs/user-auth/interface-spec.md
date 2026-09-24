# Interface Specification: Acesso de Usuário

**Feature**: `user-auth`
**Created**: 2026-09-22
**Status**: Implemented
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

> [!IMPORTANT]
> As regras, estados e contratos de acesso deste documento permanecem vigentes. A composição visual e os controles globais foram substituídos pela feature [Identidade Visual e Preferências de Interface](../visual-identity/spec.md), que é a referência para marca, tema, idioma, densidades, componentes, layout e acessibilidade dos fluxos web.

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
| --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | WEB | Visitante e usuário validado | FULL | Cadastro, confirmação de e-mail, login por senha ou sem senha e controle de sessões | Perfil, recuperação ou alteração de senha depois de definida, segundo fator e outras superfícies |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
| --- | --- | --- | --- |
| SURF-WEB-ACCESS | `resources/js/App.vue`, rotas web e API versionada | Interface Vue, contratos JSON, testes de componente, feature e navegador. | Cadastro, confirmação, login por senha ou e-mail e controles de sessão estão implementados. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-001 | SURF-WEB-ACCESS | SCREEN | NEW | Entrada de acesso | Rota pública raiz e retorno de sessão encerrada |
| INT-WEB-002 | SURF-WEB-ACCESS | SCREEN | NEW | Confirmação por e-mail | Continuação após cadastro ou acesso sem senha; link aberto pelo e-mail |
| INT-WEB-003 | SURF-WEB-ACCESS | SCREEN | NEW | Segurança de acesso | Entrada após autenticação bem-sucedida |

## Interaction Details

### INT-WEB-001 — Entrada de acesso

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: permitir que visitantes iniciem o cadastro e que usuários existentes escolham entrar por senha ou solicitar acesso sem senha.
**Actors and Permissions**: visitante; usuário não autenticado; nenhuma ação exige sessão existente.
**Entry and Navigation**: rota pública raiz; após cadastro ou solicitação de acesso, encaminha para INT-WEB-002; após login por senha bem-sucedido, encaminha para INT-WEB-003.
**Content and Data**: logotipo acima do cartão, título do fluxo, campo de e-mail, campo de senha, caixa “Manter-me conectado”, ação principal dinâmica e mensagens neutras de retorno. Sem senha, a ação é “Entrar sem senha”; com senha, é “Entrar”. O link “Criar conta” abre a jornada própria de cadastro.
**Actions and Behavior**: criar conta solicita confirmação de e-mail; entrar com senha inicia sessão quando as credenciais forem válidas; acesso sem senha solicita uma mensagem com link e código e preserva o e-mail digitado e a escolha de persistência no fluxo seguinte.
**Validation and Feedback**: valida formato de e-mail antes do envio; apresenta requisitos de senha quando aplicável; respostas de cadastro e acesso sem senha permanecem neutras; erros de senha, limite e indisponibilidade preservam campos seguros e oferecem nova tentativa quando cabível.
**Responsive/Adaptive Behavior**: desktop centraliza o formulário em coluna com largura legível; tablet mantém a mesma coluna com margens reduzidas; telefone ocupa a largura disponível, mantém botões com altura mínima adequada ao toque e evita que o teclado virtual cubra a ação principal.
**Accessibility**: landmark principal, título de primeiro nível, rótulos persistentes, foco inicial no campo de e-mail, navegação integral por teclado, mensagens de erro em região de anúncio e contraste suficiente sem depender de cor.
**Localization**: português do Brasil, inglês, espanhol e francês; termos canônicos incluem “Criar conta”, “Entrar”, “Entrar sem senha”, “E-mail” e “Senha”. Mensagens não interpolam o e-mail completo em erros ou confirmações públicas.
**Components and Design System**: `AccessFrame`, `UiField`, `UiButton`, `UiAlert`, `VisualPreferencesPopover` e `LanguageSelector`, todos baseados em tokens centrais.
**Integration and Contracts**: consome as operações de iniciar cadastro, entrar por senha e solicitar acesso sem senha em [auth-api.md](contracts/auth-api.md); envia `rememberMe` quando selecionado e não armazena código, link ou senha fora do envio da ação.
**Telemetry**: N/A nesta fase. Não registrar e-mail, senha, código, link, token, nome de exibição ou texto livre.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Entrada pronta, com e-mail e senha vazios. | Informar credenciais, manter conexão ou abrir utilitários. | empty ou ready. |
| loading | N/A — não há leitura inicial necessária. | Nenhuma. | N/A. |
| empty | Campos vazios e ação sem senha visível. | Preencher e-mail, senha ou criar conta. | ready. |
| ready | E-mail válido; ação reflete a presença de senha. | Entrar, entrar sem senha ou criar conta. | processing. |
| processing | Ação enviada fica indisponível e indica processamento. | Cancelar somente antes do envio efetivo. | success, validation-error, remote-error ou access-denied. |
| success | Confirmação neutra de mensagem enviada ou sessão criada. | Continuar para confirmação ou segurança de acesso. | INT-WEB-002 ou INT-WEB-003. |
| validation-error | Erro associado ao campo e resumo acessível. | Corrigir e reenviar. | ready. |
| remote-error | Aviso sem dados sensíveis e ação “Tentar novamente”. | Tentar novamente ou criar conta. | processing ou ready. |
| offline | Aviso de indisponibilidade local; dados seguros permanecem no formulário. | Verificar conexão e tentar novamente. | ready ao recuperar conexão. |
| access-denied | Mensagem neutra para credencial inválida ou limite atingido. | Escolher alternativa permitida ou aguardar bloqueio. | ready. |
| partial-stale | N/A — não há dados remotos parciais nesta tela. | N/A. | N/A. |

### INT-WEB-002 — Confirmação por e-mail

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: permitir que a pessoa conclua a validação de e-mail ou o acesso sem senha por código, preservando a alternativa de abrir o link recebido em outra aba.
**Actors and Permissions**: visitante com uma mensagem temporária de validação ou acesso; não requer sessão anterior.
**Entry and Navigation**: aberta após INT-WEB-001 enviar cadastro ou acesso sem senha; o link do e-mail abre esta mesma jornada na nova aba. A confirmação de cadastro segue para nome de exibição e então INT-WEB-003; a confirmação de acesso sem senha segue diretamente para INT-WEB-003.
**Content and Data**: instrução de que uma mensagem foi enviada, campo de código, contador de validade de 10 minutos, indicação da escolha “Manter-me conectado” quando selecionada, ação de confirmar, reenvio condicionado aos limites e, para cadastro, campo obrigatório de nome de exibição após confirmação do código ou link.
**Actions and Behavior**: confirmar código consome a emissão; abrir o link consome a mesma emissão na aba de destino; o primeiro consumo válido invalida link e código; reenvio substitui a emissão anterior; a confirmação de cadastro exige nome de exibição antes de liberar o acesso; uma escolha prévia de persistência cria autenticação persistente ao concluir o acesso.
**Validation and Feedback**: código obrigatório e formato esperado; nome de exibição obrigatório no cadastro; código inválido, vencido, substituído ou já usado recebe mensagem segura; limite de reenvio informa indisponibilidade temporária sem revelar dados de conta.
**Responsive/Adaptive Behavior**: mesma coluna de leitura da entrada de acesso; código usa entrada otimizada para teclado físico e virtual, sem bloquear colagem; em telefone, contador, campos e ação permanecem visíveis acima do teclado virtual.
**Accessibility**: título anuncia a etapa atual; foco vai ao código após o envio e ao nome de exibição depois de uma confirmação de cadastro; contador não anuncia cada segundo; erro, sucesso e expiração são anunciados uma vez; reenvio informa o tempo de espera em texto.
**Localization**: português do Brasil; usa “Código de confirmação”, “Reenviar mensagem”, “Confirmar e-mail” e “Concluir acesso”. O código tem 6 dígitos numéricos e a emissão é invalidada após 3 erros por padrão; mensagens de expiração não expõem identificadores nem dados de conta.
**Components and Design System**: reutiliza `AccessFrame`, `UiCard`, `UiField`, `UiButton`, `UiAlert`, contador textual e os controles globais de apresentação.
**Integration and Contracts**: consome confirmações por código e por link para validação e acesso sem senha em [auth-api.md](contracts/auth-api.md); a rota web do link remove o segredo da URL antes de chamar a API e não o expõe à telemetria.
**Telemetry**: N/A nesta fase. Não registrar e-mail, código, link, token, nome de exibição ou texto livre.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-002.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Contexto de confirmação e instrução sobre e-mail enviado. | Digitar código, abrir link ou reenviar quando permitido. | ready ou loading para link. |
| loading | Link está sendo verificado ou emissão está sendo carregada. | Nenhuma ação de confirmação duplicada. | ready, success, remote-error ou access-denied. |
| empty | Código e, quando aplicável, nome de exibição ainda não foram informados. | Preencher campos. | ready. |
| ready | Dados obrigatórios válidos e confirmação disponível. | Confirmar ou reenviar. | processing. |
| processing | Confirmação ou reenvio em andamento; duplicação bloqueada. | Nenhuma, exceto voltar antes do consumo. | success, validation-error, remote-error ou access-denied. |
| success | Confirmação concluída; explica a próxima etapa sem revelar segredo. | Seguir para segurança de acesso. | INT-WEB-003. |
| validation-error | Código ou nome inválido com mensagem e foco no campo correspondente. | Corrigir, reenviar se permitido. | ready. |
| remote-error | Falha temporária com nova tentativa segura. | Tentar novamente ou voltar. | processing ou INT-WEB-001. |
| offline | Não tenta confirmar nem reenviar; informa necessidade de conexão. | Manter texto digitado e tentar após reconectar. | ready. |
| access-denied | Emissão vencida, substituída, usada ou bloqueada. | Solicitar nova emissão quando permitido ou voltar. | INT-WEB-001 ou initial. |
| partial-stale | N/A — a emissão é válida por confirmação única; não há leitura parcial aceitável. | N/A. | N/A. |

### INT-WEB-003 — Segurança de acesso

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: apresentar a conclusão do acesso e permitir definir a senha opcional, encerrar a sessão atual ou invalidar as demais sessões.
**Actors and Permissions**: usuário autenticado e validado; somente a própria conta pode acessar e executar as ações.
**Entry and Navigation**: destino após login ou confirmação bem-sucedida; pode ser revisitada enquanto a sessão estiver ativa. Encerrar a sessão atual retorna para INT-WEB-001.
**Content and Data**: confirmação de acesso, estado “senha definida” ou “senha não definida”, indicação de autenticação persistente quando aplicável, formulário de senha quando ausente, ação de encerrar sessão atual e ação separada de invalidar as demais sessões com confirmação explícita.
**Actions and Behavior**: definir senha valida os critérios e atualiza o estado; encerrar a sessão atual encerra imediatamente o acesso naquele navegador; invalidar as demais sessões mantém somente a atual e a autenticação persistente associada a ela, quando houver, e revoga as demais; uma sessão persistente perdida no servidor é reconstruída automaticamente ao retornar à web.
**Validation and Feedback**: senha exibe critérios e erro específico de força sem registrar seu conteúdo; invalidação das demais sessões exige confirmação; erros remotos permitem nova tentativa sem declarar quais dispositivos ou sessões existem.
**Responsive/Adaptive Behavior**: desktop organiza ações de segurança em grupos claros; tablet e telefone empilham grupos e mantêm ações destrutivas separadas visualmente; confirmação é modal em desktop e painel de largura total em telefone.
**Accessibility**: heading e landmarks distinguem senha e sessões; ação destrutiva recebe nome explícito, confirmação prende o foco e o devolve à ação original; alertas são anunciados; todos os controles atendem teclado, zoom e toque.
**Localization**: português do Brasil, inglês, espanhol e francês; termos canônicos são “Definir senha”, “Encerrar esta sessão” e “Invalidar outras sessões”; mensagens distinguem a sessão atual das demais sem identificar dispositivos.
**Components and Design System**: reutiliza `AuthenticatedFrame`, `UiCard`, `UiField`, `UiButton`, `UiAlert`, `UiDialog` e os controles globais de apresentação.
**Integration and Contracts**: consome a leitura da sessão atual, definição de senha e encerramento de sessões em [auth-api.md](contracts/auth-api.md); não mantém estado local após encerramento ou invalidação que exija nova leitura.
**Telemetry**: N/A nesta fase. Não registrar senha, identificadores de sessão, dados de dispositivo ou conteúdo de formulário.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-003.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Tela exibida após autenticação, antes de carregar estado de segurança. | Nenhuma ação dependente de estado. | loading. |
| loading | Esqueleto de grupos de acesso e sessões. | Encerrar sessão atual permanece disponível somente após estado pronto. | ready, remote-error ou access-denied. |
| empty | Sem senha definida e nenhuma outra sessão conhecida; oferece definição de senha. | Definir senha, encerrar sessão atual. | ready ou processing. |
| ready | Estado de senha e ações de sessão visíveis. | Definir senha quando ausente, encerrar atual, invalidar demais. | processing. |
| processing | Ação em andamento; controles conflitantes indisponíveis. | Cancelar confirmação antes de enviar. | success, validation-error, remote-error ou access-denied. |
| success | Confirmação contextual da senha ou das sessões atualizadas. | Continuar usando a sessão. | ready ou INT-WEB-001 após encerramento atual. |
| validation-error | Requisitos de senha não atendidos em resumo e junto ao campo. | Corrigir senha. | ready. |
| remote-error | Falha sem detalhes de sessão; mantém a intenção segura para tentar novamente. | Tentar novamente ou cancelar. | processing ou ready. |
| offline | Ações de alteração indisponíveis com aviso de conexão. | Voltar ou aguardar reconexão. | ready. |
| access-denied | Sessão atual já não é válida. | Retornar para entrada de acesso. | INT-WEB-001. |
| partial-stale | Estado de outras sessões pode ter mudado; ação exige atualização antes de confirmar. | Atualizar ou cancelar. | loading ou ready. |

## Cross-Surface Rules

### Navigation and Parity

Somente a web possui cobertura nesta fase. INT-WEB-001 inicia o cadastro ou login; INT-WEB-002 resolve link ou código; INT-WEB-003 é o destino autenticado e concentra as únicas ações permitidas após o acesso. Não há paridade implícita com superfícies futuras.

### Shared Content and Terminology

“Conta validada” é a conta com e-mail confirmado e nome de exibição informado. “Link mágico” e “código” são meios alternativos da mesma emissão; o uso de um invalida ambos. “Outras sessões” nunca inclui a sessão que realizou a ação nem sua autenticação persistente associada, quando houver.

### Shared Accessibility and Input

As três interações devem ser operáveis por teclado e toque, usar rótulos persistentes, manter foco previsível e anunciar mudanças de estado sem expor dados sensíveis. Mensagens não usam apenas cor e permanecem compreensíveis com zoom de navegador.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
| --- | --- | --- | --- | --- |
| INT-WEB-001 | US-001, US-002, US-003 | FR-001–004, FR-008–010, FR-014–015, FR-020, FR-023 | SC-001, SC-004 | contracts/auth-api.md |
| INT-WEB-002 | US-001, US-002 | FR-004–006, FR-009–015, FR-022–025 | SC-001, SC-002, SC-004 | contracts/auth-api.md |
| INT-WEB-003 | US-003, US-004 | FR-007–008, FR-016–020, FR-024–026 | SC-003, SC-004 | contracts/auth-api.md |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
| --- | --- | --- |
| INT-WEB-001 | REQUIRED | wireframes/int-web-001.md | Entrada pública e escolha de jornada. |
| INT-WEB-002 | REQUIRED | wireframes/int-web-002.md | Código, link e ativação da conta. |
| INT-WEB-003 | REQUIRED | wireframes/int-web-003.md | Senha opcional e controle de sessões. |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
