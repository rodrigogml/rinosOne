# Interface Specification: Cadastro de Pessoas por Organização

**Feature**: `person-registration`  
**Created**: 2026-09-28  
**Status**: Pronta para backlog  
**Spec**: [spec.md](spec.md)  
**Plan**: [plan.md](plan.md)  
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-PEOPLE | WEB | Usuários autorizados de organização | FULL | Catálogo, busca, criação rápida e completa, edição, coleções, duplicação, inativação, reativação e exclusão em desktop, tablet e telefone. | Dados fiscais, profissionais, dependentes, prioridades de contato/Pix e tela de auditoria. |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
| --- | --- | --- | --- |
| SURF-WEB-PEOPLE | `resources/js/workspace/workspaceCatalog.ts` e `resources/js/design-system/WorkspaceStage.vue` | Inspeção de código: o catálogo só possui destinos atuais e o palco não possui destino de Pessoas. | Não existe menu, janela ou tela de Pessoas; destino organizacional e superfície serão novos. |

Não foi feita inspeção visual interativa nesta etapa, pois a especificação foi construída a partir dos componentes e padrões existentes do repositório.

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-PEOPLE-001 | SURF-WEB-PEOPLE | SCREEN | NEW | Catálogo e busca de Pessoas | Novo destino `tenant.people` no menu da organização. |
| INT-WEB-PEOPLE-002 | SURF-WEB-PEOPLE | SCREEN | NEW | Formulário de dados básicos da Pessoa | Ação Nova Pessoa, criação rápida ou abertura a partir do catálogo. |
| INT-WEB-PEOPLE-003 | SURF-WEB-PEOPLE | PANEL | NEW | Editores de dados relacionados | Seções do formulário de Pessoa. |
| INT-WEB-PEOPLE-004 | SURF-WEB-PEOPLE | DIALOG | NEW | Duplicação de Pessoa | Ação Duplicar no catálogo ou formulário. |
| INT-WEB-PEOPLE-005 | SURF-WEB-PEOPLE | DIALOG | NEW | Ciclo de vida e exclusão | Menu de ações da Pessoa. |
| INT-WEB-PEOPLE-006 | SURF-WEB-PEOPLE | DIALOG | NEW | Cadastro rápido de Pessoa | Ação Nova Pessoa em fluxos que exigem selecionar uma Pessoa. |

## Interaction Details

### INT-WEB-PEOPLE-001 — Catálogo e busca de Pessoas

