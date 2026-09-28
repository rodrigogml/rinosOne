# Interface Checklist: Rinos Drive

**Propósito**: validar a qualidade do contrato de interação web responsiva do Rinos Drive.  
**Criado em**: 2026-09-27  
**Feature**: [interface-spec.md](../interface-spec.md)

## Cobertura e Inventário

- [x] CHK001 - A superfície web declarada como FULL possui interações para navegador, organização, upload, exportação e detalhes? [Completude, Interface §Interface Coverage; §Interaction Inventory] {auto}
- [x] CHK002 - A tela modificada identifica componente e comportamento atual antes de definir a evolução estrutural? [Completude, Interface §Current-State Evidence] {auto}
- [x] CHK003 - Cada `INT-*` possui propósito, atores, permissões, navegação, conteúdo, ações, validação, responsividade, acessibilidade, localização, componentes, contrato, telemetria e wireframe? [Completude, Interface §Interaction Details] {auto}
- [x] CHK004 - Todos os estados canônicos estão definidos ou justificados para cada interação? [Cobertura, Interface INT-WEB-DRIVE-001 a 005] {auto}

## Responsividade, Acessibilidade e Sistema Visual

- [x] CHK005 - Desktop, tablet e telefone distinguem árvore/painel lateral de drawers modais sem criar rolagem no canvas ou taskbar paralela? [Responsividade, Interface INT-WEB-DRIVE-001 e 005; Wireframes] {auto}
- [x] CHK006 - Ações críticas continuam disponíveis por teclado, ponteiro, toque e leitor de tela, com foco e anúncios dinâmicos definidos? [Acessibilidade, Interface §§INT-WEB-DRIVE-001 a 004; §Regras Transversais] {auto}
- [x] CHK007 - Diálogos de organização e exportação pertencem à instância da janela, preservam pilha e não bloqueiam outras superfícies? [Consistência, Interface INT-WEB-DRIVE-002 e 004; workspace-shell contract] {auto}
- [x] CHK008 - Componentes novos foram definidos como reutilizáveis e vinculados aos tokens e componentes centrais existentes? [Rastreabilidade, Interface §§Components and Design System] {auto}
- [x] CHK009 - Os wireframes obrigatórios existem e correspondem às regras textuais de desktop e telefone? [Completude, Interface §Wireframes; wireframes/drive-desktop.md; wireframes/drive-mobile.md] {auto}

## Dados, Estados e Localização

- [x] CHK010 - Estados remoto, offline, acesso revogado e dados desatualizados preservam somente dados seguros e definem saída recuperável? [Cobertura, Interface INT-WEB-DRIVE-001 a 005] {auto}
- [x] CHK011 - A terminologia canônica diferencia Drive Pessoal, Drive Work, arquivo e exportação sem ambiguidade? [Clareza, Interface §Regras Transversais] {auto}
- [x] CHK012 - Textos, tamanhos, datas, pluralização e expansão nos quatro idiomas estão explicitamente previstos? [Localização, Interface §§INT-WEB-DRIVE-001 a 005] {auto}
- [x] CHK013 - Cada interação mapeia para jornadas, requisitos, critérios de sucesso e contrato aplicável? [Rastreabilidade, Interface §Traceability] {auto}

## Notas

- Itens `{auto}` foram resolvidos com as referências indicadas.
- Não há gap de tela, comando, estado ou rastreabilidade aberto.
