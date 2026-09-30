# Interface Specification: Interface unificada de acessos e permissões

**Feature**: `access-permissions-interface`
**Created**: 2026-09-28
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|------------|------|-------|----------|----------------|----------------------------|
| SURF-WEB-ADMIN | WEB | Administradores autorizados de tenant e plataforma | FULL | Participantes, papéis, grupos, explicação, auditoria e controles avançados contextuais | Nenhuma capacidade aprovada é removida; controles sem autorização não expõem conteúdo. |
| SURF-WEB-SHARING | WEB | Responsáveis de workspace e administradores autorizados | FULL | Consulta, criação, alteração e revogação de compartilhamentos de recursos pessoais e de tenant | Transferência de propriedade e proprietário por item não existem. |
| SURF-WEB-ACCESS | WEB | Usuário autenticado | PARTIAL | Entrada contextual visível somente quando houver leitura ou administração autorizada | Não altera a casca autenticada, o seletor de tenant ou a navegação geral. |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|------------|---------------------------------------|----------|------------------|
| SURF-WEB-ADMIN | `tenant.authorization-administration` e `resources/js/authorization/AuthorizationAdministrationSurface.vue` | `resources/js/workspace/workspaceCatalog.ts` e `resources/js/design-system/WorkspaceStage.vue` | Tela de tenant com abas e formulários que recebem IDs técnicos; não há projeção unificada de sujeitos, papéis ou contexto. |
| SURF-WEB-SHARING | `resources/js/drive/DriveExplorer.vue`, `resources/js/authorization/ResourceSharingPanel.vue` e `routes/api/authenticated.php` | Ação “Compartilhar” no detalhe de pasta e rotas contextuais de relações | Drive abre um painel reutilizável de compartilhamento que recebe o recurso e o workspace tipado da origem, sem seletor livre de workspace. |
| SURF-WEB-ACCESS | `resources/js/design-system/WorkspaceShell.vue` | Área de Trabalho autenticada e catálogo de superfícies | Exibe superfícies abertas, sem uma entrada contextual comum para administração pessoal, tenant ou plataforma. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|----------------|------------|------|-------------|------|-------------|
| INT-WEB-ADMIN-001 | SURF-WEB-ADMIN | SCREEN | MODIFIED | Central contextual de acessos | Item “Usuários e acessos” no workspace de tenant, plataforma ou espaço pessoal permitido. |
| INT-WEB-SHARING-001 | SURF-WEB-SHARING | PANEL | NEW | Compartilhar recurso do workspace | Ação “Compartilhar” no painel de detalhes de item do Drive e link de recurso no detalhe de acesso. |
| INT-WEB-ACCESS-001 | SURF-WEB-ACCESS | NAVIGATION | MODIFIED | Entrada segura para acessos | Menu/contexto atual da Área de Trabalho. |

## Interaction Details

### INT-WEB-ADMIN-001 — Central contextual de acessos

