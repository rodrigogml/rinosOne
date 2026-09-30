# Interface Specification: Compatibilidade de Schemas e Guarda Operacional

**Feature**: `schema-compatibility-guard`
**Criada em**: 2026-09-30
**Status**: Pronta para tarefas
**Spec**: [spec.md](spec.md)
**Plano**: [plan.md](plan.md)
**Catálogo de superfícies**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Tipo | Usuários | Cobertura | Escopo incluído | Escopo adiado ou excluído |
| --- | --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | WEB | Pessoas visitantes e autenticadas | FULL | Indisponibilidade global segura e indisponibilidade contextual de organização, em desktop, tablet e telefone. | Diagnóstico técnico, gestão de migrations ou execução de deploy pela interface. |
| SURF-WEB-PEOPLE | WEB | Usuários autorizados de organização | PARTIAL | Reage ao bloqueio contextual comum quando Pessoas estiver aberta. | Tela específica de recuperação de Pessoas. |
| SURF-WEB-DRIVE | WEB | Usuários autenticados e autorizados de organização | PARTIAL | Reage ao bloqueio contextual comum quando Drive Work estiver aberto. | Tela específica de recuperação de Drive. |
| SURF-FUTURE-CONSUMERS | API | Consumidores futuros autorizados | DEFERRED | Nenhuma interface humana nesta entrega; o contrato HTTP é definido separadamente. | Cliente ou paridade visual de integrações futuras. |

## Current-State Evidence

| Surface ID | Rota, comando ou componente existente | Evidência | Comportamento atual |
| --- | --- | --- | --- |
| SURF-WEB-ACCESS | `bootstrap/app.php`, `resources/js/design-system/WorkspaceShell.vue` | A aplicação registra middleware e a shell monta a área autenticada; não há guarda global nem apresentação de incompatibilidade. | O runtime pode renderizar e atender funções mesmo com migration global pendente. |
| SURF-WEB-ACCESS | `resources/js/tenant/tenantContextStore.ts`, `resources/js/design-system/TenantSelector.vue` | O contexto é iniciado por contrato remoto e a navegação é derivada de capabilities. | Uma organização ativa pode ser selecionada sem validar integralmente seu catálogo de migrations. |
| SURF-WEB-PEOPLE / SURF-WEB-DRIVE | `resources/js/people/`, `resources/js/drive/` | Ambas as superfícies consomem o contexto organizacional atual. | Não distinguem indisponibilidade por atualização de outros erros remotos. |

## Interaction Inventory

| Interaction ID | Surface ID | Tipo | Mudança | Nome | Ponto de entrada |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-SCHEMA-001 | SURF-WEB-ACCESS | SCREEN | NEW | Indisponibilidade global por atualização | Qualquer página funcional enquanto a plataforma estiver incompatível. |
| INT-WEB-SCHEMA-002 | SURF-WEB-ACCESS | CONTROL / NOTIFICATION | MODIFIED | Organização temporariamente indisponível | Seletor de organização, início de contexto ou operação contextual já aberta. |
| INT-WEB-PEOPLE-001 | SURF-WEB-PEOPLE | NOTIFICATION / STATE | MODIFIED | Bloqueio contextual de Pessoas | Resposta contextual durante uma consulta ou ação de Pessoas. |
| INT-WEB-DRIVE-001 | SURF-WEB-DRIVE | NOTIFICATION / STATE | MODIFIED | Bloqueio contextual do Drive Work | Resposta contextual durante uma consulta ou ação do Drive Work. |

## Interaction Details