**Surface**: SURF-WEB-PEOPLE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: localizar e iniciar operações sobre Pessoas exclusivamente na organização em contexto.  
**Actors and Permissions**: usuários com `tenant.people.read`; criar e ações individuais aparecem somente com suas permissões específicas.  
**Entry and Navigation**: o menu da organização abre uma instância única de `tenant.people`. Nova Pessoa abre o formulário; Abrir mostra o formulário em modo de consulta/edição; Voltar retorna ao catálogo preservando busca, página e filtro desta instância.  
**Content and Data**: cabeçalho identifica Pessoas e a organização atual; campo de busca, filtros de tipo/situação, lista paginada e contagem. Cada item mostra nome de exibição, PF/PJ, documento quando existir, resumo de contatos e situação. Dados fiscais, profissionais e dados de outras organizações nunca aparecem.  
**Actions and Behavior**: buscar por nome, alias, documento ou contato; filtrar ativas, inativas ou todas; limpar filtros; atualizar; abrir; criar; abrir menu contextual de duplicar, inativar, reativar ou excluir. A lista inicia em Pessoas ativas.  
**Validation and Feedback**: busca aceita texto livre e aplica atraso curto sem bloquear digitação; filtros inválidos são descartados com restauração segura. Erros mantêm a última lista válida sinalizada como possivelmente desatualizada e oferecem Atualizar.  
**Responsive/Adaptive Behavior**: desktop usa tabela com colunas e painel de filtros lateral; tablet pode ocultar resumo de contatos; telefone usa cards, sheet de filtros e paginação compacta. Todas as ações continuam disponíveis por menu explícito ou tela de detalhe.  
**Accessibility**: landmark principal, título único, tabela com cabeçalhos em desktop, cards com nome acessível em telefone, foco retorna ao acionador depois de fechar filtros, resultado de busca e atualização anunciados sem interromper a digitação.  
**Localization**: rótulos, situação, filtros, pluralização e mensagens usam i18n; documentos podem ser exibidos sem máscara, mas nunca são incluídos em telemetria.  
**Components and Design System**: reutiliza janela da Área de Trabalho, toolbar, campo de busca, botões, badge de situação, tabela, cards, menu contextual, sheet e alertas; cria somente componentes de domínio de Pessoas.  
**Integration and Contracts**: consome `GET /api/v1/tenants/{tenantId}/people` e a projeção Pessoa resumida em [people-api.md](contracts/people-api.md). A lista é descartada ao trocar de organização ou perder acesso.  
**Telemetry**: registrar abertura, categoria de filtro e resultado em faixas; excluir nome, documento, contato, organização e conteúdo pesquisado.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/people-catalog.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Janela abre com título e filtros padrão. | Buscar, filtrar, atualizar, criar se autorizado. | loading. |
| loading | Lista exibe skeleton; filtros permanecem visíveis. | Alterar busca, voltar, cancelar filtro. | ready, empty, remote-error ou access-denied. |
| empty | Explica que não há Pessoas para o filtro e oferece limpar filtro ou criar. | Limpar, atualizar, criar se autorizado. | loading ou ready. |
| ready | Lista paginada e ações permitidas disponíveis. | Todas as ações do catálogo. | loading, processing ou diálogo. |
| processing | N/A — operações mutáveis ocorrem em suas interações próprias. | N/A — motivo: catálogo só apresenta retorno. | N/A — motivo: sem mutação própria. |
| success | Notificação discreta após retorno de outra operação e lista atualizada. | Continuar busca ou abrir item. | ready. |
| validation-error | N/A — filtros são valores controlados e texto livre não bloqueia. | N/A — motivo: sem formulário de domínio. | N/A — motivo: sem erro de validação aplicável. |
| remote-error | Alerta seguro, última lista preservada quando existir e botão Atualizar. | Tentar novamente, ajustar filtro, sair. | loading ou initial. |
| offline | Indica indisponibilidade; não cria nem muda dados. | Consultar lista já carregada ou sair. | loading ao reconectar. |
| access-denied | Janela informa indisponibilidade e não mostra dados. | Voltar à Área de Trabalho. | initial após contexto/permite acesso válido. |
| partial-stale | Lista anterior recebe indicador de dados possivelmente desatualizados. | Atualizar, refinar busca ou sair. | loading ou ready. |

### INT-WEB-PEOPLE-002 — Formulário de dados básicos da Pessoa

