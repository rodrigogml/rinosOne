# Interface Specification: User Profile

**Feature**: `user-profile`  
**Criada**: 2026-09-26  
**Status**: Planejada  
**Spec**: [spec.md](spec.md)  
**Plan**: [plan.md](plan.md)  
**Surface Catalog**: [Arquitetura das Superfícies](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | Web responsiva | Usuário autenticado e validado | FULL | Nome, avatar privado, recorte, substituição e remoção. | Perfil público, dados adicionais, drive, álbum, thumbnails e compartilhamento. |
| SURF-FUTURE-CONSUMERS | API | Consumidores autorizados futuros | DEFERRED | Contrato HTTP preserva a expansão. | Qualquer tela ou cliente adicional. |

## Current-State Evidence

| Surface ID | Rota, comando ou componente atual | Evidência | Comportamento atual |
| --- | --- | --- | --- |
| `SURF-WEB-ACCESS` | `resources/js/design-system/WorkspaceSettingsSurface.vue` | Janela de Configurações com lista lateral e seções Tema e aparência, Sessões e Segurança. | A casca de configuração é reutilizável; não há seção Perfil nem editor de avatar. |
| `SURF-WEB-ACCESS` | `resources/js/design-system/UserAvatar.vue` | Avatar já exibe imagem ou fallback de iniciais/interrogação na topbar. | A identidade visual existe, mas ainda não recebe avatar persistido por API. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-001 | SURF-WEB-ACCESS | SCREEN / seção | MODIFIED | Perfil nas Configurações do usuário | Menu pessoal → Configurações do usuário → Perfil. |
| INT-WEB-002 | SURF-WEB-ACCESS | DIALOG | NEW | Editor de avatar | Ação “Alterar imagem” da seção Perfil. |

## Interaction Details

### INT-WEB-001 — Perfil nas Configurações do usuário

**Surface**: SURF-WEB-ACCESS  
**Surface Type**: WEB  
**Change Type**: MODIFIED  
**Purpose**: permitir que a pessoa atualize seu nome e administre a imagem privada que representa sua identidade na plataforma.  
**Actors and Permissions**: somente o usuário autenticado e validado; toda operação é implicitamente limitada à sua própria conta.  
**Entry and Navigation**: o item “Perfil” torna-se a primeira opção textual da lista lateral da janela única “Configurações do usuário”. A troca entre seções não cria outra janela. O botão voltar do navegador preserva a área de trabalho; não deve desmontar uma janela ativa sem ação explícita da pessoa.

**Content and Data**:

1. A área lateral mantém o título “Configurações” e lista, nesta ordem: Perfil, Tema e aparência, Sessões e Segurança.
2. O painel direito inicia alinhado ao topo, sem centralização vertical quando houver espaço livre.
3. O bloco “Identidade” contém avatar circular de prévia, texto de estado (“Sem imagem” ou “Imagem atual”), ação primária “Adicionar imagem”/“Alterar imagem” e ação secundária destrutiva “Remover imagem”, presente somente quando há avatar.
4. O bloco “Nome” contém campo “Nome”, preenchido com `displayName`, ajuda curta e botão “Salvar alterações”.
5. Não são exibidos caminho físico, hash, tamanho de quota, detalhes técnicos, e-mail, senha ou dados de outra conta.

**Actions and Behavior**:

- Salvar nome envia somente o nome alterado; quando não há mudança, o botão permanece desabilitado.
- “Adicionar imagem” e “Alterar imagem” abrem `INT-WEB-002`.
- “Remover imagem” abre confirmação no escopo da janela de configurações: “Remover imagem de perfil?”; confirmar chama `DELETE /api/v1/profile/avatar`, cancelar preserva a imagem.
- Depois de salvar nome, substituir ou remover avatar, a topbar e qualquer avatar montado recebem o estado atualizado imediatamente, sem novo login.
- Ao mudar de seção com uma alteração de nome não salva, a pessoa recebe confirmação para descartar ou permanecer em Perfil. Não há confirmação quando não existem alterações pendentes.

**Validation and Feedback**:

- Nome vazio ou inválido recebe mensagem associada ao campo, sem apagar o valor digitado.
- O nome permanece editável durante erro remoto; botão “Tentar novamente” repete a última intenção segura.
- A remoção exibe confirmação inequívoca antes da operação e confirmação não intrusiva após sucesso; a ausência da imagem reativa o fallback de avatar.
- Mensagens não revelam caminhos, hashes, configurações de processamento ou detalhes internos.

**Responsive/Adaptive Behavior**:

- Desktop e tablet largo: navegação de configurações à esquerda e painel de Perfil à direita, usando a largura disponível da janela.
- Tablet estreito e telefone: a lista lateral torna-se uma faixa de seleção horizontal rolável ou um seletor acessível acima do painel; não há overflow horizontal do conteúdo.
- No telefone, o bloco de avatar empilha prévia, ações e texto; o bloco de nome ocupa largura integral. A janela conserva espaço para a topbar e para o seletor de superfícies móveis existente.
- Teclado virtual não deve esconder o campo Nome nem ações de confirmação; o painel interno, e não o canvas geral, pode rolar quando necessário.

**Accessibility**:

- A janela mantém `role=dialog`, rótulo “Configurações do usuário” e foco previsível. Ao entrar na seção, foco vai ao heading “Perfil” somente quando a mudança for iniciada por teclado; por ponteiro, o foco permanece no controle acionado.
- A navegação lateral usa botões com `aria-current` ou estado selecionado perceptível por texto, contraste e não apenas por cor.
- Avatar tem nome acessível “Imagem de perfil de {nome}”; no fallback, “Avatar de {nome} sem imagem”.
- Campos, erros e confirmação de remoção possuem relações `label`, `aria-describedby` e anúncio `aria-live` para resultado assíncrono.
- Todas as ações são alcançáveis por Tab/Shift+Tab; Enter salva o nome apenas quando o campo está válido e o foco não está em controle que tenha comportamento próprio. Alvos de toque respeitam os tokens mínimos do sistema; animações respeitam preferência de movimento reduzido.

**Localization**: todos os textos usam chaves de i18n para pt-BR, inglês, espanhol e francês. A mudança de idioma preserva o nome digitado, a imagem já selecionada e a seção Perfil aberta. Textos comportam expansão; não há formato de data exposto nesta tela.

**Components and Design System**: reutiliza `WorkspaceSettingsSurface`, `UserAvatar`, janela de aplicação, diálogo de confirmação, botões, campos, feedbacks e tokens de tema/densidade. Introduz apenas um painel reutilizável `ProfileSettingsPanel`; o controle de imagem é compartilhado com `INT-WEB-002`, não uma variante exclusiva da tela.

**Integration and Contracts**: consome `GET` e `PATCH /api/v1/profile`, `DELETE /api/v1/profile/avatar` e o recurso privado de avatar descritos em [profile-api.md](contracts/profile-api.md). Carrega ao abrir Perfil; o estado local só substitui a representação compartilhada depois de resposta bem-sucedida. Respostas antigas não podem sobrepor uma operação posterior.

**Telemetry**: registrar eventos técnicos sem dados pessoais: `profile_opened`, `profile_name_save_started`, `profile_name_save_succeeded`, `profile_name_save_failed`, `profile_avatar_remove_confirmed` e `profile_avatar_remove_failed`. Nunca registrar nome, arquivo, URL, hash, coordenadas, bytes ou imagem.

**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/int-web-001.md

**Estados**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | Placeholder estrutural curto no painel, sem limpar a navegação. | Trocar de seção. | Carregamento do perfil. |
| loading | Skeleton do avatar, campo e ações; navegação continua disponível. | Trocar de seção ou aguardar. | `ready`, `remote-error` ou `access-denied`. |
| empty | Avatar de fallback, texto “Sem imagem” e ação “Adicionar imagem”. | Editar nome, adicionar imagem. | `ready` ao selecionar ou concluir mudança. |
| ready | Dados atuais e ações apropriadas; nome sem alterações tem salvar desabilitado. | Salvar nome, adicionar/alterar/remover imagem, trocar seção. | `processing`, `validation-error` ou diálogo. |
| processing | Controle que iniciou a ação mostra progresso e bloqueia somente sua intenção duplicada. | Cancelar somente antes de envio; trocar de seção pede confirmação se houver edição pendente. | `success`, `validation-error` ou `remote-error`. |
| success | Notificação discreta e estado persistido. | Continuar editando. | Volta a `ready` ou `empty`. |
| validation-error | Erro próximo ao campo ou no diálogo, com dados preservados. | Corrigir e reenviar. | `processing` ou cancelamento. |
| remote-error | Mensagem neutra, dados locais preservados e tentativa disponível. | Tentar novamente, cancelar. | `processing` ou `ready`. |
| offline | Indicador de indisponibilidade; não tenta concluir silenciosamente. | Tentar novamente ao reconectar, cancelar. | `loading` ou `ready`. |
| access-denied | Mensagem de sessão indisponível, sem dados da conta. | Entrar novamente. | Fluxo de autenticação. |
| partial-stale | N/A — o painel recebe uma representação única e substitui-a somente após resposta completa. | N/A. | N/A. |

### INT-WEB-002 — Editor de avatar

**Surface**: SURF-WEB-ACCESS  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: permitir escolher uma imagem válida, ajustar posição e zoom em um recorte quadrado e confirmar a única imagem que será persistida.  
**Actors and Permissions**: usuário autenticado e validado, limitado à sua própria conta.  
**Entry and Navigation**: aberto por `INT-WEB-001`; é modal no escopo da janela de Configurações e não bloqueia taskbar, menu ou outras janelas. Fechar por Esc, botão Fechar ou Cancelar descarta a origem local e retorna o foco a “Adicionar imagem” ou “Alterar imagem”.

**Content and Data**:

1. Cabeçalho “Imagem de perfil”, descrição de formatos aceitos e limite de 10 MB.
2. Estado inicial com zona de seleção por botão e arrastar/soltar quando o dispositivo possuir ponteiro; o botão de seleção sempre está disponível.
3. Após seleção válida, prévia quadrada, imagem ajustável, indicação textual de zoom, botões “Reduzir zoom”, “Aumentar zoom”, “Centralizar”, “Cancelar” e “Salvar imagem”.
4. O recorte enviado corresponde a `image`, `cropX`, `cropY` e `cropSize`; a origem não é incluída em outra operação nem armazenada localmente além da vida do diálogo.

**Actions and Behavior**:

- Seleção aceita JPEG, PNG e WebP de até 10 MB; o cliente lê dimensões antes de abrir o editor.
- Arquivo menor que 400 px em qualquer lado é rejeitado antes do recorte, com opção de escolher outro.
- Arrastar reposiciona a imagem; botões e controles de teclado ajustam zoom em passos previsíveis. “Centralizar” reposiciona sem alterar a escala atual.
- Salvar fica disponível apenas com imagem e região válidas. Envia uma única solicitação; repetidos cliques não criam envio adicional.
- Sucesso fecha o diálogo e atualiza o avatar da seção/topbar. Cancelar, Esc ou falha não altera o avatar vigente.

**Validation and Feedback**:

- Cliente antecipa formato, 10 MB e dimensões, mas a mensagem explica que o servidor confirmará a imagem antes de salvar.
- Erros `AVATAR_FORMAT_UNSUPPORTED`, `AVATAR_TOO_LARGE`, `AVATAR_DIMENSIONS_TOO_SMALL` e `AVATAR_CROP_INVALID` são mapeados a mensagens específicas; `AVATAR_PROCESSING_UNAVAILABLE` informa indisponibilidade temporária e preserva a imagem no editor para nova tentativa.
- Falha de rede conserva imagem e enquadramento na memória enquanto o diálogo permanecer aberto.

**Responsive/Adaptive Behavior**:

- Desktop: diálogo central com área de edição quadrada e coluna/linha de controles; não supera a área visível da janela.
- Telefone: diálogo ocupa a maior parte da janela, respeita safe areas, usa prévia quadrada dimensionada pela menor largura disponível e empilha controles abaixo dela.
- Pointer: arrastar reposiciona. Teclado: setas deslocam a imagem; Shift+setas usa passo maior; `+`/`-` alteram zoom; Escape fecha somente quando não há envio em progresso. Toque: arrastar reposiciona e pinça ajusta zoom quando suportado, com botões equivalentes obrigatórios.

**Accessibility**:

- Foco inicial vai ao botão de seleção. Após escolher imagem, vai ao heading da área de recorte e anuncia controles e atalho de teclado.
- O diálogo prende o foco somente dentro de seus limites e o devolve ao acionador ao fechar. Durante processamento, não permite fechar acidentalmente; anuncia progresso e explica que o envio está em andamento.
- Canvas/prévia possui descrição textual do enquadramento; controles de zoom e posição têm nomes acessíveis e valores anunciáveis. Não exige precisão exclusiva de gesto.
- Contraste, foco visível, área de toque e movimento usam tokens do design system. O recorte não é comunicado somente pela cor.

**Localization**: formatos e unidades usam textos localizados; tamanho é apresentado com unidade adequada ao locale. Rótulos, atalhos e erros existem nos quatro idiomas. O termo canônico é “Imagem de perfil”, não “foto pública”.

**Components and Design System**: introduz `AvatarCropDialog` reutilizável, composto por diálogo da janela, `UserAvatar`, botões de ícone com texto acessível, controle de zoom e feedback de upload. A geometria do recorte fica em componente independente de Perfil para futuro uso autorizado.

**Integration and Contracts**: consome `POST /api/v1/profile/avatar` de [profile-api.md](contracts/profile-api.md). Dados só são considerados persistidos com `201`. A resposta atualiza o estado de Perfil; nenhuma URL de storage é exibida.

**Telemetry**: registrar `profile_avatar_editor_opened`, `profile_avatar_file_rejected` (somente categoria: formato/tamanho/dimensão), `profile_avatar_upload_started`, `profile_avatar_upload_succeeded`, `profile_avatar_upload_failed` e `profile_avatar_editor_cancelled`. Excluir nome do arquivo, imagem, coordenadas, URL, tamanho exato e conteúdo.

**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/int-web-002.md

**Estados**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | Seletor de arquivo e requisitos. | Escolher ou soltar imagem, cancelar. | `loading` para leitura local. |
| loading | Indicador curto enquanto lê metadados locais. | Cancelar leitura quando suportado. | `ready` ou `validation-error`. |
| empty | N/A — o estado inicial já representa ausência de origem. | N/A. | N/A. |
| ready | Prévia, recorte, controles e salvar. | Reposicionar, zoom, centralizar, trocar arquivo, cancelar, salvar. | `processing`, `validation-error` ou cancelar. |
| processing | Progresso de envio; imagem e enquadramento permanecem visíveis, controles impeditivos desabilitados. | Aguardar. | `success`, `validation-error`, `remote-error` ou `offline`. |
| success | Anúncio de imagem atualizada e fechamento automático. | N/A. | Retorna a `INT-WEB-001` em `ready`. |
| validation-error | Mensagem específica; imagem e recorte preservados quando possível. | Escolher outra, ajustar, reenviar. | `ready` ou `processing`. |
| remote-error | Mensagem neutra e reenvio disponível. | Tentar novamente, cancelar. | `processing` ou fechar. |
| offline | Estado de conexão indisponível com enquadramento preservado. | Tentar novamente após reconexão, cancelar. | `processing` ou fechar. |
| access-denied | Mensagem de sessão indisponível e encerramento seguro. | Entrar novamente. | Fluxo de autenticação. |
| partial-stale | N/A — não há versão parcial apresentada como avatar. | N/A. | N/A. |

## Cross-Surface Rules

### Navegação e paridade

Somente a web responsiva é entregue. O contrato HTTP não exige cliente futuro nem reproduz o editor em outra superfície. O Perfil continua dentro da janela única de configurações e não abre uma área de trabalho adicional.

### Conteúdo e terminologia

Os termos canônicos são “Perfil”, “Nome”, “Imagem de perfil”, “Adicionar imagem”, “Alterar imagem” e “Remover imagem”. Avatar é usado apenas em descrições técnicas ou acessíveis quando necessário. A imagem não é pública.

### Acessibilidade e entrada compartilhadas

Controles críticos têm alternativa de teclado e toque; mensagens assíncronas usam anúncio perceptível; diálogos respeitam foco e não impedem o uso de outras janelas fora do seu escopo.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
| --- | --- | --- | --- | --- |
| INT-WEB-001 | US-001, US-002, US-003 | FR-PROFILE-001, 002, 003, 007, 008, 009, 010, 011, 012, 013 | SC-PROFILE-001, 002, 003, 004 | [profile-api.md](contracts/profile-api.md) |
| INT-WEB-002 | US-002 | FR-PROFILE-004, 005, 006, 008, 010, 011, 012, 013 | SC-PROFILE-002, 004 | [profile-api.md](contracts/profile-api.md) |

## Wireframes

| Interaction ID | Requisito | Artefato | Observação |
| --- | --- | --- | --- |
| `INT-WEB-001` | REQUIRED | [wireframes/int-web-001.md](wireframes/int-web-001.md) | Perfil na janela de configurações. |
| `INT-WEB-002` | REQUIRED | [wireframes/int-web-002.md](wireframes/int-web-002.md) | Editor de recorte e estado móvel. |

## Validation Summary

- Matriz de cobertura revisada: sim.
- Todos os itens do inventário detalhados: sim.
- Estados canônicos resolvidos: sim.
- Wireframes obrigatórios presentes: sim.
- Requisitos de acessibilidade resolvidos: sim.
- Mapeamentos de contrato verificados: sim.
- Placeholders ou decisões abertas: 0.
