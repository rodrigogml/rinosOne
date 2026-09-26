# Interface Specification: Central de Manutenções

**Feature**: `maintenance-hub`  
**Criada**: 2026-09-26  
**Status**: Draft  
**Spec**: [spec.md](spec.md)  
**Plan**: [plan.md](plan.md)  
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Tipo | Usuários | Cobertura | Escopo incluído | Escopo adiado/excluído |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-MAINTENANCE | WEB | Administradores da Plataforma | FULL | Consulta de rotinas, detalhe, histórico, auditoria e ações permitidas | Integrações futuras ainda não revisadas; autorização definitiva depende da fundação de permissões. |
| SURF-FUTURE-CONSUMERS | API | Consumidores futuros autorizados | DEFERRED | Contrato preservado | Sem consumidor nesta entrega. |

## Current-State Evidence

| Surface ID | Rota, comando ou componente existente | Evidência | Comportamento atual |
| --- | --- | --- | --- |
| SURF-WEB-MAINTENANCE | Navegação da Área de Trabalho | `resources/js/workspace/workspaceCatalog.ts`, `resources/js/design-system/WorkspaceStage.vue` | A casca autenticada suporta destinos e superfícies; não existe destino ou conteúdo de manutenção. |

## Interaction Inventory

| Interaction ID | Surface ID | Tipo | Mudança | Nome | Entrada |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-MAINTENANCE-001 | SURF-WEB-MAINTENANCE | SCREEN | NEW | Central de Manutenções | Destino “Manutenções” da Área de Trabalho, visível somente a administradores autorizados. |
| INT-WEB-MAINTENANCE-002 | SURF-WEB-MAINTENANCE | DIALOG | NEW | Confirmação de ação de manutenção | Ação permitida na rotina detalhada. |

## Interaction Details

### INT-WEB-MAINTENANCE-001 — Central de Manutenções

**Surface**: SURF-WEB-MAINTENANCE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: permitir que um administrador descubra rotinas integradas, entenda sua situação e consulte seus registros seguros.

**Actors and Permissions**: administrador da Plataforma com permissão de visualização para a rotina. A ausência de acesso a uma rotina não revela sua existência. A política definitiva será vinculada à fundação de permissões.

**Entry and Navigation**: item “Manutenções” em categoria de administração da Área de Trabalho. A tela abre como superfície da área de trabalho; o retorno fecha a superfície e devolve foco ao destino de origem. Links internos não exigem contexto de tenant.

**Content and Data**:

- Cabeçalho com título, última atualização exibida e ação de recarregar.
- Lista de rotinas acessíveis: nome, descrição curta, estado, último resultado e capacidades permitidas.
- Painel de detalhe da rotina selecionada: resumo, informações próprias autorizadas, execução mais recente, histórico técnico e link/filtro para auditoria administrativa.
- Filtros por rotina, estado e período; paginação ou carregamento progressivo para históricos extensos.
- A rotina de instituições financeiras exibe sua periodicidade diária, execução anterior e disparo manual somente se a integração o autorizar.

**Actions and Behavior**:

- Selecionar rotina atualiza o painel de detalhe e a URL/superfície sem perder filtros válidos.
- Recarregar busca dados atuais e preserva seleção/filtros quando ainda aplicáveis.
- Ações permitidas por uma rotina aparecem como botões identificados; as demais não são renderizadas.
- Consultar auditoria abre o filtro correspondente na mesma superfície.
- O disparo manual abre `INT-WEB-MAINTENANCE-002`; não inicia a rotina antes da confirmação.

**Validation and Feedback**: filtros aceitam apenas valores oferecidos pela interface. Ações exibem confirmação com impacto seguro, processamento não bloqueável indevidamente e notificação de aceitação, recusa ou falha. Dados já carregados permanecem visíveis em erro remoto identificado como potencialmente desatualizado.

**Responsive/Adaptive Behavior**:

- Desktop: lista de rotinas e detalhe em duas colunas; histórico em tabela com colunas prioritárias.
- Tablet: lista superior/colapsável e detalhe abaixo; filtros permanecem acessíveis.
- Telefone: uma rotina por vez, com botão de retorno para a lista; cartões substituem tabelas e ações mantêm alvos de toque adequados.
- Funciona com teclado físico, toque e ampliação até 200% sem ocultar ação ou estado.