**Surface**: SURF-WEB-PEOPLE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: criar, consultar ou alterar a identidade e os dados básicos de uma Pessoa PF ou PJ.  
**Actors and Permissions**: requer `tenant.people.create` para novo cadastro, `tenant.people.read` para consulta e `tenant.people.update` para alteração.  
**Entry and Navigation**: Nova Pessoa abre formulário completo; item existente abre em consulta e permite Editar conforme autorização; cadastro rápido pode promover seus dados para este formulário. Cancelar ou Voltar pergunta sobre alterações não salvas.  
**Content and Data**: dados básicos aparecem primeiro: tipo PF/PJ, nome/razão social, apelido/nome fantasia, nome de exibição calculado, CPF ou CNPJ opcional, documentos complementares aplicáveis, data de nascimento/fundação e observações. O formulário mostra seções de dados relacionados com contagem de itens.  
**Actions and Behavior**: alternar PF/PJ antes de salvar; preencher ou remover documento opcional; salvar, cancelar e navegar para coleções. Mudar PF/PJ limpa ou pede confirmação antes de descartar campos incompatíveis. Nome de exibição é somente leitura e atualiza durante a edição.  
**Validation and Feedback**: validação imediata orienta formato, datas futuras e incompatibilidade PF/PJ; servidor confirma unicidade de documento e apresenta o conflito no campo certo. Erros preservam todos os valores digitados e levam o foco ao primeiro erro depois do resumo acessível.  
**Responsive/Adaptive Behavior**: desktop usa abas horizontais e campos em até duas colunas; telefone mantém Dados como primeira seção e abre coleções em sheets de tela cheia, sem reduzir campos ou ações. Teclado virtual não cobre Salvar nem erro ativo.  
**Accessibility**: campos têm rótulo persistente, instrução de opcionalidade e erro associado; tipo PF/PJ é grupo de opções com estado anunciado; foco segue a ordem visual e retorna à origem ao cancelar; zoom não oculta comandos.  
**Localization**: formatos de data e rótulos usam idioma ativo; CPF/CNPJ permanecem reconhecíveis por leitores de tela sem anunciar além do necessário; textos longos de observação aceitam expansão de idioma.  
**Components and Design System**: reutiliza formulário, campos, validação, tabs, sheets, botões e diálogo de alterações não salvas; cria composição `PersonForm` e seções específicas do domínio.  
**Integration and Contracts**: consome criação, detalhe e atualização descritos em [people-api.md](contracts/people-api.md). O cliente não calcula nome de exibição como regra de autoridade e atualiza-se com a resposta do servidor.  
**Telemetry**: registrar somente início, conclusão, abandono e categoria de erro; excluir nomes, documentos, observações e todos os valores de campo.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/person-form.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Novo formulário vazio ou detalhe ainda não solicitado. | Cancelar ou iniciar carregamento. | loading ou ready. |
| loading | Skeleton dos campos; nenhuma entrada até receber detalhe. | Cancelar e voltar. | ready, remote-error ou access-denied. |
| empty | N/A — formulário sempre representa nova Pessoa ou detalhe conhecido. | N/A — motivo: sem coleção própria. | N/A — motivo: sem estado vazio aplicável. |
| ready | Campos e seções disponíveis conforme permissão. | Editar, salvar, abrir coleção, cancelar. | processing, validation-error ou diálogo. |
| processing | Salvar bloqueia envio duplicado e mantém os dados visíveis. | Cancelar apenas se a solicitação ainda não tiver sido enviada. | success, validation-error, remote-error ou access-denied. |
| success | Notificação confirma e resposta atualiza nome de exibição e estado. | Continuar editando ou voltar ao catálogo. | ready ou initial. |
| validation-error | Resumo e campos afetados explicam correção sem apagar valores. | Corrigir e salvar. | ready ou processing. |
| remote-error | Erro seguro preserva rascunho e oferece nova tentativa. | Tentar novamente, salvar localmente apenas enquanto a tela existir ou cancelar. | processing, ready ou initial. |
| offline | Novas mutações ficam indisponíveis; rascunho local visível não é enviado. | Corrigir, copiar conteúdo, cancelar. | ready ao reconectar. |
| access-denied | Edição cessa; dados permitidos podem ficar em consulta até retorno seguro. | Voltar ao catálogo. | initial. |
| partial-stale | Resposta indica conflito ou dado alterado desde leitura; tela pede recarregar antes de sobrescrever. | Recarregar e comparar; cancelar. | loading ou initial. |

### INT-WEB-PEOPLE-003 — Editores de dados relacionados