**Surface**: SURF-WEB-ADMIN
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: Permitir que uma pessoa autorizada responda, no contexto ativo, “quem tem acesso?” e “o que esta pessoa pode fazer?”, concedendo ou removendo acesso sem usar IDs técnicos nem trocar de esfera acidentalmente.
**Actors and Permissions**: Usuário com capability de leitura ou administração no contexto. Botões e seções usam capabilities apenas para visibilidade; cada consulta e comando é reavaliado pela API.
**Entry and Navigation**: Abre na superfície do contexto ativo. O cabeçalho persistente apresenta ícone, nome e selo “Pessoal”, “Organização” ou “Plataforma”. Navegação primária: Pessoas e acessos, Papéis e grupos, Auditoria; “Avançado” é um agrupamento secundário. O retorno fecha a superfície e mantém o workspace que a originou.
**Content and Data**: Cabeçalho de contexto; resumo de capacidades administrativas; busca paginada de sujeitos; cartão de sujeito com fontes de acesso; catálogo de papéis e permissões; detalhe de acesso efetivo; auditoria filtrável; atalhos para controles avançados autorizados. Dados vêm das projeções `context`, `subjects`, `roles`, `permissions`, `effective-access` e `audit-events`.
**Actions and Behavior**: Pesquisar pessoa, abrir detalhe, atribuir/remover papel, administrar grupo, criar papel personalizado quando permitido, abrir explicação de capacidade, filtrar auditoria e navegar aos controles avançados. A primeira ação de concessão usa seletor de papel; concessão direta fica em “Avançado”. Toda escrita abre confirmação com alvo, contexto, efeito e vigência. Papéis `systemManaged` são somente leitura.
**Validation and Feedback**: Campos validam formato e obrigatoriedade no cliente; servidor decide compatibilidade, escopo, vigência e invariantes. `409` de último administrador explica a alternativa segura. `412` de versão/contexto desatualizado recarrega projeções e exige confirmação novamente. Erro preserva pesquisa e campos não sensíveis; sucesso anuncia a alteração e atualiza o cartão afetado.
**Responsive/Adaptive Behavior**: Em desktop, lista e detalhe usam duas colunas com painel lateral. Em tablet, o detalhe ocupa painel sobreposto. Em telefone, a navegação primária vira seletor, a lista ocupa tela inteira e o detalhe abre como página/painel de retorno explícito. Teclado físico, mouse e toque mantêm as mesmas ações; não há dependência de hover.
**Accessibility**: `main` com título do contexto; abas/seletores com nome acessível; busca com rótulo; listas semânticas; foco entra no título do detalhe e retorna ao invocador ao fechar; diálogo modal prende foco e tem descrição de impacto; resultados, sucesso e erro usam região `aria-live`; não se usa cor como única indicação de acesso, expiração ou risco.
**Localization**: Chaves Vue I18n sob domínio de autorização; nomes de escopo usam “Pessoal”, “Organização” e “Plataforma”; datas e vigências seguem locale e timezone do contexto; textos toleram expansão nas quatro localidades atuais.
**Components and Design System**: Reutiliza `WorkspaceStage`, `UiAlert`, `UiButton`, `UiCard`, `UiDialog`, `UiField` e navegação mobile existente. Novos componentes específicos são `AccessContextBanner`, `AccessSubjectList`, `EffectiveAccessDetail`, `RoleCatalogPanel` e `AuditFilterPanel`; todos usam tokens já existentes.
**Integration and Contracts**: [contextual-access-administration-api.md](contracts/contextual-access-administration-api.md), [administration-api.md](../authorization-administration/contracts/administration-api.md) e [advanced-policies-api.md](../advanced-authorization-policies/contracts/advanced-policies-api.md). O cliente contextual parseia payloads, conserva cache apenas durante a superfície aberta e reconsulta após escrita, expiração ou conflito.
**Telemetry**: Registrar abertura de seção, busca sem resultado, início/conclusão/falha de comando e motivo agregado de recusa. Nunca registrar nome, e-mail, identificador de sujeito, chaves de permissão, explicação completa, conteúdo de auditoria ou credenciais.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-admin-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|-------|-----------------------|-------------------|-----------------|
| initial | Mostra casca da superfície, contexto ainda sem dados e placeholders neutros. | Voltar ao workspace. | Inicia carga contextual. |
| loading | Skeleton da lista e do detalhe, com nome do contexto somente quando confirmado. | Cancelar/voltar; nenhuma escrita. | Vai para ready, empty, access-denied ou remote-error. |
| empty | Explica que não há sujeito, papel ou evento para o filtro atual, sem sugerir outro contexto. | Limpar filtro, criar item somente se permitido. | Nova busca ou criação bem-sucedida. |
| ready | Lista, detalhe e ações autorizadas exibem dados atuais. | Buscar, abrir, conceder, remover, filtrar, navegar. | Abre processamento, detalhe ou avançado. |
| processing | Botão acionado mostra progresso e previne reenvio; o resto da tela informa que a alteração está em andamento. | Cancelar somente antes de envio; leitura não conflitante. | success, validation-error, remote-error ou partial-stale. |
| success | Alerta não intrusivo anuncia ação concluída e cartão/projeção é atualizado. | Continuar a administrar ou desfazer somente quando a operação possuir comando explícito. | Retorna a ready. |
| validation-error | Erro associado ao campo e resumo acessível no topo; valores seguros permanecem. | Corrigir e reenviar. | processing ou ready ao cancelar. |
| remote-error | Alerta seguro sem detalhes internos; dados já carregados permanecem marcados quando ainda visíveis. | Repetir, recarregar ou voltar. | loading, ready ou offline. |
| offline | Faixa informa indisponibilidade; leituras em cache recebem marca “podem estar desatualizadas”. | Navegar dados já carregados e tentar reconectar; escrita indisponível. | Revalida ao voltar online. |
| access-denied | Mostra mensagem genérica de indisponibilidade da área e oferece retorno ao workspace seguro. | Voltar ao workspace. | Fecha a superfície; não mostra dados parciais. |
| partial-stale | Mostra dados anteriores com marca de atualização necessária, preservando pesquisa e seleção. | Recarregar; nenhuma escrita sobre dados obsoletos. | loading e depois ready, empty ou access-denied. |

