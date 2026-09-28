# Security Checklist: Cadastro de Pessoas por Organização

**Purpose**: verificar requisitos de isolamento, autorização, proteção de dados pessoais, auditoria e exclusão segura.  
**Created**: 2026-09-28  
**Feature**: [spec.md](../spec.md)

## Isolamento e autorização

- [x] CHK-SEC-001 - O requisito de isolamento impede busca, leitura e alteração de Pessoa fora da organização em contexto? [Isolamento, Spec FR-001; Spec CS-003; Plan §Fluxos técnicos principais] {auto}
- [x] CHK-SEC-002 - As operações têm permissões organizacionais distintas e a decisão final é do backend? [Autorização, Plan §Autorização e auditoria; Contract §Operações] {auto}
- [x] CHK-SEC-003 - A troca de organização descarta dados e superfície carregados, impedindo vazamento visual entre contextos? [Interface segura, Interface §Cross-Surface Rules] {auto}
- [x] CHK-SEC-004 - Referências de catálogo seguem somente da organização para o catálogo corporativo e não usam ações restritivas de FK? [Integridade, Data model §Integridade referencial; Plan §Rechecagem da Constituição] {auto}

## Dados pessoais e validação

- [x] CHK-SEC-005 - Documento, contato e Pix têm normalização/validação definida, com apresentação sem máscara conforme decisão expressa e exclusão desses dados da telemetria? [Proteção de dados, Spec §Clarificações; Contract introdução; Interface §Telemetry] {auto}
- [x] CHK-SEC-006 - Telemetria e mensagens de interface excluem nome, documento, contato, endereço, conta, Pix e dados de relacionamento? [Minimização, Interface INT-WEB-PEOPLE-001 a INT-WEB-PEOPLE-006] {auto}
- [x] CHK-SEC-007 - Erros de exclusão não expõem SQL, identificadores técnicos ou dados internos de módulos? [Exposição segura, Spec FR-022; Contract §Exclusão física; Interface INT-WEB-PEOPLE-005] {auto}
- [x] CHK-SEC-008 - A proteção em repouso de dados financeiros é atribuída à infraestrutura, sem criptografia por campo ou mascaramento na aplicação? [Proteção de dados, Spec §Clarificações; Research Decisão 7; Plan §Autorização e auditoria] {auto}
- [x] CHK-SEC-009 - A apresentação de CPF/CNPJ, conta e Pix sem máscara, sem permissão específica de desmascaramento, está expressamente decidida? [Privilégio mínimo, Spec §Clarificações; Interface INT-WEB-PEOPLE-002 e INT-WEB-PEOPLE-003] {auto}

## Auditoria, retenção e ameaças

- [x] CHK-SEC-010 - Eventos de auditoria evitam snapshots integrais de dados pessoais e não bloqueiam exclusão física? [Minimização, Research Decisão 6; Data model §personAuditEvent] {auto}
- [x] CHK-SEC-011 - Retenção de auditoria define padrão de 90 dias configurável, limpeza diária pelo Hub e ausência de consulta visual nesta fase? [Retenção, Spec §Clarificações; Plan §Autorização e auditoria] {auto}
- [x] CHK-SEC-012 - O conjunto de requisitos cobre enumeração por documento/contato, acesso cruzado por rota, repetição de exclusão e abuso de busca? [Threat modeling, Spec FR-001 a FR-003 e FR-022; Contract §Políticas gerais de operação; Plan §Políticas gerais de API e concorrência] {auto}

## Notes

- Não há lacunas abertas neste domínio.
