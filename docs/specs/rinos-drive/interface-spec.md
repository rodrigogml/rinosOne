# Interface Specification: Rinos Drive unificado

**Feature**: `rinos-drive`
**Atualizado**: 2026-09-30
**Superfície**: `SURF-WEB-DRIVE`
**Fonte funcional**: [Spec](spec.md) e [Plano](plan.md)

## Interface Coverage

| Surface ID | Surface Type | Atores | Cobertura | Incluído nesta entrega | Adiado ou excluído |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-DRIVE | WEB | Usuário autenticado, administrador e membro com acesso efetivo | FULL | Ferramenta única, catálogo, compartilhados, árvore lazy, lixeira por drive, dois painéis, transferência lógica e progresso. | Editor online, preview, links públicos, compartilhamento externo e gestão visual de concessões. |

## Estado atual e mudança desejada

O componente `resources/js/drive/DriveExplorer.vue` abre uma única superfície global e obtém o catálogo autorizado antes de carregar uma árvore. Cada painel conserva alvo, localização, seleção, carregamento e capabilities próprios por `DriveWorkspaceTarget`; árvore, coleção, upload, exportação e lixeira permanecem privados e tipados.

A mudança converte esse componente em casca global de instância única. O catálogo torna os alvos selecionáveis dentro da árvore; cada painel mantém estado independente e usa os contratos do Drive por alvo. Os destinos de menu Pessoal e Work são removidos e um único botão de ferramenta Drive é exibido ao lado dos avatares da topbar.

## Interaction Inventory

| Interaction ID | Surface ID | Type | Change Type | Name | Entry |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-DRIVE-001 | SURF-WEB-DRIVE | SCREEN | MODIFIED | Janela global e catálogo de drives | Botão Drive da topbar. |
| INT-WEB-DRIVE-002 | SURF-WEB-DRIVE | PANEL | MODIFIED | Painel de navegação por drive | Raiz, pasta, lixeira ou item do catálogo. |
| INT-WEB-DRIVE-003 | SURF-WEB-DRIVE | DIALOG | NEW | Dois painéis e confirmação de transferência | Botão de painel paralelo e drop entre painéis. |
| INT-WEB-DRIVE-004 | SURF-WEB-DRIVE | PANEL | NEW | Compartilhados comigo | Raiz virtual no catálogo. |
| INT-WEB-DRIVE-005 | SURF-WEB-DRIVE | PANEL | NEW | Progresso persistente de transferência | Faixa no painel, reabertura da janela e notificação segura. |

## Interaction Details

### INT-WEB-DRIVE-001 — Janela global e catálogo de drives