### INT-WEB-SCHEMA-001 — Indisponibilidade global por atualização

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: informar que a plataforma está temporariamente indisponível por atualização e impedir qualquer operação funcional enquanto a compatibilidade global não puder ser comprovada.
**Actors and Permissions**: qualquer visitante ou pessoa autenticada; não exige, solicita nem revela identidade.
**Entry and Navigation**: substitui a página funcional solicitada quando a guarda global negar o atendimento. Não preserva nem restaura navegação, formulários ou contexto organizacional enquanto o bloqueio existir. Atualizar a página é a única tentativa de recuperação disponível para a pessoa usuária.
**Content and Data**: marca da plataforma; título “Atualização em andamento”; mensagem curta de indisponibilidade temporária; ação “Tentar novamente”. Não contém menu, conteúdo autenticado, identificador de organização, dados pessoais, versão, migration, horário estimado ou contato técnico.
**Actions and Behavior**: “Tentar novamente” solicita novamente a página atual. Se a plataforma já estiver compatível, a navegação normal retorna; se não estiver, a mesma tela é mantida. Não há logout, troca de tenant ou disparo de tarefa por esta tela.
**Validation and Feedback**: não há campos. Falha ao carregar a tentativa mantém a tela e apresenta somente a mensagem segura já visível; nenhum erro de rede ou banco é exibido literalmente.
**Responsive/Adaptive Behavior**: em desktop, conteúdo centralizado em coluna com largura de leitura moderada. Em tablet e telefone, ocupa largura segura com margens do design system, respeita área segura, zoom e teclado virtual; o botão ocupa largura confortável e mantém alvo de toque adequado.
**Accessibility**: landmark principal único, título `h1`, foco inicial no título ao entrar, descrição conectada ao título e botão nomeado. Não usa cor como único indicador; contraste e foco seguem tokens existentes. A repetição de tentativa anuncia alteração somente quando o estado efetivamente mudar; movimentos decorativos são omitidos com redução de movimento.
**Localization**: mensagens e ação pertencem aos quatro idiomas já suportados. O mesmo código de indisponibilidade pode ter texto localizado, mas nunca é exibido como diagnóstico técnico.
**Components and Design System**: reutiliza marca, tokens de tema, tipografia, botão e superfícies existentes; cria apenas uma composição de página de indisponibilidade, sem segunda casca de navegação.
**Integration and Contracts**: é a representação web de `PLATFORM_SCHEMA_INCOMPATIBLE` definido em [schema-compatibility.md](contracts/schema-compatibility.md). Não usa cache de conteúdo funcional enquanto o bloqueio estiver ativo.
**Telemetry**: contabiliza exibição e nova tentativa por categoria de indisponibilidade, sem URL completa, identidade, dados de formulário ou detalhe de migration.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-schema-001.md

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | N/A — a tela é exibida somente após bloqueio confirmado. | — | loading. |
| loading | Marca e estrutura da página; nenhuma função de negócio é montada. | Nenhuma até obter decisão. | ready ou remote-error seguro. |
| empty | N/A — não há coleção ou conteúdo vazio. | — | — |
| ready | Título, mensagem segura e “Tentar novamente”. | Tentar novamente. | processing. |
| processing | Botão informa nova tentativa e evita repetição simultânea. | Nenhuma até concluir a tentativa. | success ou remote-error. |
| success | N/A — a recuperação navega para a página funcional solicitada. | — | Saída da tela. |
| validation-error | N/A — não há entrada de usuário. | — | — |
| remote-error | Mantém a mesma mensagem segura, sem diagnóstico adicional. | Tentar novamente. | processing. |
| offline | Mantém mensagem de indisponibilidade e informa que a conexão será necessária para tentar novamente, sem ocultar o botão. | Tentar novamente quando a conexão retornar. | processing. |
| access-denied | N/A — não há decisão de autorização nesta tela. | — | — |
| partial-stale | N/A — conteúdo funcional não é exibido nem reutilizado. | — | — |