**Accessibility**: `main` possui título único; lista usa navegação semântica e indica item selecionado; alterações de carregamento e resultado usam região `aria-live`; foco vai ao título do detalhe ao trocar de rotina e retorna ao item anterior ao voltar. Estados não dependem só de cor; diálogos retêm e devolvem foco; todos os botões têm nome acessível.

**Localization**: rótulos e mensagens usam chaves de tradução existentes/novas; datas e horas seguem localidade e fuso do usuário quando disponível; textos permitem expansão de idioma e não expõem dados técnicos sensíveis.

**Components and Design System**: reutiliza a casca autenticada, superfície da Área de Trabalho, `UiButton`, alertas, diálogos e tokens existentes. Cria cartões/tabela de histórico específicos de manutenção somente onde o sistema de design não cobrir a semântica necessária.

**Integration and Contracts**: consome [maintenance-administration.md](contracts/maintenance-administration.md): listagem, detalhe e auditoria. Carregamento inicial não usa cache persistente; recarregamento manual busca estado atual.

**Telemetry**: registrar abertura da central, seleção de rotina, aplicação de filtro e solicitação de ação com `routineKey` e `action`; nunca registrar parâmetros, detalhes técnicos, nomes de usuários ou conteúdo de logs.

**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/int-web-maintenance-001.md

**Estados**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | Superfície aberta sem dados | Nenhuma até iniciar consulta | loading |
| loading | Esqueleto de lista e detalhe, com anúncio de carregamento | Fechar superfície | ready, empty, remote-error ou access-denied |
| empty | Explica que não há rotina acessível ou histórico no filtro | Ajustar filtro, recarregar | loading ou ready |
| ready | Lista, detalhe e ações permitidas | Consultar, filtrar, recarregar e ações específicas | processing, loading ou dialog |
| processing | Estado de ação somente na rotina afetada; botão correspondente indisponível | Cancelar somente se a rotina permitir | success, remote-error ou ready |
| success | Notificação curta e atualização do histórico | Fechar notificação, consultar resultado | ready |
| validation-error | Filtro ou ação inválida exibida junto ao campo/controle | Corrigir e reenviar | ready ou processing |
| remote-error | Mensagem segura; preserva conteúdo anterior como desatualizado se existir | Tentar novamente | loading ou partial-stale |
| offline | Aviso de conexão; dados anteriores só leitura | Tentar novamente quando online | loading ou partial-stale |
| access-denied | Estado sem detalhes de rotinas não autorizadas | Retornar à Área de Trabalho | fecha superfície |
| partial-stale | Identifica dados anteriores e falha de atualização | Recarregar | loading ou ready |

### INT-WEB-MAINTENANCE-002 — Confirmação de Ação de Manutenção

**Surface**: SURF-WEB-MAINTENANCE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: impedir disparos acidentais e tornar explícita a rotina e a ação antes de encaminhar uma solicitação permitida.

**Actors and Permissions**: mesmo administrador autorizado da tela principal; a autorização é reavaliada no momento da solicitação.

**Entry and Navigation**: botão de ação de `INT-WEB-MAINTENANCE-001`. `Esc`, cancelar, clique fora quando seguro e botão voltar fecham sem iniciar a rotina.

**Content and Data**: nome da rotina, nome/descrição segura da ação, impacto conhecido, campos de parâmetros permitidos e aviso de que a rotina poderá recusar a solicitação segundo suas próprias regras.

**Actions and Behavior**: confirmar encaminha uma solicitação única ao motor da rotina e mantém o diálogo até a resposta. Cancelar não produz execução; o fato de abrir e cancelar não exige auditoria. Aceitação, recusa ou falha de solicitação produz auditoria administrativa.

**Validation and Feedback**: valida os parâmetros específicos permitidos antes do envio; erros retornam ao campo; recusa da rotina explica a condição sem conteúdo sensível. Sucesso fecha o diálogo, atualiza o detalhe e anuncia resultado.