**Surface**: SURF-WEB-DRIVE
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: oferecer uma ferramenta única para todos os workspaces efetivamente acessíveis sem depender da organização ativa.
**Actors and Permissions**: qualquer usuário autenticado vê Meu Drive; Work aparece somente com acesso efetivo; Compartilhados comigo contém apenas concessões diretas.
**Entry and Navigation**: botão com ícone Drive na topbar, ao lado dos avatares. Abre uma única instância Rinos Drive; entradas duplicadas de menu pessoal e tenant são removidas. Fechar retorna o foco ao botão da topbar.
**Content and Data**: a barra de operações da coleção reúne Atualizar, Alternar painel, Nova pasta, Upload, Download e as ações sobre a seleção; não há barra de comandos separada. A janela não repete título, categoria ou identificação decorativa do workspace dentro do conteúdo. Cada raiz é um cabeçalho de acordeão com ícone e rótulo; clicar nela ativa/expande ou recolhe seu conteúdo. Pastas permanecem indentadas sob sua raiz, com expansão explícita, ícones de pasta aberta/fechada e linhas discretas de hierarquia. A lixeira é filha de cada drive e usa ícone vazio ou cheio conforme seu consumo.
**Actions and Behavior**: Atualizar recarrega o catálogo e apenas o painel afetado; clicar em uma raiz carrega a localização correspondente lazy, sempre descartando a localização, seleção, detalhes e dados transientes do drive anterior antes de consultar a raiz do novo alvo; alteração de tenant ativo em outro módulo não reinicia o Drive. O cabeçalho da coleção contém somente breadcrumbs, sem repetir a localização ao lado. Em desktop, o divisor entre árvore e coleção pode ser arrastado ou acionado por teclado em uma faixa efetiva até 25% da largura do painel correspondente. O botão de barra de navegação oculta/exibe a árvore sem fechar a janela.
**Validation and Feedback**: raiz removida por revogação recebe aviso seguro, sai da árvore e o painel volta ao catálogo; falha de catálogo mantém dados seguros já visíveis marcados como desatualizados e permite tentar novamente.
**Responsive/Adaptive Behavior**: desktop mostra árvore fixa à esquerda e painel principal; tablet reduz metadados; telefone abre a árvore em drawer modal e preserva a coleção como conteúdo principal.
**Accessibility**: janela tem heading único; catálogo é `tree` navegável por setas, Enter e Space; raiz ativa usa `aria-current`; drawer contém foco e devolve foco ao gatilho.
**Localization**: rótulos, categorias, estados e erros usam chaves `access.drive.*` nos quatro idiomas; nomes de organização e arquivo são dados, não chaves.
**Components and Design System**: reutiliza janela da Área de Trabalho, `UiButton`, `UiAlert`, tokens, ícones raster e árvore do Drive; adiciona `DriveCatalogTree` reutilizável.
**Integration and Contracts**: `GET /api/v1/drive/catalog`, leituras de alvo existentes e parsers em `driveWorkspaceApi.ts`.
**Telemetry**: `drive_opened`, `drive_catalog_loaded` e `drive_catalog_failed`, somente com categoria e resultado.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/drive-desktop.md

| Estado | Apresentação e saída |
| --- | --- |
| initial | Janela aberta sem catálogo; inicia leitura. |
| loading | Skeleton de raízes e controles desabilitados somente onde dependem do catálogo. |
| empty | Meu Drive disponível; nenhuma raiz Work ou compartilhamento elegível. |
| ready | Catálogo e primeiro painel disponíveis. |
| processing | Atualização em curso; painel atual preserva conteúdo seguro. |
| success | Anúncio discreto de catálogo atualizado. |
| validation-error | N/A — não há entrada livre do usuário. |
| remote-error | Alerta seguro com Tentar novamente; não revela drive potencial. |
| offline | Mantém dados em cache como desatualizados e desabilita novas leituras/mutações. |
| access-denied | Remove a raiz/painel afetado e anuncia perda de acesso. |
| partial-stale | Marca somente raízes/painéis que não puderam ser renovados. |

### INT-WEB-DRIVE-002 — Painel de navegação por drive