**Surface**: SURF-WEB-PEOPLE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: manter endereços, contatos, contas bancárias, chaves Pix e relacionamentos da Pessoa sem sair do seu contexto.  
**Actors and Permissions**: usuários com permissão de criar ou atualizar Pessoas; leitura mostra itens sem controles de alteração.  
**Entry and Navigation**: cada seção é aberta a partir do formulário de Pessoa. Desktop expande seção ou painel lateral; telefone abre sheet de tela cheia com Voltar explícito. Concluir ou cancelar retorna ao mesmo ponto do formulário.  
**Content and Data**: cada coleção mostra contagem, itens resumidos e ações Adicionar, Editar ou Remover. Endereço contém país obrigatório e, para Brasil, UF e município; rua textual continua disponível sem referência de localidade. Contatos não têm principal. Contas e Pix não têm principal ou finalidade. Relacionamento mostra a outra Pessoa, tipo direcional, rótulo de apresentação e descrição.  
**Actions and Behavior**: adicionar, editar, remover itens ainda não salvos ou já persistidos; pesquisar catálogos disponíveis sem bloquear entrada textual de rua; selecionar outra Pessoa da mesma organização para relacionamento; impedir auto-vínculo e apresentar o inverso apenas como leitura contextual.  
**Validation and Feedback**: erros ficam no item e campo; país, UF e município brasileiros exigidos; formato do contato/Pix depende do tipo; uma conta ou chave duplicada para a mesma Pessoa é recusada; remover item persistido pede confirmação.  
**Responsive/Adaptive Behavior**: desktop permite visão resumida da coleção ao lado do editor; telefone usa uma única coluna, ações de item em menu e sheet rolável internamente. Toque, teclado físico e ponteiro alcançam todas as ações.  
**Accessibility**: cada coleção é região nomeada com contagem anunciada; adicionar abre editor com foco no título; remover pede confirmação com foco seguro; relação de entrada usa rótulo oposto e identifica claramente a outra Pessoa, sem depender de cor ou ícone.  
**Localization**: tipos de endereço, contato, conta, Pix e relacionamento possuem rótulos localizados; número, rua e descrição não são traduzidos; nenhuma chave Pix, conta ou documento é enviada a telemetria.  
**Components and Design System**: reutiliza listas, cards, campos condicionais, lookup, panel, sheet, diálogo de confirmação e alertas; cria editores tipados do domínio somente quando componentes existentes não bastarem.  
**Integration and Contracts**: coleções fazem parte de criação e atualização em [people-api.md](contracts/people-api.md); referências de catálogo são consultadas somente quando disponíveis e nunca impedem rua textual válida.  
**Telemetry**: registrar tipo de coleção e resultado, em categorias; excluir valores, documento, endereço, conta, Pix, relacionamento e identificadores.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/person-form.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Seção fechada ou editor ainda não aberto. | Abrir, adicionar ou consultar. | loading ou ready. |
| loading | Consulta de coleção ou catálogo mostra indicador local. | Fechar editor se nada foi alterado. | ready, empty, remote-error ou access-denied. |
| empty | Explica ausência de itens e oferece Adicionar quando autorizado. | Adicionar ou voltar. | ready ou initial. |
| ready | Lista e editor mostram dados atuais e ações permitidas. | Adicionar, editar, remover, salvar. | processing, validation-error ou diálogo. |
| processing | Item em salvamento fica identificado; demais alterações não são perdidas. | Aguardar ou cancelar se ainda não enviado. | success, validation-error ou remote-error. |
| success | Item atualizado e contagem recalculada; retorno anunciado. | Continuar ou voltar ao formulário. | ready ou initial. |
| validation-error | Campo e item explicam a inconsistência. | Corrigir e salvar. | ready ou processing. |
| remote-error | Dados digitados continuam no editor e há tentativa novamente. | Tentar novamente, copiar dados ou cancelar. | processing, ready ou initial. |
| offline | Não inicia consulta de catálogo nem mutação remota. | Preencher texto local, cancelar ou aguardar conexão. | ready ao reconectar. |
| access-denied | Editor fecha ou fica somente leitura após revogação. | Retornar ao formulário. | initial. |
| partial-stale | Item alterado fora da tela pede recarregamento antes de substituir. | Recarregar ou cancelar alteração. | loading ou initial. |

### INT-WEB-PEOPLE-004 — Duplicação de Pessoa

