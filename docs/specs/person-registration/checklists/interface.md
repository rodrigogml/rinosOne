# Interface Checklist: Cadastro de Pessoas por Organização

**Purpose**: verificar cobertura, estados, responsividade, acessibilidade e rastreabilidade do contrato de interface.  
**Created**: 2026-09-28  
**Feature**: [interface-spec.md](../interface-spec.md)

## Cobertura e inventário

- [x] CHK-INT-001 - A única superfície humana aprovada possui cobertura `FULL` e inclui todas as operações em desktop, tablet e telefone? [Completude, Interface §Interface Coverage; Interface §Cross-Surface Rules] {auto}
- [x] CHK-INT-002 - Cada fluxo novo possui `INT-*` estável, tipo de mudança e ponto de entrada compatível com a Área de Trabalho? [Rastreabilidade, Interface §Interaction Inventory; Interface §Current-State Evidence] {auto}
- [x] CHK-INT-003 - O estado atual identifica que Pessoas ainda não possui destino, menu ou superfície, evitando tratar uma tela inexistente como alteração? [Completude, Interface §Current-State Evidence] {auto}
- [x] CHK-INT-004 - Catálogo, formulário, coleções, duplicação, ciclo de vida e cadastro rápido possuem interações separadas e detalhadas? [Cobertura, Interface INT-WEB-PEOPLE-001 a INT-WEB-PEOPLE-006] {auto}

## Estados e feedback

- [x] CHK-INT-005 - Cada interação define ou justifica os estados initial, loading, empty, ready, processing, success, validation-error, remote-error, offline, access-denied e partial-stale? [Cobertura, Interface INT-WEB-PEOPLE-001 a INT-WEB-PEOPLE-006] {auto}
- [x] CHK-INT-006 - Os fluxos de formulário e coleções preservam valores digitados em erros remotos e de validação? [Clareza, Interface INT-WEB-PEOPLE-002 e INT-WEB-PEOPLE-003] {auto}
- [x] CHK-INT-007 - Exclusão física, diagnóstico de uso e falha de integridade possuem mensagens seguras e confirmação destrutiva explícita? [Segurança de interação, Interface INT-WEB-PEOPLE-005; Contract §Exclusão física] {auto}
- [x] CHK-INT-008 - O catálogo define estado vazio, dados potencialmente desatualizados, atualização e perda de acesso sem expor dados de outra organização? [Cobertura, Interface INT-WEB-PEOPLE-001] {auto}

## Responsividade e acessibilidade

- [x] CHK-INT-009 - As adaptações de tabela para cards, painel para sheet e formulário para uma coluna preservam ações e dados funcionais? [Paridade, Interface §Cross-Surface Rules; Wireframes] {auto}
- [x] CHK-INT-010 - A especificação cobre teclado, toque, ponteiro, foco, leitor de tela, nomes acessíveis, contraste, zoom, movimento reduzido e teclado virtual? [Acessibilidade, Interface §Shared Accessibility and Input; Interface INT-WEB-PEOPLE-001 a INT-WEB-PEOPLE-006] {auto}
- [x] CHK-INT-011 - Mensagens, status, pluralização e dados sensíveis em feedback/telemetria têm tratamento localizado e seguro? [Localização, Interface §Shared Content and Terminology; Interface INT-WEB-PEOPLE-001 a INT-WEB-PEOPLE-006] {auto}

## Wireframes e contratos

- [x] CHK-INT-012 - Cada interação nova que altera estrutura ou navegação possui wireframe de baixa fidelidade existente e o texto continua fonte de verdade? [Rastreabilidade, Interface §Wireframes; wireframes/people-catalog.md; wireframes/person-form.md] {auto}
- [x] CHK-INT-013 - Toda interação mapeia ao menos uma história/requisito, critério de sucesso e contrato aplicável? [Rastreabilidade, Interface §Traceability] {auto}
- [x] CHK-INT-014 - As consultas de catálogo de localidades não impedem rua textual e não antecipam uma tela de CEP fora desta feature? [Limite de escopo, Interface INT-WEB-PEOPLE-003; Spec FR-010 e FR-011] {auto}

## Notes

- Não há gap aberto de interação, estado, responsividade ou wireframe.
- O comportamento de edição concorrente está definido para resposta de conflito e recarga; os estados visuais `partial-stale` permanecem alinhados ao contrato.
