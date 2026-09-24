# Interface Checklist: Casca da Aplicação Autenticada

**Propósito**: validar cobertura, consistência, rastreabilidade e completude do contrato de interação antes de criar tarefas.  
**Criado**: 2026-09-24  
**Feature**: [spec.md](../spec.md)

## Cobertura e Inventário

- [x] CHK-INT-001 - A única superfície aprovada possui cobertura explícita e enumera capacidades incluídas e adiadas? [Completude, Interface §Interface Coverage] {auto} — `SURF-WEB-ACCESS` é FULL; módulos, perfil e imagem são excluídos explicitamente.
- [x] CHK-INT-002 - Toda alteração estrutural possui identificador estável e detalhe correspondente? [Rastreabilidade, Interface §Interaction Inventory] {auto} — a barra, o menu pessoal e o painel móvel possuem `INT-WEB-SHELL-001` a `003`.
- [x] CHK-INT-003 - O estado atual e a mudança desejada da interface existente estão separados? [Clareza, Interface §Current-State Evidence] {auto} — a composição atual e as ausências de barra, avatar e painéis estão registradas antes das mudanças.
- [x] CHK-INT-004 - O contrato impede itens de navegação ou funcionalidades de produto não aprovados? [Consistência, Spec §FR-012; Interface §INT-WEB-SHELL-003] {auto} — painel móvel sem destinos fictícios e sem mudança de rota.

## Comportamento e Estados

- [x] CHK-INT-005 - Cada interação define atores, entrada, dados, ações, feedback e saída sem depender de decisão durante implementação? [Completude, Interface §INT-WEB-SHELL-001–003] {auto} — todos os campos obrigatórios estão preenchidos em cada interação.
- [x] CHK-INT-006 - Todos os estados canônicos são definidos ou justificados quando não se aplicam? [Cobertura, Interface §States] {auto} — initial, loading, empty, ready, processing, success, validation-error, remote-error, offline, access-denied e partial-stale são resolvidos nos três itens.
- [x] CHK-INT-007 - A saída de sessão possui responsável, falha recuperável e efeito visual inequívocos? [Clareza, Interface §INT-WEB-SHELL-002; Plan §Fluxo de Estado] {auto} — menu emite intenção, aplicação raiz executa saída e falha não limpa sessão visual antecipadamente.
- [x] CHK-INT-008 - A regra de fallback do avatar cobre nome composto, único, uma letra, vazio e falha de imagem futura? [Cobertura, Spec §US-002; Interface §INT-WEB-SHELL-001] {auto} — todos os cinco casos estão declarados.

## Responsividade, Acessibilidade e Localização

- [x] CHK-INT-009 - Desktop, tablet e telefone têm regras intencionais de priorização e reflow? [Cobertura, Interface §INT-WEB-SHELL-001 e §INT-WEB-SHELL-003] {auto} — marca paisagem no desktop, adaptação no tablet e acionador lateral somente em telefone estão definidos.
- [x] CHK-INT-010 - Foco inicial, fechamento, Escape, retorno de foco e bloqueio de interação de fundo são definidos para cada sobreposição? [Acessibilidade, Interface §INT-WEB-SHELL-002–003] {auto} — menu e painel especificam todos esses comportamentos.
- [x] CHK-INT-011 - Cada ícone possui nome acessível e nenhum estado depende exclusivamente de ícone, cor ou bandeira? [Acessibilidade, Interface §Cross-Surface Rules; Spec §FR-013] {auto} — requisitos proíbem identificação exclusiva visual e exigem rótulos acessíveis.
- [x] CHK-INT-012 - Os quatro idiomas possuem cobertura para conteúdo e rótulos novos sem traduzir dados pessoais? [Localização, Spec §FR-014; Interface §Localization] {auto} — a cobertura e o tratamento do nome são explícitos.

## Contratos, Design System e Wireframes

- [x] CHK-INT-013 - O contrato de sessão é reutilizado sem duplicar ou alterar sua fonte de verdade? [Consistência, Plan §Convenções de Borda; Interface §Integration and Contracts] {auto} — somente `displayName` existente é consumido e a saída continua no fluxo aprovado.
- [x] CHK-INT-014 - Componentes novos e reutilizados estão associados ao design system e aos tokens existentes? [Rastreabilidade, Plan §Componentes e Estilos Compartilhados; Interface §Components and Design System] {auto} — todos os componentes previstos são listados com responsabilidade e reuso futuro.
- [x] CHK-INT-015 - Cada mudança de estrutura ou navegação possui wireframe coerente e o texto permanece fonte de verdade? [Completude, Interface §Wireframes] {auto} — há três wireframes requeridos, um por interação estrutural; a interface-spec mantém comportamento e estados como contrato.
- [x] CHK-INT-016 - Cada interação é rastreável até stories, requisitos, critérios de sucesso e contratos aplicáveis? [Rastreabilidade, Interface §Traceability] {auto} — a matriz contempla as três interações e distingue a ausência de contrato novo do painel móvel.

## Notas

- Todos os itens deste checklist foram resolvidos contra a especificação, o plano e o contrato de interface; nenhum valida implementação.
- Não há gaps, ambiguidades ou conflitos bloqueantes identificados.