### INT-WEB-SHARING-001 — Compartilhar recurso do workspace

**Surface**: SURF-WEB-SHARING
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: Conceder e revisar acesso limitado a um recurso de workspace pessoal ou de tenant, deixando explícita a abrangência e a origem do compartilhamento sem inventar propriedade de arquivo ou pasta.
**Actors and Permissions**: Responsável pelo workspace pessoal ou administrador autorizado do tenant/recurso. A API determina se pode consultar, criar, alterar ou revogar cada relação.
**Entry and Navigation**: Ação “Compartilhar” no painel de detalhes do Drive; pode ser aberta por um link de recurso dentro do detalhe de acesso. Fecha de volta ao item do Drive ou à Central contextual, preservando seleção e busca de origem.
**Content and Data**: Nome e tipo do recurso; contexto e responsável do workspace como informação; lista de relações diretas; lista de relações herdadas com origem; seletor de pessoa/identidade elegível; nível de acesso; data de vigência quando aplicável; aviso de impacto.
**Actions and Behavior**: Buscar destinatário, criar compartilhamento direto, alterar nível de relação direta, revogar relação direta e navegar à origem de uma relação herdada quando autorizada. Relação herdada não apresenta edição no descendente. A confirmação nomeia recurso, destinatário, contexto e nível. “Responsável pelo workspace” não é ação de transferência ou edição.
**Validation and Feedback**: Cliente exige destinatário e relação; servidor valida contexto, elegibilidade, compatibilidade, versão e herança. Se o destinatário não for visível no contexto, a busca não o revela. Em conflito, a lista é recarregada; em falha, os valores não sensíveis são preservados e nenhum compartilhamento parcial é mostrado como concluído.
**Responsive/Adaptive Behavior**: Em desktop, painel lateral de largura fixa preserva o Drive visível. Em tablet e telefone, abre página/painel de tela cheia com cabeçalho de retorno e ação primária fixa acima da área segura. Linhas longas quebram antes de esconder origem ou nível de acesso.
**Accessibility**: Painel tem título associado ao recurso, foco inicial no título e retorno à ação “Compartilhar”; cada relação identifica destinatário, nível e origem por texto; seletor é operável por teclado; diálogo de revogação descreve alvo e impacto; estados de busca e alteração são anunciados sem depender de cor.
**Localization**: Usa chaves i18n de Drive e autorização; termos canônicos são “responsável pelo workspace”, “acesso direto” e “acesso herdado”; datas são localizadas e o timezone é declarado quando houver vigência.
**Components and Design System**: Reutiliza painéis/detalhes do Drive, `UiDialog`, `UiField`, `UiButton`, `UiAlert` e avatares. Adiciona `ResourceSharingPanel`, `AccessRelationList` e `ShareRecipientPicker` sob módulos de autorização, sem replicar a árvore do Drive.
**Integration and Contracts**: [contextual-access-administration-api.md](contracts/contextual-access-administration-api.md), rotas de Drive em `routes/api/authenticated.php` e adaptador `auth_resource_relation`. O painel recebe descritor do recurso da tela origem, nunca um workspace escolhido pelo usuário.
**Telemetry**: Registrar abertura, tipo de ação, resultado agregado e se a relação era direta ou herdada. Excluir recurso, caminho, destinatário, relação concreta e qualquer conteúdo do arquivo.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-sharing-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|-------|-----------------------|-------------------|-----------------|
| initial | Painel abre com título do recurso e placeholders de relações. | Fechar/voltar. | Solicita compartilhamentos. |
| loading | Lista e seletor mostram skeleton, sem valores presumidos. | Fechar/voltar. | ready, empty, access-denied ou remote-error. |
| empty | Explica que não há compartilhamentos diretos; o responsável do workspace permanece apenas informativo. | Adicionar compartilhamento se permitido. | Abre formulário ou ready após criação. |
| ready | Lista diretas/herdadas e formulário de inclusão refletem a resposta atual. | Buscar destinatário, incluir, editar direta, revogar direta, abrir origem herdada. | processing, detalhe de origem ou fechamento. |
| processing | A relação alterada mostra progresso e o envio duplicado é bloqueado. | Cancelar antes do envio quando aplicável. | success, validation-error, remote-error ou partial-stale. |
| success | Anuncia inclusão, alteração ou revogação e atualiza somente a relação afetada. | Continuar ou fechar. | Retorna a ready ou empty. |
| validation-error | Campo de destinatário ou relação recebe mensagem associada; seleção segura é preservada. | Corrigir e reenviar. | processing ou ready. |
| remote-error | Mostra erro seguro e preserva dados carregados com marca quando necessário. | Repetir, recarregar ou fechar. | loading, ready ou offline. |
| offline | Explica que alterações não podem ser enviadas; relações em cache ficam marcadas como potencialmente desatualizadas. | Consultar cache, fechar e reconectar. | partial-stale ou loading ao reconectar. |
| access-denied | Remove conteúdo de relações e apresenta retorno seguro ao recurso. | Voltar ao Drive/origem. | Fecha painel. |
| partial-stale | Bloqueia edição sobre lista antiga e oferece atualização explícita. | Recarregar ou fechar. | loading e depois ready, empty ou access-denied. |

