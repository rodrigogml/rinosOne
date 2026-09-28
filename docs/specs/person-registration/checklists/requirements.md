# Requirements Checklist: Cadastro de Pessoas por Organização

**Purpose**: verificar completude, clareza, consistência e mensurabilidade da especificação funcional antes do backlog.  
**Created**: 2026-09-28  
**Feature**: [spec.md](../spec.md)

## Completude e escopo

- [x] CHK-REQ-001 - A especificação delimita a Pessoa por organização e proíbe compartilhamento entre organizações? [Completude, Spec §Contexto e limite de escopo; Spec FR-001] {auto}
- [x] CHK-REQ-002 - As coleções aprovadas — endereços, contatos, contas, Pix e relacionamentos — estão enumeradas sem introduzir dados fiscais, profissionais ou dependentes? [Completude, Spec §Contexto e limite de escopo; Spec §Entidades principais] {auto}
- [x] CHK-REQ-003 - A ausência de CPF/CNPJ está definida como caso válido sem eliminar a unicidade quando o documento existir? [Clareza, Spec FR-005 a FR-007] {auto}
- [x] CHK-REQ-004 - A regra de endereço separa país obrigatório, territorialidade brasileira obrigatória e rua textual não dependente de catálogo? [Completude, Spec FR-009 a FR-011] {auto}
- [x] CHK-REQ-005 - As exclusões explícitas de contato principal, Pix principal/finalidade e dependentes estão documentadas como fora de escopo? [Consistência, Spec §Contexto e limite de escopo; Spec FR-013 e FR-016] {auto}

## Clareza e consistência

- [x] CHK-REQ-006 - Os estados de Pessoa são limitados a ativo e inativo, com reativação e exclusão física tratadas separadamente? [Clareza, Spec FR-020 a FR-022; Spec História 5] {auto}
- [x] CHK-REQ-007 - A relação entre Pessoas tem direção única, tipo oposto para apresentação e `OUTROS` como próprio oposto, sem vínculo recíproco persistido? [Consistência, Spec §Clarificações; Spec FR-018 a FR-019A] {auto}
- [x] CHK-REQ-008 - A exclusão física de Pessoa remove seus relacionamentos e preserva a contraparte, sem contradizer o fluxo de exclusão? [Consistência, Spec FR-019A; Spec História 4 e 5] {auto}
- [x] CHK-REQ-009 - Nome de exibição é definido como derivado e não como identidade única, permitindo homônimos? [Clareza, Spec FR-004; Spec §Casos de borda] {auto}
- [x] CHK-REQ-010 - Não há marcadores de esclarecimento, sistemas anteriores ou requisitos de importação fora do escopo aprovado? [Completude, Spec §Contexto e limite de escopo; inspeção de `spec.md`] {auto}

## Critérios de aceite e cenários

- [x] CHK-REQ-011 - As jornadas P1 podem ser testadas independentemente e possuem cenários de aceite para cadastro, endereço/contato e ciclo de vida? [Cobertura, Spec Histórias 1, 2 e 5] {auto}
- [x] CHK-REQ-012 - Os critérios de sucesso têm limiar verificável para aceite, tempo de cadastro, isolamento, diagnóstico de exclusão e busca? [Mensurabilidade, Spec CS-001 a CS-005] {auto}
- [x] CHK-REQ-013 - Os casos de borda cobrem documento ausente, documento formatado, endereço brasileiro incompleto, relação entre organizações e referência corporativa indisponível? [Cobertura, Spec §Casos de borda] {auto}
- [x] CHK-REQ-014 - Duplicação separa explicitamente identidade exclusiva de coleções copiáveis e exige confirmação para dados sensíveis? [Clareza, Spec História 6; Spec FR-025] {auto}

## Dependências e pendências de decisão

- [x] CHK-REQ-015 - As dependências de catálogos corporativos e do modelo de autorização estão identificadas sem inverter o ownership de dados? [Dependências, Plan §Contexto técnico; Plan §Autorização e auditoria] {auto}
- [x] CHK-REQ-016 - A política de retenção, consulta e descarte dos eventos de auditoria de Pessoas define padrão de 90 dias configurável, limpeza diária pelo Hub e ausência de tela nesta fase? [Compliance, Spec §Clarificações; Plan §Autorização e auditoria] {auto}
- [x] CHK-REQ-017 - A regra de concorrência recusa gravação desatualizada, exige recarga e não prevê mesclagem ou última gravação prevalecente? [Concorrência, Spec §Clarificações; Plan §Políticas gerais de API e concorrência; Contract §Políticas gerais de operação] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos SDD citados.
- Não há lacunas abertas neste domínio.