### INT-WEB-SCHEMA-002 — Organização temporariamente indisponível

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: impedir o uso de uma organização com schema incompatível sem interromper o espaço pessoal ou organizações compatíveis, e explicar a condição em linguagem segura.
**Actors and Permissions**: pessoa autenticada que possui associação com uma organização. A disponibilidade é verificada antes de qualquer capability ou dado contextual ser usado.
**Entry and Navigation**: aparece no seletor ao tentar iniciar um contexto incompatível e como notificação bloqueante quando uma operação contextual aberta recebe `TENANT_SCHEMA_UNAVAILABLE`. A pessoa pode fechar a mensagem, permanecer no espaço pessoal ou selecionar outra organização compatível. Não há deep link nem ação de recuperação de schema.
**Content and Data**: nome de exibição da organização somente quando ela já é visível à pessoa pelo seletor; estado textual “Temporariamente indisponível para atualização”; mensagem curta; ações “Entendi” e, no seletor, “Escolher outra organização”. Não mostra migrations, versão, schema, operador, tentativa ou causa técnica.
**Actions and Behavior**: a opção incompatível não abre módulos. Ao receber bloqueio contextual com uma organização já ativa, a shell invalida o contexto local, encerra superfícies organizacionais daquela aba com aviso de trabalho não salvo quando aplicável e mantém superfícies pessoais. A seleção de outra organização inicia validação remota normal.
**Validation and Feedback**: a lista de organizações não é evidência de autorização; uma tentativa sempre aguarda a resposta atual. O código `TENANT_SCHEMA_UNAVAILABLE` tem tratamento próprio, sem ser confundido com acesso negado, offline ou inexistência. Respostas desconhecidas preservam a classificação de erro remoto existente.
**Responsive/Adaptive Behavior**: em desktop, o estado aparece no item do seletor e a notificação usa área não obstrutiva da shell. Em telefone, o seletor abre como folha modal; a mensagem fica antes da lista de ações, com botões em coluna e foco sem ficar atrás do teclado virtual. Operável por mouse, toque e teclado físico.
**Accessibility**: o estado é texto, não apenas ícone ou cor. A mensagem dinâmica usa região de status; se a organização ativa for removida do contexto, o foco vai para o aviso e retorna ao acionador de organização ou à próxima superfície pessoal segura ao fechar. O diálogo móvel mantém foco contido e Escape/voltar fecham sem selecionar organização.
**Localization**: estado, mensagem e ações são localizados nos quatro idiomas; o nome da organização preserva grafia original. Textos permitem expansão de idioma sem truncar a ação principal.
**Components and Design System**: reutiliza seletor de organização, alerta/notificação da shell, diálogo móvel, botões e tokens de status existentes. Não cria novo menu de administração ou painel técnico.
**Integration and Contracts**: interpreta `TENANT_SCHEMA_UNAVAILABLE` de [schema-compatibility.md](contracts/schema-compatibility.md). A limpeza do contexto usa os mesmos limites da troca e encerramento de contexto já definidos pela fundação de tenants.
**Telemetry**: registra somente categoria de indisponibilidade, ponto de entrada (seletor ou operação contextual) e resultado de limpeza; exclui nome, schema, migration, conteúdo aberto e dados de formulário.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-schema-002.md

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | Seletor e contexto seguem o comportamento atual até uma decisão de indisponibilidade. | Selecionar organização. | loading ou ready. |
| loading | Tentativa contextual em curso; não troca o contexto antecipadamente. | Fechar seletor quando seguro. | ready, access-denied, remote-error ou schema-unavailable. |
| empty | N/A — a indisponibilidade pertence a uma organização conhecida; lista vazia usa o estado existente do seletor. | — | — |
| ready | Organização compatível permanece selecionável; incompatível exibe estado textual e não abre módulos. | Escolher organização compatível, fechar. | processing ou initial. |
| processing | Ao limpar contexto ativo, interações organizacionais daquela aba não recebem nova entrada. | Aguardar; confirmar descarte quando houver trabalho local não salvo. | success ou remote-error. |
| success | Contexto incompatível foi removido e o aviso confirma retorno ao espaço pessoal ou outra organização ativa. | Continuar. | initial ou ready. |
| validation-error | N/A — não há campo editável. | — | — |
| remote-error | Alerta seguro da falha remota existente, sem assumir incompatibilidade. | Tentar novamente, fechar. | loading ou initial. |
| offline | Indica indisponibilidade de rede, preservando o último contexto comprovadamente válido até nova operação. | Fechar e tentar depois. | loading. |
| access-denied | Segue contrato existente para acesso negado; não menciona atualização. | Escolher outra organização, fechar. | ready ou initial. |
| partial-stale | O contexto e suas superfícies são removidos; nenhuma capacidade, lista ou dado contextual antigo permanece utilizável. | Espaço pessoal e seleção de outra organização. | initial ou ready. |

### INT-WEB-PEOPLE-001 — Bloqueio contextual de Pessoas