**Surface**: SURF-WEB-PEOPLE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: criar uma nova Pessoa semelhante, permitindo escolha explícita das coleções que serão copiadas sem replicar identidade.  
**Actors and Permissions**: requer `tenant.people.duplicate` e criação de Pessoa; não aparece sem ambas as capacidades.  
**Entry and Navigation**: ação Duplicar no catálogo ou formulário abre diálogo contextual; ao concluir, o formulário da nova Pessoa é aberto. Cancelar preserva a Pessoa original sem alteração.  
**Content and Data**: mostra nome da origem, aviso de que documento e identificadores exclusivos não são copiados e escolhas separadas para endereços, contatos, contas, Pix e relacionamentos.  
**Actions and Behavior**: escolher cada coleção, confirmar ou cancelar; quando contas, Pix ou relacionamentos forem selecionados, a nova Pessoa é aberta para revisão antes da conclusão do cadastro.  
**Validation and Feedback**: o diálogo exige escolha explícita para todas as coleções; falha de autorização ou origem inexistente fecha o diálogo com mensagem segura; não inicia cópia parcialmente silenciosa.  
**Responsive/Adaptive Behavior**: desktop usa diálogo central; telefone usa sheet modal de tela cheia, com ações no rodapé seguro. As mesmas escolhas e avisos permanecem visíveis.  
**Accessibility**: foco inicia no título e segue pelos controles de escolha; texto de confirmação nomeia a origem sem repetir documentos; Escape ou Voltar cancela somente antes da confirmação.  
**Localization**: rótulos, contagens e aviso de identidade usam i18n; nome da origem é dado do usuário e não é traduzido.  
**Components and Design System**: reutiliza diálogo/sheet de confirmação, checkboxes, alertas e botões; não cria padrão de decisão paralelo.  
**Integration and Contracts**: consome `POST /people/{personId}/duplicate` de [people-api.md](contracts/people-api.md). A resposta é tratada como nova Pessoa e não modifica o item de origem.  
**Telemetry**: registrar somente confirmação/cancelamento e categorias de coleções escolhidas; excluir Pessoa, documentos e quaisquer valores copiados.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/person-form.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Diálogo fechado. | Abrir a partir de ação autorizada. | loading. |
| loading | Origem e opções são preparadas. | Cancelar se ainda não houve confirmação. | ready, remote-error ou access-denied. |
| empty | N/A — a origem é obrigatória. | N/A — motivo: sem origem não há diálogo. | N/A — motivo: sem operação aplicável. |
| ready | Avisos e escolhas de cópia visíveis. | Confirmar ou cancelar. | processing ou initial. |
| processing | Confirmação bloqueia duplicação adicional. | Aguardar. | success, validation-error, remote-error ou access-denied. |
| success | Diálogo fecha e abre formulário da nova Pessoa. | Revisar e salvar a nova Pessoa. | INT-WEB-PEOPLE-002 ready. |
| validation-error | Escolha inválida ou referência indisponível é explicada. | Ajustar escolhas. | ready. |
| remote-error | Mensagem segura preserva escolhas. | Tentar novamente ou cancelar. | processing ou initial. |
| offline | Confirmação fica indisponível. | Cancelar ou aguardar conexão. | ready. |
| access-denied | Diálogo fecha e informa indisponibilidade. | Retornar ao catálogo/formulário. | initial. |
| partial-stale | Origem mudou desde a abertura; escolhas não são aplicadas. | Reabrir com dados atuais. | loading ou initial. |

### INT-WEB-PEOPLE-005 — Ciclo de vida e exclusão

**Surface**: SURF-WEB-PEOPLE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: confirmar inativação, reativação ou exclusão física da Pessoa de modo compreensível e seguro.  
**Actors and Permissions**: exige respectivamente `tenant.people.inactivate`, `tenant.people.reactivate` ou `tenant.people.delete`; a confirmação não é exibida se a ação não for autorizada.  
**Entry and Navigation**: menu de ação no catálogo ou formulário abre diálogo específico. Após sucesso, retorna ao catálogo ou ao estado de consulta da Pessoa conforme operação.  
**Content and Data**: identifica Pessoa por nome de exibição e situação, explica efeito da ação e, para exclusão bloqueada, mostra lista segura de usos conhecidos. Não exibe documento completo, SQL, identificadores técnicos ou detalhes internos de módulo.  
**Actions and Behavior**: inativar confirma retirada de seleções comuns; reativar confirma retorno; excluir confirma remoção física de dados filhos e relacionamentos. Uso bloqueante transforma o diálogo em orientação, sem oferecer confirmação destrutiva.  
**Validation and Feedback**: servidor sempre revalida situação, autorização e usos. Se houver conflito inesperado, o diálogo informa que a exclusão não foi concluída e mantém a Pessoa intacta.  
**Responsive/Adaptive Behavior**: desktop usa diálogo focado; telefone usa sheet modal que não depende de hover e mantém ação destrutiva distante de Cancelar. Todas as mensagens e ações cabem em rolagem interna, não no canvas externo.  
**Accessibility**: foco inicial vai para título e descrição; ação destrutiva possui nome explícito; Cancelar é a ação visual inicial; o resultado é anunciado e o foco retorna ao menu/linha existente ou à lista após remoção.  
**Localization**: mensagens de status, confirmação, uso bloqueante e erro usam i18n; nome de Pessoa não é traduzido.  
**Components and Design System**: reutiliza diálogos de confirmação, alertas, badges e botões destrutivos existentes.  
**Integration and Contracts**: consome inativação, reativação e exclusão de [people-api.md](contracts/people-api.md), incluindo `PERSON_IN_USE` e `PERSON_DELETE_CONFLICT`.  
**Telemetry**: registrar categoria da ação e resultado; excluir nome, documento, lista de usos e identificadores.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/people-catalog.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Diálogo fechado. | Abrir ação autorizada. | loading. |
| loading | Situação e diagnóstico de uso são consultados. | Cancelar quando a ação ainda não iniciou. | ready, remote-error ou access-denied. |
| empty | N/A — ação exige Pessoa definida. | N/A — motivo: sem alvo não abre. | N/A — motivo: sem operação aplicável. |
| ready | Efeito e confirmação apropriados visíveis. | Confirmar ou cancelar. | processing ou initial. |
| processing | Botão de confirmação ocupado e saída acidental bloqueada. | Aguardar. | success, remote-error ou access-denied. |
| success | Notificação confirma; lista/formulário se atualiza. | Retornar ao catálogo ou continuar consulta. | INT-WEB-PEOPLE-001 ready ou INT-WEB-PEOPLE-002 ready. |
| validation-error | Situação mudou e ação não é mais aplicável. | Atualizar Pessoa ou fechar. | loading ou initial. |
| remote-error | Mensagem segura preserva dados e não afirma remoção. | Tentar novamente ou cancelar. | processing ou initial. |
| offline | Ação remota não é iniciada. | Cancelar ou aguardar conexão. | ready. |
| access-denied | Diálogo fecha e a lista é revalidada. | Retornar ao catálogo. | initial. |
| partial-stale | Diagnóstico ou situação mudou; confirmação anterior é invalidada. | Recarregar diagnóstico. | loading ou initial. |