**Responsive/Adaptive Behavior**: modal central em desktop/tablet; folha inferior em telefone, com ações visíveis acima do teclado virtual e ordem de foco preservada.

**Accessibility**: diálogo modal rotulado, foco inicial no título ou primeira entrada, `Esc` seguro, foco devolvido ao botão que o abriu, confirmação não identificada apenas por cor.

**Localization**: textos e parâmetros seguem tradução e formatos da rotina; nenhuma mensagem inclui segredo ou payload técnico.

**Components and Design System**: reutiliza host de diálogos e botões do sistema de design; cria campos específicos apenas se a ação os exigir.

**Integration and Contracts**: usa a operação de solicitação de ação no [contrato administrativo](contracts/maintenance-administration.md).

**Telemetry**: registrar abertura, cancelamento e conclusão de diálogo somente com `routineKey`, `action` e resultado; parâmetros são excluídos.

**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/int-web-maintenance-002.md

**Estados**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | Diálogo preparado com dados da ação | Confirmar, cancelar | ready ou processing |
| loading | N/A — os dados já pertencem ao detalhe aberto | N/A | N/A |
| empty | N/A — uma ação sempre possui rotina e nome | N/A | N/A |
| ready | Campos e explicação visíveis | Confirmar ou cancelar | processing ou fecha |
| processing | Confirmação indisponível e progresso anunciado | Cancelar somente se ainda não enviado | success, validation-error ou remote-error |
| success | Resultado anunciado; diálogo fecha | Consultar detalhe | ready na tela principal |
| validation-error | Erro associado ao campo | Corrigir ou cancelar | ready |
| remote-error | Erro seguro e tentativa novamente | Repetir ou cancelar | processing ou fecha |
| offline | Envio indisponível sem perder parâmetros | Cancelar ou aguardar conexão | ready |
| access-denied | Diálogo fecha com notificação segura | Retornar ao detalhe | ready na tela principal |
| partial-stale | N/A — ação não é submetida com dados desatualizados | N/A | N/A |

## Regras Transversais

### Navegação e Paridade

A central é uma superfície de administração de Plataforma, não depende do tenant ativo e não possui paridade nativa móvel. Em telefone, preserva as mesmas capacidades autorizadas por meio de fluxo lista → detalhe → ação.

### Conteúdo e Terminologia

Usar “Central de Manutenções”, “rotina”, “histórico técnico” e “auditoria administrativa” como termos canônicos. “Agenda” aparece apenas quando a integração a oferece.

### Acessibilidade e Entrada Compartilhadas

Todos os fluxos críticos funcionam por teclado e toque, anunciam mudanças de estado e preservam foco previsível. Logs e detalhes são apresentados como texto selecionável, com rótulos e relações semânticas.

## Traceability

| Interaction ID | Histórias | Requisitos | Critérios | Contratos |
| --- | --- | --- | --- | --- |
| INT-WEB-MAINTENANCE-001 | 1, 2, 3, 4 | FR-MH-001, 004, 006, 007, 008, 009, 010, 012, 013 | SC-MH-001, 002, 003, 004, 005 | `maintenance-administration.md` |
| INT-WEB-MAINTENANCE-002 | 2, 3, 4 | FR-MH-005, 007, 010, 011, 013, FR-MH-INFRA-IDEMP | SC-MH-002, 003, 005 | `maintenance-administration.md` |

## Wireframes

| Interação | Requisito | Artefato | Notas |
| --- | --- | --- | --- |
| INT-WEB-MAINTENANCE-001 | REQUIRED | `wireframes/int-web-maintenance-001.md` | Hierarquia lista, detalhe e histórico. |
| INT-WEB-MAINTENANCE-002 | REQUIRED | `wireframes/int-web-maintenance-002.md` | Confirmação de ação segura. |

## Validation Summary

- Matriz de cobertura revisada: sim.
- Todos os itens do inventário possuem detalhe: sim.
- Estados canônicos resolvidos: sim.
- Wireframes obrigatórios presentes: sim.
- Requisitos de acessibilidade resolvidos: sim.
- Mapeamentos de contrato verificados: sim.
- Placeholders ou decisões abertas: 0.