**Surface**: SURF-WEB-DRIVE
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: navegar e operar uma localização autorizada mantendo lixeira, quota e capabilities do drive selecionado.
**Actors and Permissions**: leitura permite navegar, detalhes, download e exportação; edição controla pasta, upload, lixeira e ações mutáveis.
**Entry and Navigation**: clique em raiz, pasta, lixeira, breadcrumb ou item de Compartilhados comigo. Voltar do navegador não altera a topbar; fecha apenas drawer/modal ativo antes da janela.
**Content and Data**: breadcrumb lógico destacado, toolbar de ícones sem contorno, modos grade/lista/detalhes, coleção, detalhes e uso do drive de origem. A raiz no breadcrumb usa o mesmo nome de catálogo apresentado na árvore; a lixeira é sempre filha visual e semântica da raiz real, nunca da raiz virtual. O uso é apresentado de forma compacta (`X?B utilizados`), truncado visualmente quando necessário e com o valor integral disponível no tooltip. O controle de segundo painel fica à esquerda; os comandos da coleção ficam agrupados à direita. A coleção ocupa toda a altura restante do painel e possui rolagem interna; a barra de status permanece fina e ancorada ao rodapé. Ela informa `X Itens (W Arquivos + Y Pastas)` e, havendo seleção, acrescenta quantidades selecionadas e tamanho lógico agregado — incluindo conteúdo descendente das pastas autorizadas. Progresso e mensagens descartáveis são anexados à mesma barra. O painel paralelo replica árvore por drives, breadcrumb, coleção, modos de exibição e status próprios, mas não replica a raiz virtual Compartilhados comigo. Depois de uma mutação concluída, os dois painéis renovam árvore, coleção, uso e status sem exigir atualização manual.
**Actions and Behavior**: os dois painéis possuem a mesma capacidade operacional, respeitando as capabilities da localização: criar, upload, baixar, exportar, mover intra-drive, lixar, restaurar, excluir definitivamente, detalhes e compartilhamento de pasta. O ícone do drive alterna somente sua árvore; o rótulo do drive seleciona sempre sua raiz. Um arquivo é baixado diretamente; uma pasta única ou múltiplos itens legíveis usam o mesmo comando de download para gerar ZIP temporário privado. A raiz virtual `Exportações`, identificada por `fileZipExport`, só é exibida após a primeira solicitação de exportação na janela e mostra os pacotes dessa sessão com estado localizado, geração e expiração; ela usa os mesmos modos de exibição e seleção, mas não aceita upload, criação, movimentação ou edição. Selecionar vários ZIPs prontos e acionar download busca e inicia cada arquivo sequencialmente, sem gerar outro pacote; o navegador pode solicitar a autorização normal para múltiplos downloads. Arrastar do painel esquerdo para o direito ou no sentido inverso abre a mesma confirmação de cópia/movimentação entre workspaces. Arquivo compartilhado diretamente oferece somente leitura, exportação e cópia para destino editável.
**Validation and Feedback**: capabilities vêm do servidor; comandos revalidam antes de executar. Nome inválido, lote inválido, conflito, reserva ativa ou revogação usam alerta seguro e preservam dados locais não enviados.
**Responsive/Adaptive Behavior**: desktop mantém árvore/coleção e detalhes em regiões internas; telefone usa drawers para árvore e detalhes, toolbar compacta e coleção com rolagem interna. Em largura restrita, ações secundárias passam para o menu de três pontos, sem deslocar a seleção, atualização ou navegação da barra.
**Accessibility**: foco chega ao heading da localização após navegação; itens possuem seleção por teclado, leitores de tela recebem contagem/estado e atalhos não atuam em campos de texto.
**Localization**: datas, tamanhos, plurais, ação e estado usam locale ativo; expansão de texto não pode ocultar controles.
**Components and Design System**: reutiliza `DriveExplorer`, `DriveDetailsPanel`, `DriveOperationDialog`, botões, alertas, tokens e ícones; extrai `DriveNavigationPane` para estado isolado por painel.
**Integration and Contracts**: leituras e comandos do prefixo de alvo, detalhes, upload, exportação e `GET /api/v1/drive/shared-with-me`.
**Telemetry**: categorias `drive_location_loaded`, `drive_action_completed`, `drive_access_revoked`; sem nome, id, caminho, tamanho exato ou conteúdo.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/drive-desktop.md

| Estado | Apresentação e saída |
| --- | --- |
| initial | Painel sem localização; solicita raiz escolhida. |
| loading | Mantém breadcrumb anterior seguro e usa skeleton na coleção. |
| empty | Explica que a localização não contém itens visíveis; mantém ações autorizadas. |
| ready | Coleção, toolbar e capabilities disponíveis. |
| processing | Upload, exportação ou comando local apresenta progresso por painel. |
| success | Anúncio da conclusão e atualização da coleção. |
| validation-error | Diálogo informa campo inválido, mantém texto selecionado e foco no primeiro erro. |
| remote-error | Alerta seguro, Retry e preservação de seleção válida. |
| offline | Dados cacheados ficam stale; upload e mutações são bloqueados. |
| access-denied | Limpa coleção, árvore e detalhes daquele ramo; retorna a uma raiz ainda autorizada. |
| partial-stale | Exibe aviso de dados possivelmente desatualizados e permite atualizar. |