### INT-WEB-PEOPLE-006 — Cadastro rápido de Pessoa

**Surface**: SURF-WEB-PEOPLE  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: cadastrar uma Pessoa mínima sem abandonar um fluxo que precisa selecioná-la.  
**Actors and Permissions**: requer `tenant.people.create`; sem permissão, o fluxo oferece somente busca e seleção de Pessoas existentes.  
**Entry and Navigation**: acionado dentro de futuras escolhas de Pessoa ou pelo atalho Nova Pessoa; após salvar, retorna a Pessoa recém-criada como seleção do fluxo de origem ou abre seu formulário completo quando acionado do catálogo.  
**Content and Data**: apresenta tipo PF/PJ, nome/razão social, apelido/nome fantasia opcional e CPF/CNPJ opcional. Mostra que documento é opcional e que dados adicionais podem ser completados depois.  
**Actions and Behavior**: selecionar tipo, preencher mínimos, salvar ou cancelar. Não mostra coleções, dados fiscais, profissionais, prioridade de contato ou Pix.  
**Validation and Feedback**: aplica as mesmas regras de nome, tipo e documento do formulário completo; conflito de documento mantém o diálogo e direciona para busca da Pessoa existente quando apropriado.  
**Responsive/Adaptive Behavior**: desktop usa diálogo compacto; telefone usa sheet de tela cheia com os mesmos campos, ações e retorno ao fluxo de origem.  
**Accessibility**: foco inicia no título, campos possuem rótulos e erros associados, e o retorno foca o seletor de Pessoa de origem com a seleção anunciada.  
**Localization**: rótulos, opcionalidade, instruções e erros usam i18n; os valores inseridos permanecem no idioma/dados do usuário.  
**Components and Design System**: reutiliza diálogo/sheet, campos, grupo de tipo, botões e alertas do formulário principal.  
**Integration and Contracts**: consome criação em [people-api.md](contracts/people-api.md) e recebe a projeção Pessoa resumida para o fluxo de origem.  
**Telemetry**: registrar abertura, conclusão, cancelamento e categoria de erro; excluir nome e documento.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/person-form.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
| --- | --- | --- | --- |
| initial | Diálogo fechado. | Abrir por ação autorizada. | ready. |
| loading | N/A — formulário mínimo não exige leitura prévia. | N/A — motivo: dados locais iniciais. | N/A — motivo: sem carregamento próprio. |
| empty | N/A — campos vazios constituem estado inicial válido. | N/A — motivo: não é lista. | N/A — motivo: sem estado vazio distinto. |
| ready | Campos mínimos e explicação de opcionalidade visíveis. | Salvar ou cancelar. | processing ou validation-error. |
| processing | Salvar bloqueia duplo envio. | Aguardar. | success, validation-error, remote-error ou access-denied. |
| success | Fecha e devolve a nova Pessoa ao fluxo de origem. | Continuar seleção ou completar cadastro. | initial ou INT-WEB-PEOPLE-002 ready. |
| validation-error | Campo de nome, tipo ou documento explica correção. | Corrigir e salvar. | ready ou processing. |
| remote-error | Dados permanecem e há nova tentativa. | Tentar novamente ou cancelar. | processing ou initial. |
| offline | Salvamento é indisponível. | Cancelar ou aguardar conexão. | ready. |
| access-denied | Fecha e informa que criação não está disponível. | Retornar ao fluxo de origem. | initial. |
| partial-stale | N/A — não há dado remoto prévio a ficar desatualizado. | N/A — motivo: criação mínima nova. | N/A — motivo: sem leitura anterior. |

