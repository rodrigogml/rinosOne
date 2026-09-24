# Interface Checklist: Identidade Visual e Preferências de Interface

**Purpose**: validar completude, clareza, consistência e rastreabilidade do contrato das interações web antes da implementação.
**Created**: 2026-09-23
**Feature**: [spec.md](../spec.md)

## Cobertura e Inventário

- [x] CHK001 - A única superfície humana em escopo possui cobertura explícita e a superfície futura está declarada como adiada? [Completude, Spec §Interface Coverage; Interface §Interface Coverage] {auto}
- [x] CHK002 - Cada tela ou popup novo/modificado possui identificador estável, tipo de mudança e ponto de entrada? [Rastreabilidade, Interface §Interaction Inventory] {auto}
- [x] CHK003 - O estado atual da interface afetada identifica rota, componente e comportamento que serão substituídos? [Completude, Interface §Current-State Evidence] {auto}
- [x] CHK004 - A separação entre entrada e criação de conta define navegação, retorno e não transporte da senha entre jornadas? [Clareza, Interface §INT-WEB-VIS-001; Interface §INT-WEB-VIS-002] {auto}

## Comportamento e Estados

- [x] CHK005 - Cada interação define propósito, atores, permissões, conteúdo, ações, validação e feedback sem alterar regras de autenticação existentes? [Completude, Interface §INT-WEB-VIS-001–005; Plan §Summary] {auto}
- [x] CHK006 - Todos os estados canônicos estão definidos ou possuem justificativa explícita de não aplicabilidade em cada interação? [Cobertura, Interface §INT-WEB-VIS-001–005 States] {auto}
- [x] CHK007 - Os requisitos para preservar dados durante troca de apresentação distinguem dados seguros de senha, código, token e estado de sessão? [Segurança e clareza, Spec §Edge Cases; Interface §INT-WEB-VIS-001, §INT-WEB-VIS-003 e §INT-WEB-VIS-005] {auto}
- [x] CHK008 - O popup unificado descreve integralmente os quatro grupos, as opções permitidas, a persistência e o fechamento sem ambiguidade? [Completude, Spec §FR-003 e FR-006; Interface §INT-WEB-VIS-005] {auto}

## Responsividade, Acessibilidade e Localização

- [x] CHK009 - Os form factors web possuem limites mensuráveis, regras de reflow e tratamento de teclado virtual, safe area e escalas ampliadas? [Clareza, Plan §Movimento e responsividade; Interface §INT-WEB-VIS-001–005] {auto}
- [x] CHK010 - Foco, teclado, leitor de tela, contraste, área de toque, zoom e redução de movimento são definidos para as interações críticas? [Cobertura, Spec §FR-015; Interface §Shared Accessibility and Input] {auto}
- [x] CHK011 - Os quatro idiomas, fallback, expansão de texto e a restrição explícita de que RTL não integra esta fase estão documentados? [Cobertura, Spec §FR-004–005; Plan §Internacionalização] {auto}
- [x] CHK012 - Bandeiras não são o único identificador de idioma e controles de ícone possuem nomes acessíveis? [Acessibilidade, Interface §INT-WEB-VIS-005] {auto}

## Contratos, Componentes e Wireframes

- [x] CHK013 - As interações de acesso referenciam o contrato já existente sem copiar payloads ou propor mudança de API? [Consistência, Plan §Summary e §Convenções de Borda; Interface §Integration and Contracts] {auto}
- [x] CHK014 - Preferências locais possuem fonte de verdade, validação e fronteira claras, sem persistência de dados de acesso? [Rastreabilidade, Plan §Preferências e inicialização; data-model.md §Preferência visual local] {auto}
- [x] CHK015 - Cada interação estrutural possui wireframe e a especificação textual permanece a fonte de verdade? [Completude, Interface §Wireframes; arquivos `wireframes/int-web-vis-001.md`–`004.md`] {auto}
- [x] CHK016 - Os componentes previstos são reutilizáveis e suas responsabilidades não são vinculadas a uma única tela? [Consistência, Plan §Componentes compartilhados; Interface §Components and Design System] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Não há itens `{humano}` nem gaps de requisito abertos neste domínio.