### INT-WEB-DRIVE-003 — Dois painéis e confirmação de transferência

**Surface**: SURF-WEB-DRIVE
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: comparar duas localizações e iniciar cópia ou movimento consciente por drag-and-drop.
**Actors and Permissions**: requer leitura da origem e edição do destino; arquivo diretamente compartilhado só pode ser origem de cópia.
**Entry and Navigation**: botão Alternar painel cria ou fecha o segundo painel. Drop em pasta/root editável de outro painel abre diálogo modal local à janela. Esc cancela diálogo, não a operação já confirmada.
**Content and Data**: dois painéis com identificador de drive/localização, separados no desktop por divisor arrastável que permite redistribuir a largura sem alterar o estado de cada painel; cada painel tem árvore própria, expansível e redimensionável até 25% de sua largura. O diálogo apresenta origem e destino seguros, modo Copiar/Mover, quantidade em faixa e aviso de processamento em segundo plano entre drives.
**Actions and Behavior**: todo drop pede confirmação. Mesmo drive pré-seleciona Mover; drives diferentes pré-selecionam Copiar. Confirmar envia operação única; Cancelar não muda seleção. Ações mutáveis em ramo reservado recebem estado de operação em andamento.
**Validation and Feedback**: impede drop na própria pasta/descendente, seleção de múltiplas origens, destino sem edição e arquivo read-only em modo Mover. Erro não revela transferência concorrente nem item protegido.
**Responsive/Adaptive Behavior**: desktop/tablet largo mostra colunas lado a lado. Em telefone o segundo painel abre como modal quase integral e o usuário escolhe origem/destino com ação explícita; não há arrasto horizontal nem dois painéis comprimidos.
**Accessibility**: o divisor possui papel `separator`, nome acessível e responde a setas, Home e End; o diálogo tem radio group, foco inicial no modo pré-selecionado, descrição de consequência e retorno de foco ao item de origem.
**Localization**: verbos, modo sugerido, descrição de origem/destino e mensagens de reserva usam i18n; nomes de dados são interpolados com escape normal.
**Components and Design System**: adiciona `DriveNavigationPane`, `DriveTransferDialog` e affordances drag/drop com tokens de foco, seleção e estado; reutiliza modal local empilhável.
**Integration and Contracts**: `POST /api/v1/drive/transfers`, status/cancelamento de transferência e comandos intra-drive existentes.
**Telemetry**: `drive_transfer_confirmed`, `drive_transfer_cancelled`, `drive_drop_rejected`, com modo e categoria de origem/destino, sem nomes ou ids.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/drive-desktop.md

| Estado | Apresentação e saída |
| --- | --- |
| initial | Um painel; segundo painel ainda não solicitado. |
| loading | Segundo painel exibe skeleton independente. |
| empty | Destino vazio continua válido se houver edição. |
| ready | Dois painéis e destinos elegíveis disponíveis. |
| processing | Confirmação enviada; mostra operação em andamento e bloqueio contextual. |
| success | Diálogo fecha, painéis atualizam e progresso persiste em INT-WEB-DRIVE-005. |
| validation-error | Destino/origem inválido recebe mensagem junto ao controle correspondente. |
| remote-error | Diálogo mantém escolha e oferece reenviar quando seguro. |
| offline | Drop não inicia; explica que transferência requer conexão. |
| access-denied | Fecha diálogo, remove destino/origem revogado e anuncia alteração. |
| partial-stale | Exige atualizar o painel afetado antes de confirmar. |

### INT-WEB-DRIVE-004 — Compartilhados comigo