### INT-WEB-ACCESS-001 — Entrada segura para acessos

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: Oferecer uma entrada consistente para a Central contextual sem transformar a navegação em controle de autorização ou expor áreas de outro contexto.
**Actors and Permissions**: Todo usuário autenticado pode ver seu contexto atual; somente quem recebe capability de leitura, administração ou compartilhamento vê a entrada acionável correspondente.
**Entry and Navigation**: No menu do workspace atual, “Usuários e acessos” abre `INT-WEB-ADMIN-001`; no detalhe de recurso, “Compartilhar” abre `INT-WEB-SHARING-001`. A entrada é vinculada ao workspace/superfície aberta e não inclui seletor livre de tenant, pessoa ou plataforma.
**Content and Data**: Rótulo localizado, ícone de segurança, contexto atual e, quando útil, descrição curta da finalidade. Usa capabilities mínimas já projetadas para a casca; não carrega lista de sujeitos ou permissões antecipadamente.
**Actions and Behavior**: Abrir a Central, abrir compartilhamento de recurso, fechar/retornar e tratar capacidade revogada durante a sessão. Se a capability não existir, a opção não é apresentada; deep link ou superfície já aberta ainda é protegido pela API.
**Validation and Feedback**: A navegação valida se existe contexto ativo e apresenta mensagem segura se a surface não puder ser montada. A API determina acesso final; erro 403 encaminha ao workspace seguro sem informar qual capacidade faltou.
**Responsive/Adaptive Behavior**: Em desktop, a entrada fica no menu/atalho contextual da Área de Trabalho. Em tablet e telefone, fica no painel de navegação móvel ou no menu de detalhes do recurso, com alvo de toque compatível com o sistema de design.
**Accessibility**: Entrada possui nome acessível que inclui a finalidade e, quando necessário, o contexto; ordem de tabulação segue a navegação existente; abertura move foco para o título da nova superfície; retorno devolve foco ao item de origem.
**Localization**: Texto vem de Vue I18n e suporta os idiomas já disponíveis. O rótulo não usa abreviação técnica, nem inclui identificador de tenant ou chave de permissão.
**Components and Design System**: Reutiliza `WorkspaceMegaMenu`, `WorkspaceNavigationRail`, `WorkspaceMobileTaskPanel`, ícones e componentes de navegação existentes; não introduz uma segunda barra de navegação.
**Integration and Contracts**: Capabilities mínimas do contexto e [contextual-access-administration-api.md](contracts/contextual-access-administration-api.md). A entrada não mantém cache próprio de autorização e a superfície recarrega o contexto ao abrir.
**Telemetry**: Registrar apenas abertura solicitada, abertura concluída e indisponibilidade agregada por superfície; não registrar contexto, alvo, permission key ou dados de recurso.
**Wireframe Requirement**: OPTIONAL
**Wireframe**: N/A — extensão de entrada dentro de componentes de navegação existentes, cuja hierarquia é especificada pelas interações principais.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|-------|-----------------------|-------------------|-----------------|
| initial | Renderiza a casca autenticada antes da projeção de capability. | Navegação geral existente. | Carrega capability do contexto. |
| loading | Não mostra uma ação privilegiada até a capability ser conhecida. | Navegação geral existente. | ready, empty ou access-denied. |
| empty | N/A — ausência de capability resulta em entrada não exibida, sem estado vazio exposto. | Navegação geral existente. | Muda de contexto ou capability. |
| ready | Exibe a entrada contextual permitida com rótulo claro. | Abrir Central ou compartilhamento aplicável. | processing de montagem ou nova superfície. |
| processing | Indica abertura breve sem permitir acionamento repetido. | Voltar/cancelar navegação se ainda possível. | success, remote-error ou access-denied. |
| success | A superfície de destino recebe foco e carrega seu próprio estado. | Ações da superfície de destino. | Saída controlada pela nova superfície. |
| validation-error | N/A — não há formulário ou dado digitável nesta interação. | Navegação geral existente. | N/A — motivo declarado. |
| remote-error | Exibe mensagem segura de que a área não pôde ser aberta, sem informar capacidade ausente. | Tentar novamente ou continuar no workspace. | processing ou ready. |
| offline | Mantém ações que não exigem rede; entrada de acesso não é montada se o contexto não puder ser validado. | Navegação geral existente. | Revalida ao reconectar. |
| access-denied | Oculta ou fecha a entrada/superfície e mantém o usuário no workspace seguro. | Continuar no workspace. | Retorna a initial após mudança de contexto. |
| partial-stale | N/A — a casca não reutiliza capability antiga para abrir área de segurança. | Navegação geral existente. | Revalida antes de exibir a entrada. |

