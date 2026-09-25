# UX Checklist: Área de Trabalho da Aplicação

**Propósito**: validar que os requisitos de experiência, interação, responsividade, visual e acessibilidade da Área de trabalho sejam suficientes para orientar uma implementação coerente e reutilizável.
**Criada em**: 2026-09-24
**Feature**: [spec.md](../spec.md)

## Hierarquia e previsibilidade da experiência

- [x] CHK001 - A hierarquia desktop identifica papéis permanentes de top bar, navegação, palco e taskbar sem apresentar um módulo de negócio fictício como conteúdo inicial? [Clareza, Spec §Direção de Produto; Interface §INT-WEB-WORKSPACE-001] {auto}
- [x] CHK002 - Os requisitos tornam discernível qual superfície está ativa e como alternar para outra, sem depender exclusivamente de cor, ícone ou posição? [Cobertura, Spec §User Story 2; Interface §INT-WEB-WORKSPACE-003] {auto}
- [x] CHK003 - O estado vazio orienta descoberta de forma discreta e não promete produto, módulo ou dados que ainda não pertencem ao escopo? [Consistência, Spec §Direção de Produto; Interface §INT-WEB-WORKSPACE-001] {auto}
- [x] CHK004 - O menu lateral define comportamento de abertura, fechamento, substituição de categoria e recolhimento que evita sobreposições acumuladas e ambiguidade de ícones? [Cobertura, Interface §INT-WEB-WORKSPACE-002] {auto}
- [x] CHK005 - A escolha dos termos de interface diferencia a metáfora produtiva de uma simulação literal de sistema operacional, sem invalidar o entendimento de usuário? [Clareza, Spec §Direção de Produto; Interface §Shared Content and Terminology] {auto}

## Feedback, erros e mudanças de contexto

- [x] CHK006 - Fechamento com alterações pendentes possui decisão explícita, preservação no cancelamento e retorno de foco ao originador, inclusive em teclado? [Cobertura, Spec §User Story 3; Interface §INT-WEB-WORKSPACE-003] {auto}
- [x] CHK007 - Mensagens globais distinguem aviso comum de erro crítico, preservam o foco da tarefa e definem fila para evitar competição visual? [Completude, Spec §User Story 4; Interface §INT-WEB-WORKSPACE-003; Contrato §Comandos do runtime] {auto}
- [x] CHK008 - Os requisitos de erro remoto, indisponibilidade e perda de contexto preservam a superfície anterior quando aplicável e evitam expor detalhes sensíveis ao usuário? [Cobertura, Interface §INT-WEB-WORKSPACE-001—004] {auto}
- [x] CHK009 - A mudança de organização especifica efeito compreensível sobre menus, painéis e superfícies contextuais, mantendo as opções pessoais acessíveis? [Consistência, Spec §Direção de Produto; Interface §INT-WEB-WORKSPACE-002 e §INT-WEB-WORKSPACE-004] {auto}

## Responsividade e entradas

- [x] CHK010 - As regras de adaptação estabelecem uma jornada de telefone própria — painéis e um palco em largura total — em vez de reduzir arbitrariamente a experiência desktop? [Cobertura, Spec §User Story 5; Interface §INT-WEB-WORKSPACE-004] {auto}
- [x] CHK011 - Painéis móveis definem fechamento acessível, proteção contra concorrência de sobreposições, área segura, rolagem e comportamento diante do teclado virtual? [Cobertura, Interface §INT-WEB-WORKSPACE-004] {auto}
- [x] CHK012 - Atalhos de alternância e fechamento têm escopo, prevenção em campos editáveis e compatibilidade com comandos assistivos definidos, sem substituírem os controles visíveis? [Clareza, Interface §INT-WEB-WORKSPACE-003; Interface §Shared Accessibility and Input] {auto}
- [x] CHK013 - Os controles interativos preveem teclado, ponteiro, toque, foco visível e alvos dimensionados pela escala de componentes reutilizáveis? [Cobertura, Interface §INT-WEB-WORKSPACE-001—004] {auto}

## Inclusão visual, movimento e localização

- [x] CHK014 - Os estados ativo, pendente, indisponível, tipo de notificação e erro contam com texto ou semântica complementar a cor e ícone? [Acessibilidade, Interface §INT-WEB-WORKSPACE-003 e §INT-WEB-WORKSPACE-004; Interface §Shared Accessibility and Input] {auto}
- [x] CHK015 - A redução de movimento está prevista para transições e painéis sem retirar o feedback essencial de estado? [Acessibilidade, Interface §INT-WEB-WORKSPACE-004; Plan §Design System] {auto}
- [x] CHK016 - A localização cobre quatro idiomas, rótulos visíveis e acessíveis, pluralização e expansão de títulos, inclusive em listas e taskbar? [Cobertura, Interface §INT-WEB-WORKSPACE-001—004] {auto}
- [x] CHK017 - A especificação vincula novos elementos aos tokens e componentes centrais, preservando tema, densidades e consistência visual da aplicação? [Consistência, Plan §Design System; Interface §Components and Design System] {auto}

## Notas

- Os 17 itens `{auto}` foram resolvidos contra os artefatos da feature e incluem referência de evidência.
- Não foram identificados marcadores `[Gap]`, `[Ambiguity]` ou `[Conflict]` neste domínio.
- Este checklist avalia qualidade de requisitos UX e não serve como teste de interface implementada.