**Surface**: SURF-WEB-DRIVE
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: concentrar pastas e arquivos recebidos diretamente sem converter o drive de origem em espaço navegável.
**Actors and Permissions**: usuário que possua relação direta ativa `READ` a pasta ou arquivo.
**Entry and Navigation**: raiz Compartilhados comigo é a última do catálogo. Abrir pasta concedida inicia a navegação autorizada no alvo de origem; abrir arquivo seleciona detalhes seguros.
**Content and Data**: lista itens-raiz concedidos, origem em rótulo seguro e ações permitidas. Não apresenta quota, lixeira, breadcrumb de ancestrais ou irmãos de outro responsável.
**Actions and Behavior**: pasta recebida conserva capabilities efetivas; arquivo direto é somente leitura e pode copiar para destino editável. Um item já alcançável por pasta direta não reaparece duplicado.
**Validation and Feedback**: revogação remove o item na atualização ou ação seguinte. Ausência de itens apresenta vazio neutro, sem sugerir que exista acervo oculto.
**Responsive/Adaptive Behavior**: usa coleção e drawer de árvore existentes; origem aparece em linha secundária que reflowa abaixo do nome no telefone.
**Accessibility**: raiz tem nome acessível explícito; item identifica se é arquivo/pasta e origem sem depender de cor; ações indisponíveis não são focáveis.
**Localization**: o nome canônico é Compartilhados comigo e possui tradução nas quatro localidades.
**Components and Design System**: reutiliza coleção, item card/lista, ícone Drive e `DriveNavigationPane`; não cria lixeira virtual.
**Integration and Contracts**: `GET /api/v1/drive/shared-with-me`, detalhes/download/exportação/cópia do alvo de origem.
**Telemetry**: `drive_shared_opened`, `drive_shared_item_opened` e `drive_shared_revoked`, somente categoria/resultados.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/drive-desktop.md

| Estado | Apresentação e saída |
| --- | --- |
| initial | Raiz ainda não consultada. |
| loading | Skeleton de itens concedidos. |
| empty | Mensagem neutra de nenhum item compartilhado diretamente. |
| ready | Itens concedidos e ações seguras visíveis. |
| processing | Cópia/exportação iniciada mostra estado no item ou painel. |
| success | Anúncio de operação concluída sem expor origem indevida. |
| validation-error | N/A — raiz não aceita entrada de formulário. |
| remote-error | Retry seguro, sem informações de itens possíveis. |
| offline | Mantém lista conhecida marcada stale e bloqueia novas ações. |
| access-denied | Remove item revogado e informa perda de acesso de modo genérico. |
| partial-stale | Marca somente itens cuja atualização falhou. |

### INT-WEB-DRIVE-005 — Progresso persistente de transferência

**Surface**: SURF-WEB-DRIVE
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: tornar a transferência observável depois de a confirmação ter sido aceita, mesmo sem janela aberta.
**Actors and Permissions**: somente o solicitante autenticado consulta ou cancela operação ainda pendente.
**Entry and Navigation**: faixa de progresso aparece no painel de origem/destino após confirmação; reabrir Rinos Drive restaura operações próprias não terminais. Notificação não modal anuncia término.
**Content and Data**: modo, estado, contagem processada em relação ao total e erro categorizado; nunca lista nomes, paths, outra sessão ou detalhes de reserva.
**Actions and Behavior**: consultar estado com backoff enquanto pendente/processando; cancelar é permitido só antes do processamento. Conclusão atualiza ambos os painéis se ainda abertos.
**Validation and Feedback**: lease vencido ou recuperação não gera controle manual; estado terminal informa que a reserva foi liberada e permite atualizar os locais.
**Responsive/Adaptive Behavior**: desktop mostra faixa compacta acima da coleção; telefone a mostra em painel/modal de operações aberto pelo header, sem ocupar a taskbar ou reduzir a coleção permanentemente.
**Accessibility**: usa `role=status` com anúncio limitado de mudança de estado; progresso tem texto equivalente, não apenas barra visual; foco não é roubado por polling.
**Localization**: estados e plurais de contagem usam locale atual; erro técnico é convertido em mensagem de recuperação.
**Components and Design System**: adiciona `DriveTransferProgress`, reutiliza `UiAlert`, tokens de progresso e notificação não modal.
**Integration and Contracts**: `GET` e `POST cancel` de `/api/v1/drive/transfers/{transferId}`.
**Telemetry**: `drive_transfer_state_changed`, `drive_transfer_recovered`, `drive_transfer_failed`; apenas modo, estado e categoria.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/drive-mobile.md

