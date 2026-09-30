# Checklist de Interface: Compatibilidade de Schemas e Guarda Operacional

**Finalidade**: validar a cobertura de superfícies, estados, acessibilidade, responsividade e contratos da experiência de indisponibilidade.
**Criado em**: 2026-09-30
**Feature**: [interface-spec.md](../interface-spec.md)

## Cobertura e estados

- [x] CHK001 - Cada superfície humana FULL ou PARTIAL possui uma interação inventariada e detalhada? [Cobertura, Interface §§Interface Coverage e Interaction Inventory] {auto}
- [x] CHK002 - A indisponibilidade global substitui a experiência funcional sem preservar menu, conteúdo autenticado ou contexto operacional reutilizável? [Clareza de estado, Interface §INT-WEB-SCHEMA-001; Spec §FR-SCG-002] {auto}
- [x] CHK003 - A indisponibilidade de organização preserva explicitamente espaço pessoal e outras organizações compatíveis? [Isolamento, Interface §INT-WEB-SCHEMA-002; Spec §FR-SCG-006] {auto}
- [x] CHK004 - Os estados canônicos estão definidos ou justificados como N/A em cada interação inventariada? [Completude, Interface §§INT-WEB-SCHEMA-001, 002, PEOPLE-001 e DRIVE-001] {auto}
- [x] CHK005 - Pessoas e Drive Work tratam o bloqueio comum sem criar mensagens, recuperação ou regra de disponibilidade divergentes? [Consistência, Interface §§INT-WEB-PEOPLE-001, INT-WEB-DRIVE-001 e Regras entre superfícies] {auto}

## Acessibilidade, responsividade e conteúdo

- [x] CHK006 - A tela global possui hierarquia semântica, foco inicial, botão nomeado e feedback que não depende somente de cor ou movimento? [Acessibilidade, Interface §INT-WEB-SCHEMA-001] {auto}
- [x] CHK007 - O seletor e o aviso contextual definem foco, retorno seguro, teclado, toque e diálogo móvel? [Acessibilidade, Interface §INT-WEB-SCHEMA-002] {auto}
- [x] CHK008 - Desktop, tablet e telefone possuem regras de reflow, área segura e teclado virtual para as duas interações estruturais? [Responsividade, Interface §INT-WEB-SCHEMA-001 e 002] {auto}
- [x] CHK009 - A terminologia evita os termos técnicos proibidos e possui localização nos quatro idiomas suportados? [Conteúdo e localização, Interface §Regras entre superfícies; Contract] {auto}

## Contratos e rastreabilidade

- [x] CHK010 - Cada interação consome uma identificação estável de indisponibilidade e não interpreta texto como condição de controle? [Contrato, Interface §§INT-WEB-SCHEMA-001 e 002; Contract] {auto}
- [x] CHK011 - A rastreabilidade mapeia interações a histórias, requisitos, critérios de sucesso e contrato? [Rastreabilidade, Interface §Traceability] {auto}
- [x] CHK012 - Os wireframes obrigatórios existem e são explicitamente subordinados ao texto da interface? [Completude, Interface §Wireframes; wireframes/int-web-schema-001.md e 002.md] {auto}

## Notas

O quality gate não testa código, páginas ou dispositivos. A validação executável de responsividade e dos contratos está planejada em [quickstart.md](../quickstart.md).
