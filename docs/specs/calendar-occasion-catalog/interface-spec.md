# Interface Specification: Calendar Occasion Catalog

**Feature**: `calendar-occasion-catalog`  
**Data**: 2026-10-02  
**Plano**: [plan.md](plan.md) · **Contrato**: [calendar-occasion-api.md](contracts/calendar-occasion-api.md)

## Interface Coverage

| Surface ID | Surface Type | Scope | Coverage | Decisão |
| --- | --- | --- | --- | --- |
| SURF-WEB-ADMIN | WEB | Administração global de definições de calendário | PARTIAL | Nova entrada e tela responsiva na Área de Trabalho; consumidores consultam a API, sem interface própria nesta fase. |

## Current-State Evidence

| Surface ID | Referência existente | Comportamento a preservar |
| --- | --- | --- |
| SURF-WEB-ADMIN | `resources/js/people/PeopleWorkspace.vue`, `PeopleCatalog.vue` e `PersonForm.vue` | Uma janela contém catálogo ou editor, nunca um formulário modal; voltar ao catálogo restaura foco e atualiza a consulta. |

## Integration with the workspace

Na esfera de domínio, a categoria existente **Administração** (`domain-governance`) passa a usar o ícone raster `planet`, em substituição ao SVG/fallback atual. Seus três itens atuais, no grupo **Administração**, permanecem como estão.

É criado o grupo **Tabelas Centrais** com o destino de instância única `platform.calendar-occasions`, rótulo **Feriados** e ícone raster `holiday`. A abertura é na Área de Trabalho, sem nova rota de navegador. A implementação registra ambos os ativos já existentes no `rasterIconAssets` e atualiza `workspaceCatalog.ts` e `WorkspaceStage.vue`.

O caminho visível é: **Domínio → Administração → Tabelas Centrais → Feriados**.

## Interaction Inventory

| Interaction ID | Surface ID | Type | Change Type | State | Purpose |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-ADMIN-001 | SURF-WEB-ADMIN | Tela | NEW | Planejado | Listar e filtrar definições; iniciar criação, edição ou exclusão. |
| INT-WEB-ADMIN-002 | SURF-WEB-ADMIN | Tela de edição | NEW | Planejado | Criar ou corrigir uma definição de ocasião de calendário. |

## Padrão obrigatório: janela de Pessoas

`CalendarOccasionWorkspace` seguirá a composição de `PeopleWorkspace`: `CalendarOccasionCatalog` é exibido quando nenhum editor está aberto; `CalendarOccasionForm` ocupa a mesma área para novo ou edição; salvar ou cancelar retorna ao catálogo, recarrega os dados e restaura o foco no catálogo. Não haverá diálogo de formulário nem rota paralela.

O catálogo seguirá o padrão visual de `PeopleCatalog`: área de trabalho com altura integral, toolbar, pesquisa por nome, filtros explícitos, grade rolável e virtualizável, configurações de coluna locais e seleção de uma linha. A feature não introduz ações em lote: a seleção única serve para abrir Editar ou Excluir na barra de comandos. O editor seguirá `PersonForm`: cabeçalho, abas, conteúdo rolável e barra de comandos persistente com Cancelar e Salvar; Excluir fica disponível apenas na edição e pede confirmação.

## Interaction Details

### INT-WEB-ADMIN-001 — Gestão de feriados