| Estado | Apresentação e saída |
| --- | --- |
| initial | Não há operação própria restaurada. |
| loading | Consulta de estado discreta, sem bloquear navegação. |
| empty | Nenhuma operação ativa ou recente. |
| ready | Estado terminal conhecido e ações de atualizar locais disponíveis. |
| processing | Barra textual/visual com estado e contagem segura. |
| success | Notificação não modal e atualização de painéis abertos. |
| validation-error | N/A — somente cancelamento sem formulário. |
| remote-error | Mantém último estado seguro e tenta novamente com backoff. |
| offline | Interrompe polling, informa que a operação continua no servidor. |
| access-denied | Descarta a operação local se o solicitante não puder mais consultá-la. |
| partial-stale | Último estado é marcado desatualizado até nova consulta. |

## Responsive Rules

| Form factor | Catálogo e painel | Comparação | Detalhes e progresso |
| --- | --- | --- | --- |
| Desktop largo | Árvore e até dois painéis lado a lado, cada qual com rolagem interna e divisor redimensionável. | Drag-and-drop direto com alternativa por teclado. | Detalhes à direita; faixa compacta de progresso. |
| Tablet | Painéis podem dividir a largura mínima; metadados secundários refluem. | Mesmo diálogo; se largura insuficiente, segundo painel vira drawer. | Detalhes em drawer lateral. |
| Telefone | Uma coleção principal; árvore abre em drawer modal. | Segundo painel em modal quase integral e escolha explícita de destino. | Detalhes e operações em modal; safe areas e teclado virtual preservados. |

## Traceability

| Interaction ID | User Stories / FR | Success Criteria | Contract | Wireframe |
| --- | --- | --- | --- | --- |
| INT-WEB-DRIVE-001 | US-1, US-3; FR-DRIVE-001 a 005 | SC-DRIVE-001, 002, 006 | Catálogo e leitura por alvo | wireframes/drive-desktop.md |
| INT-WEB-DRIVE-002 | US-1, US-2, US-4, US-5; FR-DRIVE-005 a 028 | SC-DRIVE-001, 003 a 006 | Navegação, comandos, upload, download e exportação | wireframes/drive-desktop.md |
| INT-WEB-DRIVE-003 | US-7; FR-DRIVE-029 a 031, 034 e 035 | SC-DRIVE-001, 003, 006 e 007 | Transferências entre painéis | wireframes/drive-desktop.md |
| INT-WEB-DRIVE-004 | US-1; FR-DRIVE-032 e 033 | SC-DRIVE-001, 002 e 006 | Compartilhados comigo | wireframes/drive-desktop.md |
| INT-WEB-DRIVE-005 | US-7; FR-DRIVE-031, 034 e 036 | SC-DRIVE-001 e 007 | Status e cancelamento de transferência | wireframes/drive-mobile.md |

## Validation Summary

- `SURF-WEB-DRIVE` tem cobertura FULL e cinco interações detalhadas.
- Todos os estados canônicos, entradas, adaptação responsiva, teclado, toque, leitor de tela, localização e telemetria segura estão definidos.
- Wireframes desktop e telefone são obrigatórios e atualizados junto desta especificação.
- O contrato HTTP é a fonte de verdade de payloads; este documento descreve apenas seu consumo humano.