## Cross-Surface Rules

### Navigation and Parity

`INT-WEB-ADMIN-001` e `INT-WEB-SHARING-001` recebem seu contexto da origem e não apresentam troca livre de esfera. A Central pode levar ao painel de compartilhamento apenas quando o recurso já estiver contextualizado. O retorno sempre restaura a tela de origem; nenhum fluxo abre outro tenant ou espaço pessoal.

### Shared Content and Terminology

Os termos canônicos são “Usuários e acessos”, “Papel”, “Grupo”, “Acesso efetivo”, “Acesso direto”, “Acesso herdado”, “Responsável pelo workspace”, “Pessoal”, “Organização” e “Plataforma”. “Owner”, “dono do arquivo”, “proprietário da pasta” e “SCIM” não são usados como conceitos de interface.

### Shared Accessibility and Input

Toda alteração material exige confirmação acessível e mantém foco previsível. Listas e filtros podem ser operados por teclado e toque. Alertas de sucesso, erro, atualização necessária e estado offline usam texto e região viva; animações obedecem preferência de movimento reduzido.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|----------------|--------------|-------------------------|------------------|-----------|
| INT-WEB-ADMIN-001 | História 1, História 2, História 4, História 5 | FR-API-001 a FR-API-009, FR-API-012 a FR-API-018 | Administração contextual, explicação de acesso, último administrador, controles avançados e teclado | `contracts/contextual-access-administration-api.md`, contratos de administração e avançados existentes |
| INT-WEB-SHARING-001 | História 3, História 4 | FR-API-003, FR-API-010 a FR-API-012, FR-API-016 a FR-API-018 | Compartilhamento limitado, sem posse por item, confirmação e isolamento de contexto | `contracts/contextual-access-administration-api.md` |
| INT-WEB-ACCESS-001 | História 1, História 3 | FR-API-001 a FR-API-003, FR-API-017 a FR-API-018 | Entrada contextual, bloqueio entre esferas e navegação acessível | `contracts/contextual-access-administration-api.md` |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|----------------|-------------|----------|-------|
| INT-WEB-ADMIN-001 | REQUIRED | [int-web-admin-001.md](wireframes/int-web-admin-001.md) | Hierarquia da Central em desktop e telefone. |
| INT-WEB-SHARING-001 | REQUIRED | [int-web-sharing-001.md](wireframes/int-web-sharing-001.md) | Painel de compartilhamento e distinção entre direto/herdado. |
| INT-WEB-ACCESS-001 | OPTIONAL | N/A | Entrada reaproveita a arquitetura de navegação existente. |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
