# Checklist de Segurança: Fundação de Autorização

**Propósito**: validar se os requisitos de autorização descrevem controles verificáveis, isolamento de esferas e proteção da trilha de segurança antes do backlog.  
**Criada em**: 2026-09-26  
**Feature**: [spec.md](../spec.md)

## Decisão e Isolamento

- [x] CHK001 - O modelo de autorização e suas esferas-base estão definidos sem sobrepor autorização por recurso? [Completude, Spec §Direção de Produto; Spec §FR-AF-002] {auto}
- [x] CHK002 - O default deny está expresso como comportamento observável para autenticação, membership e identificadores conhecidos? [Segurança, Spec §FR-AF-003; Spec §Casos de Borda] {auto}
- [x] CHK003 - Os requisitos distinguem membership de tenant de concessão de permission? [Consistência, Spec §FR-AF-012; Plan §Desenho da Arquitetura] {auto}
- [x] CHK004 - As decisões TENANT exigem tenant explícito, ativo e membership ativa antes da avaliação de grants? [Segurança, Spec §FR-AF-006; Contract §check] {auto}
- [x] CHK005 - As decisões PLATFORM e PERSONAL proíbem fallback ou inferência de tenant? [Segurança, Spec §FR-AF-004 a FR-AF-005; Contract §check] {auto}
- [x] CHK006 - O requisito de isolamento impede que uma concessão no Tenant A produza efeito no Tenant B? [Cobertura, Spec §User Story 1; SC-AF-002] {auto}
- [x] CHK007 - O escopo exclui explicitamente relações por recurso e regras contextuais, impedindo que sejam implementadas implicitamente na fundação? [Limite de Escopo, Spec §FR-AF-017; Data Model §Decisão e integridade] {auto}

## Privilégios e Revogação

- [x] CHK008 - Roles, permissions e grupos possuem regras explícitas de compatibilidade de esfera e de inativação? [Completude, Spec §FR-AF-007 a FR-AF-011; Data Model §auth_permission a §Relações] {auto}
- [x] CHK009 - A role administrativa protegida possui regra verificável para impedir a remoção do último administrador elegível? [Segurança, Spec §FR-AF-013; Plan §Desenho da Arquitetura] {auto}
- [x] CHK010 - A role administrativa é descrita como conjunto de permissions, sem bypass oculto de autorização? [Consistência, Spec §Casos de Borda; Plan §Desenho da Arquitetura] {auto}
- [x] CHK011 - Revogação de role, grupo ou membership tem efeito exigido na próxima operação, sem depender de logout? [Segurança, Spec §FR-AF-014; Spec §User Story 3] {auto}
- [x] CHK012 - O requisito de hierarquia de grupos cobre ciclo direto e indireto e determina que a rejeição seja atômica? [Cobertura, Spec §User Story 2; Spec §Casos de Borda; Research §Decisão 4] {auto}
- [x] CHK013 - A política que determina quais permissions TENANT novas passam automaticamente a compor `tenant.administrator` está definida sem exceções? [Resolvido, Spec §FR-AF-020; Plan §Desenho da Arquitetura] {humano}

## Auditoria e Exposição

- [x] CHK014 - As alterações administrativas relevantes exigem evento de auditoria com ator, instante, contexto e alvo, sem segredos? [Segurança, Spec §FR-AF-015; Data Model §auth_audit_event] {auto}
- [x] CHK015 - A imutabilidade da auditoria está definida como ausência de atualização e remoção pelo aplicativo? [Clareza, Research §Decisão 5; Data Model §auth_audit_event] {auto}
- [x] CHK016 - Capabilities de workspace estão declaradas como projeção de UX e o backend repete a decisão? [Segurança, Spec §FR-AF-016; Contract §Capabilities de workspace] {auto}
- [x] CHK017 - O período de retenção e o descarte de eventos de auditoria estão definidos com prazo padrão, configuração por ambiente e rotina diária? [Resolvido, Spec §FR-AF-019; Research §Decisão 5] {humano}
- [x] CHK018 - O processo para bootstrap do primeiro administrador PLATFORM é definido sem conceder privilégios automaticamente a novos usuários? [Resolvido, Spec §FR-AF-021; Research §Decisão 7] {humano}

## Notas

- Itens `{auto}` foram resolvidos contra a spec, plano e contrato citados.
- CHK013, CHK017 e CHK018 foram resolvidos pela sessão de clarificação de 2026-09-26.