**Surface**: SURF-WEB-ADMIN  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: Administrar definições de feriados, pontos facultativos e datas comemorativas; não exibir nem persistir ocorrências.  
**Actors and Permissions**: Usuário no contexto de domínio já disponibilizado pela Área de Trabalho. Não há capability ou permissão nova nesta feature.  
**Entry and Navigation**: Abrir `platform.calendar-occasions` em Domínio → Administração → Tabelas Centrais → Feriados; a instância é única.  
**Content and Data**: Catálogo em altura integral, com toolbar, pesquisa por nome, filtros, contador e grade. As colunas padrão são Nome, Categoria, Esfera/localidade, Recorrência e Vigência; o usuário pode ordenar, ajustar largura e ocultar colunas como no catálogo de Pessoas. Ação primária **Novo feriado** e barra contextual para uma linha selecionada oferecem Editar e Excluir.
**Actions and Behavior**: Abertura carrega a primeira página. A pesquisa reutiliza o filtro `name`; filtros de categoria, esfera, País, UF, Município e período usam **Aplicar filtros** e **Limpar**. País → UF → Município é cascata. O período exige as duas datas e busca definições, não instâncias. Clique/Enter na linha ou Editar abre `INT-WEB-ADMIN-002`; ao voltar, catálogo preserva filtros, ordenação e posição de rolagem, recarrega e retoma foco. Excluir confirma que a definição deixa consultas futuras e vínculos filhos ficam independentes.
**Validation and Feedback**: De/até devem ser preenchidos juntos; o fim não pode anteceder início. Erros HTTP são mostrados junto à região de resultados; toast anuncia criação, edição ou exclusão. Confirmação de exclusão exige ação explícita.  
**Responsive/Adaptive Behavior**: Desktop/tablet seguem a grade rolável de Pessoas; em tablet filtros quebram em blocos. Telefone usa painel de filtros recolhível e cartões derivados da linha, mantendo seleção e menu acessível; não depende de arrastar.
**Accessibility**: Um `h1`, regiões nomeadas de filtros/resultados, rótulos visíveis e contador associado à coleção. Tabulação segue a ordem visual; sucesso, vazio, erro e carregamento usam `aria-live`. A confirmação devolve foco ao gatilho e nenhuma categoria depende só de cor/ícone.  
**Localization**: Rótulos, enumerações e mensagens entram no i18n existente em pt-BR, en, es e fr. Datas de negócio são ISO `YYYY-MM-DD` no contrato e exibidas sem horário conforme locale.  
**Components and Design System**: Reutiliza a composição, toolbar, grid virtualizado, seleção única, preferências locais de colunas, filtros, notificações e confirmação destrutiva de Pessoas; raster `planet` e `holiday` pelo registry central.
**Integration and Contracts**: `GET /api/v1/calendar-occasions` recebe `name`, categorias, esferas, localidade, período e paginação; `GET /api/v1/platform/calendar-occasions/{calendarOccasionId}` carrega edição; `DELETE` remove. Países/UFs/Municípios usam os endpoints de referência do contrato. Esta interação não chama `/calendar-occurrences`.  
**Telemetry**: Não cria telemetria específica; falhas seguem o mecanismo operacional existente sem registrar valores de filtros.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/int-web-admin-001.md

| State | Expected behavior |
| --- | --- |
| initial | Filtros vazios e primeira página solicitada. |
| loading | Esqueleto nos resultados; filtros e Nova permanecem estáveis. |
| empty | “Nenhuma definição encontrada”, com ação para limpar filtros ou criar. |
| ready | Linhas, contador e paginação ficam disponíveis. |
| processing | Exclusão mostra progresso somente na ação afetada. |
| success | Toast anuncia mutação e a lista recarrega. |
| validation-error | Período inválido é indicado nos campos antes da consulta. |
| remote-error | Última lista válida e filtros permanecem; botão Tentar novamente é oferecido. |
| offline | Dados carregados recebem indicação de desatualização; recarga e mutações ficam indisponíveis. |
| access-denied | Área de Trabalho exibe seu estado padrão de destino de domínio indisponível. |
| partial-stale | Resultados anteriores continuam legíveis com indicação de que a nova consulta falhou. |

### INT-WEB-ADMIN-002 — Editor de feriado