**Surface**: SURF-WEB-PEOPLE
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: encerrar com segurança a utilização da superfície de Pessoas quando a organização atual se tornar incompatível, sem apresentar erro técnico nem permitir nova gravação.
**Actors and Permissions**: usuário que já possuía acesso a Pessoas na organização; a indisponibilidade prevalece sobre as capabilities exibidas.
**Entry and Navigation**: resposta `TENANT_SCHEMA_UNAVAILABLE` em qualquer leitura ou ação de Pessoas. A superfície delega limpeza de contexto a INT-WEB-SCHEMA-002 e retorna ao espaço pessoal seguro.
**Content and Data**: notificação contextual comum; não reapresenta dados de Pessoas nem dados pendentes após a invalidação.
**Actions and Behavior**: interrompe ações em andamento, evita novo envio e aciona a mesma confirmação de trabalho local não salvo já prevista pela shell.
**Validation and Feedback**: o código contextual recebe tratamento próprio; erros de validação ou concorrência existentes não são reclassificados.
**Responsive/Adaptive Behavior**: em todos os form factors usa o aviso e a limpeza contextual definidos em INT-WEB-SCHEMA-002.
**Accessibility**: a notificação é textual, anunciada e leva o foco a uma área segura depois de fechar a superfície.
**Localization**: reutiliza as mensagens localizadas de INT-WEB-SCHEMA-002.
**Components and Design System**: reutiliza a notificação e a shell existentes; não cria componente exclusivo de Pessoas.
**Integration and Contracts**: [schema-compatibility.md](contracts/schema-compatibility.md), código `TENANT_SCHEMA_UNAVAILABLE`.
**Telemetry**: registra somente a categoria e a superfície `people`, sem dados de Pessoa.
**Wireframe Requirement**: N/A
**Wireframe**: N/A — usa o aviso e fluxo de INT-WEB-SCHEMA-002.

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | Superfície de Pessoas segue o contexto válido. | Operações autorizadas. | loading ou ready. |
| loading | Consulta ou ação contextual em andamento. | Conforme operação atual. | ready, remote-error ou schema-unavailable. |
| empty | N/A — a lista vazia de Pessoas não muda. | — | — |
| ready | Dados somente enquanto o contexto estiver válido. | Operações autorizadas. | processing ou schema-unavailable. |
| processing | Nova gravação é bloqueada ao reconhecer indisponibilidade. | Confirmar descarte quando aplicável. | success. |
| success | Contexto é limpo e a superfície é fechada. | Espaço pessoal ou outra organização. | initial. |
| validation-error | Preserva contrato de validação normal quando não houver bloqueio. | Corrigir dados. | processing. |
| remote-error | Preserva erro remoto normal se o código não for de compatibilidade. | Tentar novamente. | loading. |
| offline | Preserva comportamento offline existente, sem inferir incompatibilidade. | Tentar novamente. | loading. |
| access-denied | Preserva contrato de autorização existente. | Fechar. | initial. |
| partial-stale | Nenhum dado de Pessoa do contexto removido permanece acionável. | Nenhuma ação contextual. | success. |

### INT-WEB-DRIVE-001 — Bloqueio contextual do Drive Work

**Surface**: SURF-WEB-DRIVE
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: encerrar com segurança o Drive Work da organização incompatível sem afetar o Drive pessoal ou outras organizações.
**Actors and Permissions**: usuário com acesso contextual ao Drive Work; indisponibilidade de schema prevalece sobre permissões e compartilhamentos.
**Entry and Navigation**: resposta `TENANT_SCHEMA_UNAVAILABLE` em leitura, upload, download, exportação ou outra ação contextual. O fluxo delega a limpeza a INT-WEB-SCHEMA-002.
**Content and Data**: notificação contextual comum; referências e seleção de arquivos daquele contexto deixam de ser utilizáveis.
**Actions and Behavior**: interrompe a ação contextual, evita repetição e fecha somente a superfície do Drive Work afetada; o Drive pessoal permanece aberto quando presente.
**Validation and Feedback**: o código contextual não é confundido com erro de arquivo, quota ou autorização de compartilhamento.
**Responsive/Adaptive Behavior**: em desktop, tablet e telefone usa o mesmo aviso, foco e retorno seguro definidos para INT-WEB-SCHEMA-002.
**Accessibility**: status textual anunciado; foco deixa uma seleção de arquivo inválida e vai ao aviso/acionador seguro.
**Localization**: reutiliza mensagens localizadas de indisponibilidade contextual.
**Components and Design System**: reutiliza shell, notificação e mecanismos existentes de limpeza de superfície.
**Integration and Contracts**: [schema-compatibility.md](contracts/schema-compatibility.md), código `TENANT_SCHEMA_UNAVAILABLE`.
**Telemetry**: registra somente a categoria e a superfície `drive-work`, sem nomes, caminhos ou conteúdo de arquivo.
**Wireframe Requirement**: N/A
**Wireframe**: N/A — usa o aviso e fluxo de INT-WEB-SCHEMA-002.

