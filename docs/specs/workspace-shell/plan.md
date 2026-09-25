# Plano de Implementação: Área de Trabalho da Aplicação

## Resumo

Construir uma Área de trabalho responsiva abaixo da barra superior existente. A implementação cria uma fundação de navegação e superfícies por aba, sem módulos de negócio, persistência nova ou APIs novas. O shell controla o ciclo de vida de menus, superfícies, diálogos e notificações; módulos futuros entram por catálogo interno e não controlam a infraestrutura global diretamente.

## Contexto Técnico

| Item | Decisão |
| --- | --- |
| Linguagem e runtime web | TypeScript, Vue 3 e navegador moderno. |
| Estado local | Pinia, apenas em memória por aba. |
| Apresentação | Tokens CSS, componentes Vue próprios, temas, densidades e i18n existentes. |
| Localização | pt-BR, en, es e fr; novas chaves sincronizadas. |
| Integrações | Nenhuma API ou persistência nova nesta fase. |
| Testes | Formatação configurada pelo repositório; testes backend e frontend; Vitest para store/componentes; browser E2E para jornadas críticas; verificação de tipos e build de produção. |

## Constitution Check

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental | PASS | O catálogo inicia vazio e não cria módulo ou produto fictício. |
| II. Fronteira API | PASS | Não há regra de negócio ou contrato externo novo; futuros módulos continuam responsáveis por suas APIs. |
| III. Identidade segura | PASS | O shell consome somente a sessão já validada e limpa estado ao perdê-la. |
| IV. Dados mínimos | PASS | Não cria persistência, cookie, sessão adicional ou dados sensíveis. |
| V. Mudanças verificáveis | PASS | Plano prevê formatação, testes backend e frontend, unitários, componentes, E2E, tipos e build. |

## Arquitetura da Superfície de Interação

O `AuthenticatedFrame` passa a hospedar um `WorkspaceShell`, preservando `ApplicationTopBar`. O shell é composto por:

1. `WorkspaceNavigationRail`: categorias pessoais e contextuais, recolhível no desktop.
2. `WorkspaceMegaMenu`: painel ancorado na navegação, com grupos e destinos do catálogo filtrado.
3. `WorkspaceStage`: apresenta apenas a superfície ativa e preserva instâncias abertas no ciclo de vida da aba.
4. `WorkspaceTaskbar`: alterna e solicita o fechamento de superfícies abertas.
5. `WorkspaceOverlayHost`: gerencia diálogo de descarte, diálogos futuros e retorno de foco.
6. `WorkspaceNotificationHost`: apresenta uma única notificação por vez a partir de uma fila.
7. `WorkspaceMobilePanels`: adapta navegação e tarefas para painéis modais em tela estreita.

O `workspaceStore` é a autoridade da coleção de superfícies. Ele observa a mudança do contexto de organização e remove apenas superfícies contextuais da aba atual. O catálogo interno é a única fonte de destinos que podem entrar em menu ou abrir superfícies.

**Interface Design Applicability**: REQUIRED — a feature altera uma superfície humana complexa em desktop e telefone, incluindo foco, atalhos, estados e adaptação responsiva.

## Modelo de Estado

Consultar [data-model.md](data-model.md). O modelo é efêmero e não produz migration, alteração de banco ou contrato HTTP.

## Contratos

Consultar [workspace-runtime.md](contracts/workspace-runtime.md). O contrato interno separa o shell dos módulos futuros e estabelece instâncias, foco, descarte e invalidação contextual.

## Estrutura do Projeto

```text
resources/js/
├── design-system/
│   ├── AuthenticatedFrame.vue            # hospeda a Área de trabalho
│   ├── WorkspaceShell.vue                # composição estrutural
│   ├── WorkspaceNavigationRail.vue
│   ├── WorkspaceMegaMenu.vue
│   ├── WorkspaceStage.vue
│   ├── WorkspaceTaskbar.vue
│   ├── WorkspaceOverlayHost.vue
│   └── WorkspaceNotificationHost.vue
├── workspace/
│   ├── workspaceStore.ts                 # estado efêmero por aba
│   ├── workspaceTypes.ts                 # contratos internos
│   ├── workspaceCatalog.ts               # destinos aprovados
│   └── workspaceShortcuts.ts             # registro seguro de atalhos
└── i18n/messages/                        # quatro catálogos sincronizados

resources/css/design-system/
└── components.css                         # tokens e estilos do shell

tests/js/
├── workspace/                             # store e catálogo
└── design-system/                         # componentes e acessibilidade

tests/e2e/
└── workspace-shell.spec.ts                # desktop, telefone, foco e contexto
```

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Catálogo interno | camelCase | tipos TypeScript e testes | `workspaceCatalog.ts` e `workspaceTypes.ts` |
| Estado de superfícies | camelCase | store e testes unitários | `workspaceStore.ts` |
| Componentes | props e eventos em kebab-case no template | tipos Vue e testes de componente | componentes do shell |
| Textos visíveis | chaves hierárquicas | paridade dos quatro catálogos | `resources/js/i18n/messages/` |

**Mapper layer (DB <-> DTO)**: N/A — a feature não acessa banco nem introduz DTO HTTP.

**Validação de schema**: o catálogo e as superfícies são validados no cliente antes de entrarem no registro; não há request ou response novo.

## Sequência de Implementação

1. Remover o conteúdo demonstrativo autenticado e introduzir a composição neutra da Área de trabalho.
2. Criar tipos, catálogo vazio e store de superfícies com instância única, múltipla, ativação, fechamento e limpeza contextual.
3. Implementar o palco e a barra de tarefas desktop com foco, atalhos e recolhimento automático do menu.
4. Implementar menu lateral e mega menu com estados, clique externo e redução de movimento.
5. Implementar hosts reutilizáveis de diálogos e notificações, incluindo confirmação de descarte.
6. Adaptar navegação e tarefas ao telefone e integrar a troca de organização.
7. Acrescentar traduções, tokens, testes e validações finais.

## Cenários de Validação

Consultar [quickstart.md](quickstart.md). Não há roundtrip backend–frontend nesta feature, pois ela não cria endpoint nem payload novo.

## Complexity Tracking

| Decisão | Complexidade adicionada | Justificativa | Alternativa rejeitada |
| --- | --- | --- | --- |
| Registro de superfícies por aba | Store e ciclo de vida explícitos | Necessário para produtividade, instâncias e isolamento de organização. | Trocar toda a tela a cada destino. |
| Host central de overlays | Pilha e fila compartilhadas | Necessário para foco, acessibilidade e consistência entre módulos futuros. | Cada módulo criar suas próprias camadas globais. |
