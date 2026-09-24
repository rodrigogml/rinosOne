# Interface Checklist: Fundação de Tenants

**Purpose**: validar cobertura, estados, responsividade, acessibilidade e rastreabilidade da interface de tenants.
**Created**: 2026-09-24
**Feature**: [interface-spec.md](../interface-spec.md)

## Cobertura e Inventário

- [x] CHK001 - A superfície web possui cobertura FULL e a superfície futura possui adiamento explícito? [Completude, Interface §Interface Coverage] {auto}
- [x] CHK002 - Barra contextual, seletor e criação/gestão possuem identificadores estáveis e detalhes individuais? [Rastreabilidade, Interface §Interaction Inventory; §Interaction Details] {auto}
- [x] CHK003 - O estado atual da barra, avatar e painel móvel está documentado antes das mudanças? [Completude, Interface §Current-State Evidence] {auto}

## Estados e Navegação

- [x] CHK004 - Cada interação define os estados inicial, carregamento, vazio, pronto, processamento, sucesso, erro, offline, acesso negado e estado desatualizado, ou justifica N/A? [Cobertura, Interface §INT-WEB-001 a §INT-WEB-003] {auto}
- [x] CHK005 - Troca e encerramento preservam recursos pessoais e não alteram outra aba? [Consistência, Spec §FR-TEN-011 e FR-TEN-012; Interface §Cross-Surface Rules] {auto}
- [x] CHK006 - A interface evita seleção automática e exige validação remota antes de exibir um novo contexto? [Clareza, Spec §FR-TEN-008; Interface §INT-WEB-002] {auto}

## Responsividade, Acessibilidade e Conteúdo

- [x] CHK007 - O comportamento para desktop e telefone define popover/folha, rolagem, área segura, teclado virtual e prioridade da marca e avatares? [Cobertura, Interface §INT-WEB-001 a §INT-WEB-003] {auto}
- [x] CHK008 - Foco, teclado, leitor de tela, anúncio de mudança, contraste, toque e preferência de movimento estão definidos? [Acessibilidade, Interface §Cross-Surface Rules] {auto}
- [x] CHK009 - Os quatro idiomas existentes abrangem rótulos, estados, erros e confirmações, preservando nomes de organização? [Localização, Interface §INT-WEB-001 a §INT-WEB-003] {auto}

## Design System e Wireframes

- [x] CHK010 - Componentes novos são reutilizáveis e os componentes/tokens existentes são identificados sem criar uma segunda navegação? [Consistência, Interface §INT-WEB-001 a §INT-WEB-003] {auto}
- [x] CHK011 - Os wireframes obrigatórios existem e são coerentes com barra, popover/folha e diálogo descritos? [Completude, Interface §Wireframes; wireframes/tenant-workspace.md] {auto}

## Notes

- Todos os itens deste domínio foram resolvidos por evidência no contrato de interface.
