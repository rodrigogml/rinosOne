# Interface Checklist: Perfil do usuário

**Purpose**: validar a cobertura e qualidade do contrato de interação web responsivo de Perfil e do editor de avatar.  
**Created**: 2026-09-26  
**Feature**: [spec.md](../spec.md)

## Cobertura e navegação

- [x] CHK001 - A única superfície humana entregue está classificada como `FULL` e o consumidor futuro como `DEFERRED`, sem paridade implícita? [Completude, Interface §Interface Coverage; Spec §Cobertura de Interfaces] {auto}
- [x] CHK002 - A alteração de Configurações existente identifica componente atual, comportamento vigente e ponto de entrada para a nova seção Perfil? [Rastreabilidade, Interface §Current-State Evidence; Interface §INT-WEB-001] {auto}
- [x] CHK003 - Perfil permanece uma seção da janela única de Configurações, sem criar arquitetura paralela de navegação ou nova área de trabalho? [Consistência, Interface §INT-WEB-001 Entry and Navigation; Plan §Contrato e superfície] {auto}
- [x] CHK004 - A organização de desktop, tablet e telefone define reflow sem permitir overflow horizontal nem scroll do canvas geral? [Responsividade, Interface §INT-WEB-001 Responsive/Adaptive Behavior; Spec §SC-PROFILE-004] {auto}

## Interação, estados e feedback

- [x] CHK005 - Cada interação possui ID estável, ponto de entrada, permissões, ações, feedback e mapeamento ao contrato? [Completude, Interface §Interaction Inventory; Interface §INT-WEB-001; Interface §INT-WEB-002] {auto}
- [x] CHK006 - Todos os estados canônicos são definidos ou justificados como N/A, preservando dados locais em erro de validação, rede ou processamento? [Cobertura, Interface §INT-WEB-001 States; Interface §INT-WEB-002 States] {auto}
- [x] CHK007 - Nome sem alteração, mudança de seção com edição pendente e remoção de imagem têm regras inequívocas de confirmação e descarte? [Clareza, Interface §INT-WEB-001 Actions and Behavior] {auto}
- [x] CHK008 - O editor impede persistência parcial, bloqueia envios duplicados e mantém imagem/enquadramento enquanto a falha permitir nova tentativa? [Cobertura, Interface §INT-WEB-002 Actions and Behavior; Spec §FR-PROFILE-006 e FR-PROFILE-008] {auto}

## Acessibilidade, localização e design system

- [x] CHK009 - Foco, teclado, leitor de tela, movimento reduzido, contraste e toque estão definidos tanto para a seção quanto para o diálogo de recorte? [Acessibilidade, Interface §INT-WEB-001 Accessibility; Interface §INT-WEB-002 Accessibility] {auto}
- [x] CHK010 - O recorte oferece alternativas de teclado e botões para arrastar/pinçar, sem depender de gesto ou cor? [Acessibilidade, Interface §INT-WEB-002 Responsive/Adaptive Behavior e Accessibility] {auto}
- [x] CHK011 - Os quatro idiomas preservam a edição em curso e os textos deixam claro que a imagem permanece privada? [Localização, Interface §INT-WEB-001 Localization; Interface §INT-WEB-002 Localization; Spec §FR-PROFILE-010 e FR-PROFILE-011] {auto}
- [x] CHK012 - Componentes novos são reutilizáveis e os existentes são referenciados por responsabilidade, sem criar controles exclusivos evitáveis? [Consistência, Interface §INT-WEB-001 Components and Design System; Interface §INT-WEB-002 Components and Design System] {auto}

## Wireframes e rastreabilidade

- [x] CHK013 - As alterações estruturais possuem wireframes obrigatórios de desktop e telefone coerentes com o texto, mantendo o texto como fonte de verdade? [Completude, Interface §Wireframes; wireframes/int-web-001.md; wireframes/int-web-002.md] {auto}
- [x] CHK014 - Cada interação mapeia stories, requisitos, critérios de sucesso e contrato HTTP, completando a cadeia até a etapa de tarefas? [Rastreabilidade, Interface §Traceability] {auto}

## Notas

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Não há gaps, ambiguidades ou conflitos abertos neste domínio.
