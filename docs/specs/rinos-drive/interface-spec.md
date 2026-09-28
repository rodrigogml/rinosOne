# Interface Specification: Rinos Drive

**Feature**: `rinos-drive`  
**Criada em**: 2026-09-27  
**Status**: Draft  
**Spec**: [spec.md](spec.md)  
**Plan**: [plan.md](plan.md)  
**Surface Catalog**: [catálogo de superfícies](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Tipo | Usuários | Cobertura | Escopo incluído | Escopo adiado ou excluído |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-DRIVE | WEB | Usuário autenticado, administrador e membro autorizado de organização | FULL | Drive Pessoal e Work, navegação, árvore, upload, seleção, download, lixeira, exportação e detalhes. | Compartilhamento, gestão visual de relações, previews, thumbnails, edição de conteúdo, álbuns e links públicos. |

## Current-State Evidence

| Surface ID | Componente existente | Evidência | Comportamento atual |
| --- | --- | --- | --- |
| SURF-WEB-DRIVE | `WorkspaceFoldersSurface.vue` | `resources/js/workspace/WorkspaceFoldersSurface.vue` | Janela “Arquivos e anexos” lista somente pastas pessoais autorizadas, sem árvore, conteúdo, upload, downloads ou suporte Work. |

## Interaction Inventory

| Interaction ID | Surface ID | Tipo | Mudança | Nome | Ponto de entrada |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-DRIVE-001 | SURF-WEB-DRIVE | SCREEN | MODIFIED | Navegador de workspace | Destino “Arquivos” no menu pessoal; destino Work quando organização ativa. |
| INT-WEB-DRIVE-002 | SURF-WEB-DRIVE | DIALOG | NEW | Organização de pastas e lixeira | Toolbar, menu contextual ou atalho sobre item autorizado. |
| INT-WEB-DRIVE-003 | SURF-WEB-DRIVE | PANEL | NEW | Upload múltiplo | Botão Upload, área de soltar arquivos ou seletor nativo. |
| INT-WEB-DRIVE-004 | SURF-WEB-DRIVE | DIALOG | NEW | Exportação de seleção | Botão Baixar quando há dois ou mais itens selecionados. |
| INT-WEB-DRIVE-005 | SURF-WEB-DRIVE | PANEL | NEW | Detalhes e modos de visualização | Toolbar, teclado ou menu de item. |

## Interaction Details

### INT-WEB-DRIVE-001 — Navegador de workspace

**Surface**: SURF-WEB-DRIVE  
**Surface Type**: WEB  
**Change Type**: MODIFIED  
**Purpose**: permitir que uma pessoa encontre e trabalhe somente nos arquivos e pastas efetivamente acessíveis do Drive Pessoal ou Work.

**Actors and Permissions**: Drive Pessoal usa o workspace do usuário e pastas pessoais recebidas por relação. Drive Work exige organização ativa: administrador vê todo o acervo; membro vê apenas raízes com `READ` ou `EDIT`. Nenhuma ação é inferida pelo estado visual.

**Entry and Navigation**: o destino pessoal muda o rótulo para “Arquivos”, abre uma superfície de instância única e exibe “Rinos Drive Pessoal”. O destino Work só existe com tenant ativo, é instância única por tenant e exibe “Rinos Drive Work” com o nome da organização. Trocar ou encerrar o tenant fecha sua superfície Work, conforme o runtime da Área de Trabalho.

**Content and Data**: cabeçalho compacto com ícone raster Drive, nome contextual e ações; árvore lateral interna; breadcrumb; consulta limitada à localização atual; toolbar; coleção de pastas e arquivos; seleção; consumo disponível; lixeira; painel de detalhes opcional. A árvore de membro parcial contém somente raízes concedidas, sem irmãos ou raiz real não autorizada.

**Actions and Behavior**: abrir pasta por clique, Enter ou duplo clique; voltar por breadcrumb; abrir lixeira; selecionar um ou vários itens; atualizar a localização; abrir ações autorizadas. A seleção é descartada ao trocar de localização, contexto ou após atualização que invalide itens selecionados.

**Validation and Feedback**: carregamento anuncia localização; acesso revogado remove a localização, limpa seleção e retorna à raiz acessível ou ao estado vazio; erro preserva coleção anterior identificada como possivelmente desatualizada e oferece Atualizar.

**Responsive/Adaptive Behavior**: em desktop, árvore é coluna interna redimensionável e a coleção preenche o restante. Em tablet ou telefone, árvore abre drawer modal; o conteúdo permanece principal e a barra de ações pode quebrar em grupos “principal” e “mais”. Nenhum painel altera a altura do canvas ou remove a taskbar estrutural desktop.

**Accessibility**: landmark de navegação nomeado para árvore e região nomeada para coleção; árvore usa semântica hierárquica, setas para expandir/retrair e Enter para abrir; foco vai ao título da localização após troca; estados de acesso, seleção e atualização usam texto e `aria-live`; targets por toque respeitam tokens de componente.

**Localization**: rótulos, estados vazios, plural de itens selecionados, unidades de tamanho e datas usam i18n nos quatro idiomas; nomes de arquivo e pasta não são traduzidos; textos expansíveis não ocultam botões críticos.

**Components and Design System**: reutiliza moldura de janela, `WorkspaceSurfaceIcon`, botões e tokens. Introduz componentes reutilizáveis `DriveExplorer`, `DriveFolderTree`, `DriveBreadcrumbs`, `DriveToolbar`, `DriveItemCollection`, `DriveSelectionBar` e `DriveEmptyState`. O ícone Drive usa o catálogo raster central e suas variantes já aprovadas.

**Integration and Contracts**: consome leitura de árvore, localização e lixeira em [drive-workspace-api.md](contracts/drive-workspace-api.md); o parser valida a projeção antes de atualizar store local. Não persiste contexto ou seleção entre abas.

**Telemetry**: registrar somente categorias agregadas `drive_opened` (pessoal/work), `drive_location_loaded` (resultado/erro) e `drive_access_revoked`; excluir nome, caminho, id, tamanho, quantidade e conteúdo.

**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/drive-desktop.md  
**Responsive Wireframe**: wireframes/drive-mobile.md

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Saída |
| --- | --- | --- | --- |
| initial | Moldura e localização ainda sem dados. | Fechar janela. | loading. |
| loading | Skeleton de árvore e coleção, sem dados anteriores. | Fechar; cancelar somente requests ainda não enviadas. | empty, ready ou remote-error. |
| empty | Mensagem neutra: sem arquivos acessíveis ou sem acesso ao Work. | Atualizar; criar/enviar somente se a raiz tiver edição. | ready ou processing. |
| ready | Árvore, breadcrumb, coleção e ações conforme capabilities. | Navegar, selecionar, abrir ações. | loading, processing ou partial-stale. |
| processing | Ação local em curso identifica item/área sem bloquear outras janelas. | Aguardar; cancelar apenas ação cancelável. | success ou remote-error. |
| success | Notificação curta e coleção atualizada. | Continuar na localização. | ready. |
| validation-error | Mensagem junto ao controle inválido. | Corrigir e reenviar. | processing ou ready. |
| remote-error | Alerta seguro; coleção anterior fica marcada como desatualizada. | Atualizar ou navegar apenas em dados ainda válidos. | loading ou ready. |
| offline | Aviso de conectividade; nenhuma alteração ou download é iniciado. | Consultar dados existentes; tentar novamente ao reconectar. | partial-stale ou loading. |
| access-denied | Localização é removida sem nomear item negado. | Retornar à raiz acessível ou fechar. | empty ou ready. |
| partial-stale | Dados anteriores continuam visíveis com aviso. | Atualizar; cancelar seleção inválida. | ready ou remote-error. |

### INT-WEB-DRIVE-002 — Organização de pastas e lixeira

**Surface**: SURF-WEB-DRIVE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: criar, renomear, mover, enviar à lixeira, restaurar e limpar definitivamente itens autorizados sem operações parciais silenciosas.

**Actors and Permissions**: somente localização ou item com `edit`. Administrador Work tem essas capabilities integralmente; membro parcial somente no ramo concedido.

**Entry and Navigation**: Nova pasta abre diálogo local da instância. Renomear/mover/lixeira vêm do menu contextual, tecla de atalho documentada ou barra de seleção. Limpeza definitiva sempre abre confirmação explícita local; a pilha sobrevive quando o usuário alterna para outra janela.

**Content and Data**: diálogo de nova pasta/renomeação contém rótulo, nome, limite e mensagem de conflito; mover apresenta árvore filtrada para destinos válidos; confirmação de lixeira informa quantidade e prazo; limpeza definitiva informa irrecuperabilidade para aquele workspace.

**Actions and Behavior**: Enter confirma formulário válido; Escape fecha apenas diálogo dispensável; exclusão e limpeza de seleção mista são atômicas, recusando o conjunto inteiro se qualquer item não puder ser operado. Conflito gera nome automático e comunica o nome final.

**Validation and Feedback**: validação local não aceita vazio, espaços finais, nomes reservados ou comprimento excessivo; servidor é a autoridade para ciclo, conflito e permissão. A resposta atualiza árvore, localização, seleção e consumo.

**Responsive/Adaptive Behavior**: desktop usa diálogos locais compactos; mobile usa sheet modal com título, ação primária fixa e rolagem interna. A árvore para mover é filtro pesquisável, nunca um popup fora do viewport.

**Accessibility**: foco inicial no campo de nome ou primeira ação de confirmação; trap de foco no diálogo; retorno ao acionador; confirmação destrutiva exige rótulo explícito, não apenas cor ou ícone.

**Localization**: nomes de ações, pluralização, prazo e mensagens usam i18n. A confirmação traduzida não altera o nome original do item.

**Components and Design System**: introduz `DriveFolderDialog`, `DriveMoveDialog` e `DriveDestructiveConfirmation`, todos genéricos para futuros navegadores de workspace.

**Integration and Contracts**: consome comandos de organização e lixeira do contrato de Drive. Nenhuma relação de acesso é criada ou alterada por esta interação.

**Telemetry**: registrar categoria da ação e resultado, sem item, nome, destino, tenant ou seleção.

**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/drive-desktop.md

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Saída |
| --- | --- | --- | --- |
| initial | Diálogo fechado. | Abrir ação autorizada. | loading. |
| loading | Destinos válidos ou dados do item são consultados. | Fechar se dispensável. | ready ou remote-error. |
| empty | N/A — formulário ou confirmação sempre possui contexto. | N/A — motivo: ação depende de item/localização. | N/A — motivo: sem contexto não abre. |
| ready | Formulário ou confirmação pronta. | Confirmar, cancelar, pesquisar destino. | processing ou initial. |
| processing | Botão primário ocupado; duplicação bloqueada. | Aguardar. | success, validation-error ou remote-error. |
| success | Diálogo fecha e coleção anuncia resultado. | Continuar navegando. | INT-WEB-DRIVE-001 ready. |
| validation-error | Campo e mensagem identificam correção. | Corrigir. | ready. |
| remote-error | Mensagem segura no diálogo; valores são preservados. | Tentar novamente ou cancelar. | processing ou initial. |
| offline | Ação é indisponível; rascunho permanece local. | Cancelar; aguardar conexão. | ready. |
| access-denied | Diálogo fecha, seleção é atualizada. | Retornar ao navegador. | INT-WEB-DRIVE-001 ready/empty. |
| partial-stale | N/A — ação é revalidada antes do envio. | N/A — motivo: não opera dados antigos. | N/A — motivo: servidor decide. |

### INT-WEB-DRIVE-003 — Upload múltiplo

**Surface**: SURF-WEB-DRIVE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: enviar vários arquivos a uma localização editável com feedback individual e sem criar itens parcialmente ativos.

**Actors and Permissions**: usuário com `edit` na localização atual. O controle não aparece em localização somente leitura ou lixeira.

**Entry and Navigation**: botão Upload abre seletor nativo múltiplo; arrastar arquivos sobre a coleção revela zona de soltar acessível. A fila abre painel local não bloqueante na janela, permitindo navegação somente quando a localização de destino continuar válida.

**Content and Data**: cada linha mostra nome local, tamanho formatado, estado, percentual, nome final em caso de conflito e mensagem segura de falha. O navegador nunca apresenta caminho local completo ao restante da aplicação.

**Actions and Behavior**: iniciar lote valida limite prévio; cada arquivo tem resultado independente; cancelamento remove somente envio ainda não confirmado. Arquivo com nome ocupado recebe nome final seguro. Ao concluir, a localização de destino atualiza uma vez e preserva seleção não afetada.

**Validation and Feedback**: antes do envio, validar quantidade, tamanho e tipo declarados; o servidor confirma conteúdo, limite e autorização. Falhas de um arquivo não cancelam automaticamente outro arquivo válido, salvo limite de lote ou perda de autorização do destino.

**Responsive/Adaptive Behavior**: painel ocupa coluna lateral em desktop quando houver espaço e sheet inferior em mobile. A zona de soltar não é exigida em toque; botão sempre permanece disponível.

**Accessibility**: seletor nativo possui rótulo; fila anuncia início, conclusão e falha por item de forma não intrusiva; percentual não é a única indicação; cancelamento tem nome acessível com o arquivo visível ao usuário.

**Localization**: pluralização de fila, unidades de tamanho, percentual e erros usam i18n; nomes locais não são traduzidos nem enviados a telemetria.

**Components and Design System**: introduz `DriveUploadTrigger` e `DriveUploadQueue`, reutilizáveis em qualquer workspace; usa botões, alertas e tokens de progresso existentes.

**Integration and Contracts**: consome `POST uploads` e atualiza a localização por leitura contratada após conclusão. Usa estado local de fila, não sessão persistida.

**Telemetry**: registrar apenas quantidade em faixas, classe de resultado e tipo de falha; excluir nome, MIME exato, bytes, caminho e conteúdo.

**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/drive-desktop.md  
**Responsive Wireframe**: wireframes/drive-mobile.md

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Saída |
| --- | --- | --- | --- |
| initial | Nenhuma fila aberta. | Escolher ou soltar arquivos. | loading. |
| loading | Seletor/fila prepara validação local. | Cancelar escolha. | empty ou ready. |
| empty | Fila sem itens depois de cancelar ou concluir. | Escolher arquivos. | loading. |
| ready | Arquivos válidos aguardam início ou estão prontos. | Iniciar/cancelar por item. | processing. |
| processing | Progresso individual e total visíveis. | Cancelar pendentes. | success, validation-error, remote-error ou access-denied. |
| success | Resultado individual e atualização da coleção. | Fechar fila ou enviar mais. | empty/ready. |
| validation-error | Item recusado antes ou depois de envio; demais preservados. | Remover item; escolher outro. | ready. |
| remote-error | Item falho informa tentativa possível quando segura. | Repetir item ou remover. | processing/ready. |
| offline | Fila pausa e não inicia novos envios. | Cancelar; retomar ao reconectar. | processing/ready. |
| access-denied | Fila cessa para o destino; arquivos locais não são perdidos sem aviso. | Copiar lista; fechar. | INT-WEB-DRIVE-001 ready. |
| partial-stale | Destino mudou ou parte já concluiu. | Atualizar localização; revisar resultados. | ready. |

### INT-WEB-DRIVE-004 — Seleção e exportação

**Surface**: SURF-WEB-DRIVE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: permitir download simples ou exportação compactada de uma seleção integralmente autorizada.

**Actors and Permissions**: requer `read` em cada item. A seleção não oferece download se a coleção misturar item inacessível ou já removido.

**Entry and Navigation**: clique, Ctrl/Shift com ponteiro ou Space seleciona itens; a barra contextual informa contagem e ações. Um item baixa diretamente; dois ou mais abrem diálogo local de exportação com estado de preparação.

**Content and Data**: barra mostra contagem, limpar seleção, baixar, mover/lixeira apenas quando editável. Diálogo de exportação mostra quantidade, status, prazo de 60 minutos configurável e ação de cancelar; não mostra caminho, backend ou IDs internos.

**Actions and Behavior**: seleção em múltiplas localizações não é permitida nesta entrega. A exportação preserva a seleção ao trocar de janela; troca de tenant cancela a exportação Work ainda pendente nesta instância. Quando pronta, notificação oferece download; expiração remove a ação.

**Validation and Feedback**: o servidor revalida todos os itens no pedido, no job e no download. Limites excedidos retornam mensagem clara antes de disponibilizar pacote; seleção parcialmente autorizada é recusada integralmente.

**Responsive/Adaptive Behavior**: desktop mantém barra contextual junto à toolbar; mobile usa faixa fixa segura acima da borda inferior da superfície, respeitando safe area e teclado virtual. O diálogo ocupa largura segura e rola internamente.

**Accessibility**: seleção usa `aria-selected`, anúncio de contagem e atalhos que não disparam em entrada de texto; diálogo informa estado pronto/falho e devolve foco à barra; download não é iniciado sem comando explícito.

**Localization**: plural de seleção, estados de exportação, prazo e nomes de ação usam i18n; o nome do ZIP é localizado, mas os nomes internos preservam os originais.

**Components and Design System**: introduz `DriveSelectionBar` e `DriveExportDialog`; ambos são reutilizáveis por futuros workspaces. Reutiliza diálogos locais da janela e tokens de estado.

**Integration and Contracts**: consome download unitário e ciclo de exportação do contrato de Drive; polling é limitado ao diálogo/instância ativa e cessa em fechamento, cancelamento, prontidão ou expiração.

**Telemetry**: registrar somente tamanho de seleção em faixa, resultado e categoria de limite; excluir itens, nomes, paths, tenant e conteúdo.

**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/drive-desktop.md  
**Responsive Wireframe**: wireframes/drive-mobile.md

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Saída |
| --- | --- | --- | --- |
| initial | Sem seleção. | Selecionar itens. | ready. |
| loading | Revalidação inicial da seleção. | Cancelar diálogo. | ready ou access-denied. |
| empty | Seleção removida ou sem itens. | Voltar à coleção. | initial. |
| ready | Barra/detalhe mostra ações possíveis. | Baixar, limpar, abrir exportação. | processing. |
| processing | Exportação informa preparação sem bloquear a janela. | Cancelar quando pendente. | success, remote-error ou access-denied. |
| success | Pacote pronto e prazo informado. | Baixar; fechar. | ready/initial. |
| validation-error | Seleção ou limite inválido explica correção. | Ajustar seleção. | ready. |
| remote-error | Falha segura; pacote não existe. | Tentar de novo ou fechar. | ready. |
| offline | Não inicia nem baixa exportação. | Fechar; tentar após reconexão. | ready. |
| access-denied | Cancela fluxo e limpa seleção negada. | Retornar à coleção. | initial. |
| partial-stale | Parte dos itens mudou desde seleção. | Atualizar e selecionar novamente. | ready/initial. |

### INT-WEB-DRIVE-005 — Detalhes e modos de visualização

**Surface**: SURF-WEB-DRIVE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: adaptar a densidade de leitura da coleção e consultar informações seguras de um único item sem acionar conteúdo.

**Actors and Permissions**: todo usuário com `read` no item exibido. Ações de alteração no painel aparecem somente com `edit`.

**Entry and Navigation**: toolbar alterna grade, lista, detalhes e tabela; botão Detalhes abre painel contextual; Enter em item abre pasta, enquanto ação explícita abre detalhes. Fechar painel restaura foco ao acionador.

**Content and Data**: modos mostram nome, ícone de tipo, tamanho, data e localização conforme espaço. Painel inclui tipo detectado, extensão, versão atual quando permitida, datas, tamanho e metadados seguros disponíveis. Não exibe preview, thumbnail, hash, dispositivo completo, localização sensível ou bytes.

**Actions and Behavior**: preferência de modo é local ao navegador e aplicada a ambas as apresentações do Drive; não modifica dados do workspace. Painel troca de item sem desmontar a coleção e é fechado se o item perder acesso.

**Validation and Feedback**: parser recusa projeção incompleta; erro de detalhe preserva coleção e oferece nova tentativa. Dados de detalhe não são reutilizados após troca de localização ou contexto.

**Responsive/Adaptive Behavior**: desktop abre painel à direita; mobile usa drawer modal. Tabela pode ter rolagem horizontal somente dentro da coleção, com primeira coluna fixa e sem criar rolagem no canvas.

**Accessibility**: alternância é grupo de botões com estado selecionado; tabela possui cabeçalhos e ordenação futura não é simulada; painel tem heading, ordem de foco e fechamento por Escape quando não houver diálogo sobreposto.

**Localization**: datas, tamanhos, estados e rótulos usam locale ativo; textos longos quebram ou truncam visualmente com nome completo disponível de modo acessível.

**Components and Design System**: introduz `DriveViewModeControl`, `DriveDetailsPanel` e `DriveMetadataList`; todos usam tokens de densidade e tipografia já existentes.

**Integration and Contracts**: consome leitura de detalhes e a mesma projeção de item do contrato; preferência visual fica local e nunca integra payload de autorização.

**Telemetry**: N/A — mudança de visualização e consulta de metadados não serão registradas nesta entrega.

**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/drive-desktop.md  
**Responsive Wireframe**: wireframes/drive-mobile.md

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Saída |
| --- | --- | --- | --- |
| initial | Modo salvo localmente é aplicado; painel fechado. | Alternar modo; abrir item. | loading/ready. |
| loading | Painel exibe skeleton de metadados. | Fechar painel. | ready ou remote-error. |
| empty | N/A — detalhe exige item; coleção vazia é tratada pela interação principal. | N/A — motivo: sem item. | N/A — motivo: sem item. |
| ready | Modo e painel mostram projeções autorizadas. | Alternar, fechar, usar ação permitida. | loading/processing. |
| processing | N/A — alternância é local; ações delegam à interação correspondente. | N/A — motivo: não altera dados. | N/A — motivo: sem operação própria. |
| success | N/A — alternância tem efeito imediato local. | N/A — motivo: sem confirmação remota. | N/A — motivo: efeito imediato. |
| validation-error | N/A — não há entrada livre. | N/A — motivo: sem formulário. | N/A — motivo: sem formulário. |
| remote-error | Painel exibe erro seguro e botão para tentar novamente. | Tentar novamente; fechar. | loading/initial. |
| offline | Painel não solicita novo detalhe; indica dado ausente. | Fechar; usar coleção já carregada. | initial. |
| access-denied | Painel fecha e foco retorna à coleção. | Selecionar outro item. | initial. |
| partial-stale | Detalhe antigo não é mostrado como atual. | Atualizar localização. | loading/initial. |

## Regras Transversais

### Navegação e Paridade

Desktop e telefone oferecem as mesmas operações autorizadas, mas não a mesma composição. Desktop usa árvore interna e painel lateral; telefone usa drawers modais. A janela segue a política de instância única por alvo: uma pessoal e uma por organização. Nenhuma ação de Drive desloca menu, topbar ou taskbar estrutural.

### Conteúdo e Terminologia

Os nomes canônicos são “Rinos Drive”, “Rinos Drive Pessoal”, “Rinos Drive Work”, “Meus arquivos”, “Lixeira”, “Nova pasta”, “Upload”, “Baixar”, “Detalhes” e “Exportação”. “Arquivo” designa item de workspace; “exportação” designa ZIP temporário, nunca um item salvo pelo usuário.

### Acessibilidade e Entrada Compartilhadas

Teclado, toque, ponteiro e leitor de tela operam os fluxos essenciais. Ctrl/Shift auxiliam seleção somente quando não há controle editável; Escape fecha camada dispensável; comandos do browser e do shell mantêm precedência. Movimento reduzido remove animações decorativas sem ocultar transições de estado. Ícones não substituem rótulos acessíveis.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
| --- | --- | --- | --- | --- |
| INT-WEB-DRIVE-001 | US-1, US-3 | FR-DRIVE-001 a 006, 016 a 025, 027 | SC-DRIVE-001, 002, 006 | [drive-workspace-api.md](contracts/drive-workspace-api.md) |
| INT-WEB-DRIVE-002 | US-1, US-4 | FR-DRIVE-006, 009, 015, 019, 021, 024 | SC-DRIVE-001, 003 | [drive-workspace-api.md](contracts/drive-workspace-api.md) |
| INT-WEB-DRIVE-003 | US-2 | FR-DRIVE-007 a 009, 019, 021, 026, 027 | SC-DRIVE-001, 003, 005, 006 | [drive-workspace-api.md](contracts/drive-workspace-api.md) |
| INT-WEB-DRIVE-004 | US-2, US-6 | FR-DRIVE-010 a 014, 017, 026, 027 | SC-DRIVE-001, 004, 005, 006 | [drive-workspace-api.md](contracts/drive-workspace-api.md) |
| INT-WEB-DRIVE-005 | US-5 | FR-DRIVE-016, 017, 027, 028 | SC-DRIVE-005, 006 | [drive-workspace-api.md](contracts/drive-workspace-api.md) |

## Wireframes

| Interaction ID | Requisito | Artefato | Notas |
| --- | --- | --- | --- |
| INT-WEB-DRIVE-001 | REQUIRED | [drive-desktop.md](wireframes/drive-desktop.md), [drive-mobile.md](wireframes/drive-mobile.md) | Estrutura, árvore e responsividade. |
| INT-WEB-DRIVE-002 | REQUIRED | [drive-desktop.md](wireframes/drive-desktop.md) | Ações locais e organização. |
| INT-WEB-DRIVE-003 | REQUIRED | Ambos | Fila de upload e prioridade em mobile. |
| INT-WEB-DRIVE-004 | REQUIRED | Ambos | Seleção e exportação efêmera. |
| INT-WEB-DRIVE-005 | REQUIRED | Ambos | Painel de detalhes e modos. |

## Validation Summary

- Matriz de cobertura revisada: sim.
- Todos os itens de inventário detalhados: sim.
- Estados canônicos resolvidos: sim.
- Wireframes obrigatórios presentes: sim.
- Requisitos de acessibilidade resolvidos: sim.
- Mapeamentos de contrato verificados: sim.
- Placeholders ou decisões abertas: 0.
