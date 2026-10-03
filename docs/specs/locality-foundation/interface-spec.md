# Interface Specification: Fundação de Localidades — Rotina IBGE no Hub

**Feature**: `locality-foundation`
**Created**: 2026-09-27
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [../../architecture/interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-MAINTENANCE | WEB | Administradores da Plataforma com permissão de leitura da rotina | PARTIAL | Exibir a rotina territorial IBGE na lista e no detalhe existentes, com estado, agenda e histórico; não exibir ação manual. | Nova tela de catálogo de localidades, remoção/reativação administrativa, edição de agenda e tela consumidora de CEP. |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
| --- | --- | --- | --- |
| SURF-WEB-MAINTENANCE | `resources/js/maintenance/MaintenanceHubSurface.vue`; `/api/v1/platform/maintenance/routines` | Lista filtrável, detalhe responsivo, estados de carga/erro/offline, histórico e ação condicionada a `canSynchronize`. | Exibe somente rotinas que o Hub devolver ao principal autorizado; o botão manual é renderizado apenas quando `capabilities.canSynchronize` é verdadeiro. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-MAINTENANCE-001 | SURF-WEB-MAINTENANCE | SCREEN | MODIFIED | Consulta da rotina territorial IBGE | Área de trabalho autenticada → Administração → Manutenções → Localidades brasileiras |

## Interaction Details

### INT-WEB-MAINTENANCE-001 — Consulta da rotina territorial IBGE

**Surface**: SURF-WEB-MAINTENANCE
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: permitir que administrador autorizado acompanhe a atualização territorial do IBGE sem alterar agenda nem disparar a rotina.
**Actors and Permissions**: administrador da Plataforma cuja autorização em escopo Plataforma permita `platform.maintenance.locality-ibge.read`; a ausência da permissão oculta a rotina e não expõe seu detalhe.
**Entry and Navigation**: o Hub permanece acessível pela área de trabalho autenticada. A rotina aparece na lista quando o endpoint de rotinas a devolver; selecionar seu item carrega o detalhe. Em telefone, o botão “Voltar às rotinas” retorna à lista e recebe foco natural no controle.
**Content and Data**: título “Localidades brasileiras”, descrição segura, estado, agenda “Inicial automática e mensal”, última execução e histórico técnico. O bloco de auditoria administrativa usa o padrão existente e informa lista vazia, pois esta rotina não possui ações administrativas.
**Actions and Behavior**: recarregar a lista/detalhe e filtrar por estado continuam disponíveis. Não há botão de atualização, diálogo de confirmação, edição de agenda ou ação de reativação/cadastro de localidades.
**Validation and Feedback**: a lista só inclui dados autorizados pelo servidor. `NOT_EXECUTED` é apresentado como condição inicial, não como falha. Estados de sucesso/falha usam resumo seguro do histórico; falha não mostra URL, provedor, exceção ou payload.
**Responsive/Adaptive Behavior**: em desktop, lista e detalhe permanecem lado a lado. Entre 701px e 900px, a lista pode assumir grade acima do detalhe. Em até 700px, o detalhe ocupa a área principal e a lista é ocultada até a pessoa ativar “Voltar às rotinas”. Todos os controles funcionam por toque, mouse e teclado.
**Accessibility**: preservar `main`, título de nível 2, navegação nomeada, `aria-current` no item selecionado, regiões de anúncio educadas e foco no título do detalhe após seleção. Estado, sucesso e falha têm texto além de cor; botões e seletor mantêm alvo mínimo existente e operação por teclado.
**Localization**: usar o catálogo i18n existente para rótulos genéricos do Hub. “Localidades brasileiras”, agenda e resumos da rotina devem ter chaves traduzíveis nos idiomas suportados; datas usam `Intl.DateTimeFormat` do locale ativo.
**Components and Design System**: reutilizar `MaintenanceHubSurface`, `UIRinoButton`, `UiAlert`, o seletor nativo e tokens existentes. Nenhum componente, ícone ou layout novo é necessário.
**Integration and Contracts**: consome [contracts/maintenance-routine.md](contracts/maintenance-routine.md) por meio dos endpoints existentes de lista e detalhe do Hub. `capabilities.canSynchronize` deve ser `false`; o endpoint de ação permanece indisponível para essa rotina.
**Telemetry**: não criar telemetria adicional nesta entrega. Logs/histórico técnicos do backend permanecem a fonte de observabilidade; a interface não registra conteúdo de erros nem dados de execução.
**Wireframe Requirement**: N/A
**Wireframe**: N/A — alteração usa a hierarquia, navegação e layout responsivo existentes; apenas acrescenta um item de rotina sem ação manual.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Hub ainda não iniciou a leitura; não exibe dado inventado. | Nenhuma antes do carregamento. | Torna-se `loading` na montagem. |
| loading | Indicador textual de carregamento quando não há dados prévios. | Recarregar fica ocupado conforme componente existente. | Torna-se `ready`, `empty`, `remote-error` ou `offline`. |
| empty | Mensagem de nenhuma rotina disponível quando a pessoa não possui leitura para nenhuma rotina. | Nenhuma ação sobre rotina. | Recarregar ou obter nova permissão. |
| ready | Lista inclui “Localidades brasileiras”; detalhe mostra estado, agenda, última execução e histórico. | Filtrar, selecionar rotina, voltar no mobile e recarregar. | Pode tornar-se `loading`, `partial-stale`, `remote-error` ou `offline`. |
| processing | N/A — a rotina não admite disparo manual e a tela não processa mutação. | N/A — motivo: não há ação de escrita. | N/A — motivo: não há ação de escrita. |
| success | N/A — não há confirmação de ação manual. Sucesso de execução aparece como dado de histórico dentro de `ready`. | Consultar histórico e recarregar. | Permanece em `ready`. |
| validation-error | N/A — não há formulário ou entrada desta rotina além do filtro existente. | Ajustar filtro, quando aplicável. | Retorna a `ready`. |
| remote-error | Alerta seguro de falha ao recarregar, sem detalhes da fonte IBGE; dados prévios não são descartados. | Recarregar quando a conexão estiver disponível. | Torna-se `ready`, `partial-stale` ou `offline`. |
| offline | Alerta de conexão indisponível; recarregar fica desabilitado. | Navegar por dados já carregados e aguardar retorno da conexão. | Ao evento online, pode recarregar e ir a `loading`. |
| access-denied | A rotina não aparece na lista; acesso direto recebe resposta normalizada de indisponibilidade, sem informar se ela existe. | Retornar a outra rotina/área permitida. | Nova autorização e recarregamento podem levar a `ready`. |
| partial-stale | Alerta de dados possivelmente desatualizados mantendo lista/detalhe já carregados. | Consultar dados preservados e tentar recarregar. | Torna-se `ready`, `remote-error` ou `offline`. |

## Cross-Surface Rules

### Navigation and Parity

Não há paridade implícita com aplicativo nativo ou outra tela administrativa. A lista/detalhe web existente é a única superfície humana desta entrega. O contrato postal é API para consumidor futuro e não gera tela nesta feature.

### Shared Content and Terminology

O termo canônico é “Localidades brasileiras”. “Inicial automática e mensal” descreve a agenda e não deve ser apresentado como botão ou configuração editável. `NOT_EXECUTED` representa a primeira execução ainda não concluída.

### Shared Accessibility and Input

Aplicam-se os padrões existentes de foco, anúncios, contraste, teclado e toque da superfície de manutenção. O carregamento e alertas usam texto perceptível, não apenas cor.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
| --- | --- | --- | --- | --- |
| INT-WEB-MAINTENANCE-001 | User Story 5 | FR-LOC-011, FR-LOC-012, FR-LOC-024 | SC-LOC-006 | [contracts/maintenance-routine.md](contracts/maintenance-routine.md) |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
| --- | --- | --- | --- |
| INT-WEB-MAINTENANCE-001 | N/A | N/A | Reutiliza a estrutura existente sem mudança de navegação ou hierarquia. |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes — none required
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