**States**:

| Estado | Apresentação esperada | Ações disponíveis | Transição/saída |
| --- | --- | --- | --- |
| initial | Drive Work disponível somente com contexto válido. | Navegar conforme autorização. | loading ou ready. |
| loading | Operação contextual em curso. | Conforme a operação atual. | ready, remote-error ou schema-unavailable. |
| empty | N/A — estado de pasta vazia não muda. | — | — |
| ready | Conteúdo organizacional visível somente em contexto válido. | Operações autorizadas. | processing ou schema-unavailable. |
| processing | Operação deixa de aceitar nova entrada ao reconhecer bloqueio. | Confirmar descarte quando aplicável. | success. |
| success | Drive Work afetado fecha; Drive pessoal não é alterado. | Continuar em espaço pessoal ou outra organização. | initial. |
| validation-error | N/A — o bloqueio não acrescenta validação de formulário. | — | — |
| remote-error | Erro não relacionado preserva o comportamento do Drive. | Tentar novamente. | loading. |
| offline | Preserva comportamento offline normal, sem inferir schema incompatível. | Tentar novamente. | loading. |
| access-denied | Preserva contrato de autorização do Drive. | Fechar. | initial. |
| partial-stale | Arquivos, seleção e ações do contexto removido não permanecem acionáveis. | Nenhuma ação contextual. | success. |

## Regras entre superfícies

### Navegação e paridade

A mesma regra de bloqueio contextual alcança Pessoas, Drive Work e qualquer superfície futura de organização por meio da resolução comum de contexto. Não haverá tela de recuperação específica por módulo. Recursos pessoais permanecem operáveis durante indisponibilidade de uma organização; a indisponibilidade global substitui toda a experiência funcional.

### Conteúdo e terminologia

A interface usa “atualização em andamento” para indisponibilidade global e “organização temporariamente indisponível para atualização” para o escopo organizacional. Termos como schema, migration, banco, versão e deploy não aparecem para usuários finais.

### Acessibilidade e entradas compartilhadas

Todas as mensagens dinâmicas são textuais e anunciadas; os fluxos funcionam por teclado, mouse e toque. A ordem de foco e o retorno de foco preservam o contexto pessoal seguro. A interface não depende apenas de ícones, cor ou animação para comunicar indisponibilidade.

## Traceability

| Interaction ID | Histórias | Requisitos funcionais | Critérios de sucesso | Contratos |
| --- | --- | --- | --- | --- |
| INT-WEB-SCHEMA-001 | 1, 5 | FR-SCG-001, 002, 003, 004, 014, 015 | SC-SCG-001, 005, 006 | schema-compatibility.md |
| INT-WEB-SCHEMA-002 | 2, 3, 4, 5 | FR-SCG-005, 006, 007, 008, 009, 010, 011, 012, 015 | SC-SCG-002, 003, 004, 005, 006 | schema-compatibility.md |
| INT-WEB-PEOPLE-001 | 2, 3 | FR-SCG-005, 006, 007, 009, 015 | SC-SCG-002, 005, 006 | schema-compatibility.md |
| INT-WEB-DRIVE-001 | 2, 3 | FR-SCG-005, 006, 007, 009, 015 | SC-SCG-002, 005, 006 | schema-compatibility.md |

## Wireframes

| Interaction ID | Requisito | Artefato | Notas |
| --- | --- | --- | --- |
| INT-WEB-SCHEMA-001 | REQUIRED | [int-web-schema-001.md](wireframes/int-web-schema-001.md) | Página global segura, sem navegação funcional. |
| INT-WEB-SCHEMA-002 | REQUIRED | [int-web-schema-002.md](wireframes/int-web-schema-002.md) | Seletor e aviso contextual em desktop e telefone. |

## Validation Summary

- Matriz de cobertura revisada: sim.
- Todos os itens do inventário detalhados: sim.
- Estados canônicos resolvidos: sim.
- Wireframes obrigatórios presentes: sim.
- Requisitos de acessibilidade resolvidos: sim.
- Mapeamentos de contrato verificados: sim.
- Placeholders ou decisões em aberto: 0.