## Cross-Surface Rules

### Navigation and Parity

`SURF-WEB-PEOPLE` é uma única SPA responsiva: desktop, tablet e telefone possuem todas as operações autorizadas. Desktop privilegia tabela, painel lateral e abas; telefone usa cards, sheets e telas completas para preservar legibilidade e toque. Nenhuma ação depende exclusivamente de hover, duplo clique, gesto de deslizar ou atalho de teclado.

O destino `tenant.people` é visível somente com contexto organizacional ativo e pelo menos a capacidade de leitura. A troca de organização fecha a instância ou descarta dados carregados, impedindo que conteúdo de uma organização apareça em outra.

### Shared Content and Terminology

Os termos canônicos são “Pessoas”, “Pessoa física”, “Pessoa jurídica”, “Ativa”, “Inativa”, “Inativar”, “Reativar”, “Excluir”, “Endereço”, “Contato”, “Conta bancária”, “Chave Pix” e “Relacionamento”. “Excluir” sempre designa remoção física e informa que não pode ser desfeita. “Rua/Logradouro” não exige referência prévia de localidade.

### Shared Accessibility and Input

Todos os fluxos críticos funcionam por teclado, leitor de tela, toque e ponteiro. Ícones possuem nome acessível ou rótulo visível; cor nunca é o único indicador de situação; feedback assíncrono usa região de anúncio apropriada. Escape fecha somente camadas dispensáveis; confirmações destrutivas exigem ação explícita. Respeitar zoom, movimento reduzido, teclado virtual e áreas seguras.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
| --- | --- | --- | --- | --- |
| INT-WEB-PEOPLE-001 | História 1, História 5 | FR-001 a FR-003, FR-020 a FR-022 | CS-001, CS-003, CS-005 | [people-api.md](contracts/people-api.md) |
| INT-WEB-PEOPLE-002 | História 1, História 2 | FR-004 a FR-011, FR-024, FR-026 | CS-001, CS-002, CS-003 | [people-api.md](contracts/people-api.md) |
| INT-WEB-PEOPLE-003 | História 2, História 3, História 4 | FR-009 a FR-019A, FR-026 | CS-001, CS-003 | [people-api.md](contracts/people-api.md) |
| INT-WEB-PEOPLE-004 | História 6 | FR-025, FR-026 | CS-001, CS-003 | [people-api.md](contracts/people-api.md) |
| INT-WEB-PEOPLE-005 | História 5 | FR-020 a FR-023 | CS-001, CS-003, CS-004 | [people-api.md](contracts/people-api.md) |
| INT-WEB-PEOPLE-006 | História 1 | FR-004 a FR-007, FR-024, FR-026 | CS-001, CS-002, CS-003 | [people-api.md](contracts/people-api.md) |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
| --- | --- | --- | --- |
| INT-WEB-PEOPLE-001 | REQUIRED | [people-catalog.md](wireframes/people-catalog.md) | Catálogo, filtros e adaptação para cards. |
| INT-WEB-PEOPLE-002 | REQUIRED | [person-form.md](wireframes/person-form.md) | Dados básicos e navegação das coleções. |
| INT-WEB-PEOPLE-003 | REQUIRED | [person-form.md](wireframes/person-form.md) | Coleções em painel e sheet. |
| INT-WEB-PEOPLE-004 | REQUIRED | [person-form.md](wireframes/person-form.md) | Diálogo/sheet de duplicação. |
| INT-WEB-PEOPLE-005 | REQUIRED | [people-catalog.md](wireframes/people-catalog.md) | Ações contextuais e confirmação destrutiva. |
| INT-WEB-PEOPLE-006 | REQUIRED | [person-form.md](wireframes/person-form.md) | Formulário mínimo em diálogo/sheet. |

## Validation Summary

- Coverage matrix reviewed: yes.
- All inventory items detailed: yes.
- Canonical states resolved: yes.
- Required wireframes present: yes.
- Accessibility requirements resolved: yes.
- Contract mappings verified: yes.
- Placeholders or open decisions remaining: 0.