**Surface**: SURF-WEB-ADMIN  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: Criar ou corrigir uma definição na mesma janela do catálogo, sem impor versionamento, encerramento prévio ou controle jurídico.
**Actors and Permissions**: Usuário no contexto de domínio já disponibilizado pela Área de Trabalho; a feature não introduz autorização adicional.  
**Entry and Navigation**: Aberto por **Novo feriado**, clique/Enter na linha ou Editar da `INT-WEB-ADMIN-001`; substitui o catálogo na mesma superfície. Salvar ou Cancelar retorna ao catálogo e restaura foco.
**Content and Data**: Cabeçalho “Novo feriado” ou “Editar feriado”; abas **Geral**, **Recorrência** e **Vigência e substituição**. Geral contém Nome, Categoria, Esfera e País/UF/Município. Recorrência contém Tipo e parâmetros exclusivos. Vigência contém início/fim e substituição opcional. Campos vazios de vigência significam “sempre existiu” e “continua vigente”.
**Actions and Behavior**: `ONE_TIME_DATE` mostra data; `ANNUAL_FIXED_DATE`, dia/mês; `ANNUAL_NTH_WEEKDAY`, mês/ordinal/dia semanal; `EASTER_OFFSET`, deslocamento inteiro com ajuda: Paixão -2, Carnaval segunda -48, terça -47 e Corpus Christi +60. 29/02 ocorre somente em ano bissexto; quinta ocorrência semanal pode não existir. Data comemorativa força País; escopos de Estado/Município só estão disponíveis para Brasil e exigem a cadeia completa. Alterar categoria, esfera ou recorrência remove valores incompatíveis somente após confirmação. Substituição é opcional e aparece só para Feriado estadual/municipal, filtrando candidatas ancestrais facultativas compatíveis; o domínio decide a validade final. Salvar usa POST/PATCH; fechar com alteração pede descarte.  
**Validation and Feedback**: Nome, categoria, esfera, País, localidade exigida, recorrência e seus parâmetros são obrigatórios. Fim não antecede início. A validação local dá retorno imediato; `422` apresenta resumo navegável e erro junto ao campo, preservando valores.  
**Responsive/Adaptive Behavior**: Desktop/tablet mantêm abas e conteúdo rolável dentro da janela, como Pessoas. Telefone mantém abas com rolagem horizontal, campos em uma coluna e barra de comandos fixa respeitando área segura.
**Accessibility**: Foco inicial no cabeçalho do editor; Cancelar retorna foco ao catálogo. `Escape` solicita descarte se houver alterações. Abas seguem o padrão acessível de Pessoas; campos condicionais entram/removem-se da tabulação e erros usam `aria-describedby`.
**Localization**: Mesma matriz pt-BR/en/es/fr; rótulos de enumeração são localizados, enquanto valores de API continuam os enums do contrato. Data não possui timezone.  
**Components and Design System**: Reutiliza estrutura de `PersonForm`: cabeçalho, navegação por abas, frame com rolagem, barra de comandos, confirmação de descarte, select encadeado, campo de data e mensagens de erro.
**Integration and Contracts**: `POST /api/v1/platform/calendar-occasions`, `PATCH /api/v1/platform/calendar-occasions/{calendarOccasionId}`, consulta de definições para candidatas de promoção e endpoints territoriais do [contrato](contracts/calendar-occasion-api.md).  
**Telemetry**: Não cria evento novo e não registra valores de formulário.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/int-web-admin-002.md

| State | Expected behavior |
| --- | --- |
| initial | Formulário vazio ou definição carregada para edição. |
| loading | Seletor territorial dependente mostra progresso local sem bloquear campos independentes. |
| empty | Busca de candidata de promoção explica que não há ponto facultativo compatível. |
| ready | Campos aplicáveis ao tipo/esfera selecionados ficam editáveis. |
| processing | Salvar bloqueia controles que mudariam o payload e mantém o editor aberto. |
| success | Fecha, devolve foco e atualiza/avisa a lista de origem. |
| validation-error | Resumo e mensagens por campo preservam o preenchimento. |
| remote-error | Rede/servidor falho mantém dados e oferece nova tentativa. |
| offline | Salvar fica indisponível com explicação; dados locais permanecem editáveis sem persistir. |
| access-denied | Resposta de acesso é tratada pelo estado padrão da Área de Trabalho, sem formulário alternativo. |
| partial-stale | Referências previamente carregadas continuam identificadas como possivelmente desatualizadas após falha de atualização. |

## Traceability

| Interaction ID | User Story | Functional Requirement | Contract/Artifact | Validation |
| --- | --- | --- | --- | --- |
| INT-WEB-ADMIN-001 | US-1 | FR-011, FR-012, FR-013, FR-014 | Listagem de definições e `workspaceCatalog.ts` | Testes de cliente, contrato, navegação e responsividade. |
| INT-WEB-ADMIN-002 | US-1, US-2, US-3 | FR-001 a FR-010 | Mutação de definição e referências territoriais | Testes de formulário, requests, domínio e acessibilidade. |

## Validation Summary

- Navegação Domínio → Administração → Tabelas Centrais → Feriados abre uma única instância, preserva os três itens anteriores, usa `planet` na categoria e `holiday` na entrada.
- Cada tipo de recorrência mostra somente os parâmetros próprios e não envia valores residuais; data comemorativa/país estrangeiro limitam a esfera a País.
- Estado e Município exigem a cadeia territorial brasileira; a promoção não permite rebaixar feriado e continua validada no servidor.
- O filtro por período devolve regras, não ocorrências: `occurrenceDate` não é mostrado nesta interface.
- Cobrir carregamento, vazio, sucesso, validação, falha remota, offline, acesso indisponível, dados parcialmente desatualizados, teclado, foco e telefone.
