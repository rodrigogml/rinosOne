# Interface Checklist: Área de Trabalho da Aplicação

**Propósito**: validar a completude, clareza, consistência e rastreabilidade dos requisitos das superfícies humanas da Área de trabalho antes da decomposição em tarefas.
**Criada em**: 2026-09-24
**Feature**: [spec.md](../spec.md)

## Cobertura e inventário

- [x] CHK001 - Todas as superfícies declaradas possuem uma cobertura explícita, incluindo o escopo postergado? [Completude, Spec §Cobertura de Interfaces] {auto}
- [x] CHK002 - Cada tela, painel e camada global criada ou modificada possui um identificador `INT-*` estável e uma responsabilidade delimitada? [Rastreabilidade, Interface §Interaction Inventory] {auto}
- [x] CHK003 - A remoção do conteúdo demonstrativo atual define claramente o destino de navegação e evita preservar uma funcionalidade fictícia? [Cobertura, Spec §Requisitos Funcionais FR-WS-001; Interface §INT-WEB-WORKSPACE-001] {auto}
- [x] CHK004 - As capacidades excluídas nesta fase — módulos de negócio, permissões detalhadas, restauração entre sessões e janelas livres em telefone — estão declaradas sem conflito com a solução proposta? [Consistência, Spec §Fora de Escopo; Plan §Escopo e Exclusões] {auto}
- [x] CHK005 - Cada interação cuja compreensão depende de representação visual aponta para wireframe coerente, mantendo o texto como fonte de verdade? [Completude, Interface §Wireframes; Interface §Validation Summary] {auto}

## Estados, contexto e ciclo de vida

- [x] CHK006 - Cada interação define ou justifica como não aplicável os estados inicial, carregando, vazio, pronto, processando, sucesso, erro de validação, erro remoto, offline, acesso negado e estado parcial desatualizado? [Cobertura, Interface §INT-WEB-WORKSPACE-001—004] {auto}
- [x] CHK007 - Os requisitos estabelecem que a coleção de superfícies é isolada por aba, efêmera e não restaurada após recarregamento, sem depender de estado implícito? [Clareza, Spec §Requisitos Funcionais FR-WS-006 e FR-WS-015; Plan §Decisões Técnicas] {auto}
- [x] CHK008 - A regra de abertura diferencia inequivocamente destino de instância única e de instâncias múltiplas, incluindo o padrão aplicado quando o catálogo não especificar a política? [Clareza, Spec §Requisitos Funcionais FR-WS-007; Contrato §Catálogo de destinos] {auto}
- [x] CHK009 - A troca, perda ou invalidação de organização estabelece o fechamento somente das superfícies contextuais e preserva as pessoais na mesma aba? [Cobertura, Spec §Direção de Produto e FR-WS-014; Contrato §Comandos do runtime] {auto}
- [x] CHK010 - O fluxo de alterações pendentes descreve separadamente pedido de fechamento, cancelamento, descarte confirmado, foco e remoção de interação residual? [Cobertura, Spec §User Story 3; Interface §INT-WEB-WORKSPACE-003] {auto}

## Navegação, adaptação e acessibilidade

- [x] CHK011 - A navegação especifica o filtro de destinos pessoais e contextuais sem usar o identificador, nome ou estado visual da organização como substituto de escopo? [Consistência, Spec §Direção de Produto; Contrato §Catálogo de destinos] {auto}
- [x] CHK012 - O rail recolhível e o mega menu definem abertura, substituição, fechamento, retorno de foco, ausência de destinos e elegibilidade que muda durante o uso? [Completude, Interface §INT-WEB-WORKSPACE-002] {auto}
- [x] CHK013 - Desktop e telefone preservam os mesmos destinos autorizados e a mesma coleção por aba, com diferenças de composição explicitamente documentadas? [Consistência, Spec §User Story 5; Interface §Cross-Surface Rules] {auto}
- [x] CHK014 - A especificação de telefone cobre painéis, safe areas, teclado virtual, orientação, rolagem interna, sobreposições concorrentes e ausência de uma ação falsa para lista vazia? [Cobertura, Interface §INT-WEB-WORKSPACE-004] {auto}
- [x] CHK015 - Os requisitos de acessibilidade definem semântica, foco, teclado, escape, leitor de tela, toque, contraste sem depender só de cor/ícone e redução de movimento para todos os fluxos críticos? [Cobertura, Interface §INT-WEB-WORKSPACE-001—004; Interface §Cross-Surface Rules] {auto}
- [x] CHK016 - O conteúdo, os rótulos acessíveis, pluralização e expansão de texto estão previstos para os quatro idiomas suportados sem truncar a identificação essencial? [Cobertura, Interface §INT-WEB-WORKSPACE-001—004] {auto}

## Contratos e composição reutilizável

- [x] CHK017 - O contrato interno especifica a fonte de verdade para catálogo, identidade, foco, fechamento, invalidação e camadas globais sem criar endpoint, payload ou persistência inexistentes? [Consistência, Contrato §Catálogo de destinos; Contrato §Contrato de superfície; Plan §Contratos] {auto}
- [x] CHK018 - As responsabilidades de componentes globais reutilizáveis — navegação, palco, taskbar, sobreposições e notificações — estão separadas de conteúdos futuros de módulos? [Clareza, Plan §Estrutura de Projeto; Interface §Components and Design System] {auto}
- [x] CHK019 - Diálogos e notificações possuem regras distintas de bloqueio, empilhamento, foco, retorno à origem e fila, evitando que cada superfície defina uma camada global concorrente? [Completude, Spec §User Story 4; Interface §INT-WEB-WORKSPACE-003; Contrato §Contrato de superfície] {auto}
- [x] CHK020 - A integração preserva a top bar, marca, avatar e seletor de organização existentes, sem introduzir rota, sessão ou contrato HTTP adicional nesta entrega? [Consistência, Plan §Arquitetura; Interface §Cross-Surface Rules; Interface §INT-WEB-WORKSPACE-004] {auto}

## Notas

- Os 20 itens `{auto}` foram resolvidos contra os artefatos da feature e incluem referência de evidência.
- Não foram identificados marcadores `[Gap]`, `[Ambiguity]` ou `[Conflict]` neste domínio.
- Este checklist avalia os requisitos; não valida a implementação.
