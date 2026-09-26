# Requirements Checklist: Central de Manutenções

**Purpose**: validar completude, clareza, consistência e rastreabilidade dos requisitos antes da decomposição em tarefas.  
**Created**: 2026-09-26  
**Feature**: [spec.md](../spec.md)

## Completude e Limites

- [x] CHK001 - A centralização de visualização e ações administrativas está definida sem transformar as rotinas em um modelo genérico? [Completude, Spec §FR-MH-001 a 003; Plan §Arquitetura de Integração] {auto}
- [x] CHK002 - As capacidades que variam por rotina — agenda, eventos, disparo manual, concorrência, instâncias e retentativas — possuem limite explícito? [Completude, Spec §FR-MH-003, 005, 006 e INFRA-SCHED] {auto}
- [x] CHK003 - A primeira integração e a consolidação gradual das rotinas existentes estão separadas de modo que não ampliem o MVP silenciosamente? [Escopo, Spec História 4; Briefing §4] {auto}
- [x] CHK004 - A dependência unidirecional “hub conhece rotina; rotina não conhece hub” está consistente entre briefing, spec e plano? [Consistência, Briefing §5; Spec §FR-MH-002 e 003; Plan §Arquitetura de Integração] {auto}

## Auditoria, Histórico e Retenção

- [x] CHK005 - Histórico técnico e auditoria administrativa são definidos como dados distintos, com conteúdo e propósito verificáveis? [Completude, Spec §FR-MH-007 a 010; Data Model] {auto}
- [x] CHK006 - As retenções de histórico técnico e auditoria são independentes, configuráveis e possuem padrão de 90 dias? [Clareza, Spec §FR-MH-008 a 010; Plan §Contexto Técnico] {auto}
- [x] CHK007 - A imutabilidade da auditoria administrativa até a expiração está declarada sem conflitar com sua retenção configurável? [Consistência, Spec §FR-MH-009; Data Model §Relações e retenção] {auto}
- [x] CHK008 - A especificação limita parâmetros, logs e detalhes a dados seguros, evitando exposição de segredos na interface, auditoria e telemetria? [Segurança, Spec §FR-MH-013; Interface §INT-WEB-MAINTENANCE-001 e 002] {auto}

## Interface e Acesso

- [x] CHK009 - A superfície administrativa possui cobertura FULL, inventário de interações, estados, responsividade, acessibilidade e rastreabilidade completos? [Completude, Interface §Interface Coverage, §Interaction Inventory, §Interaction Details e §Traceability] {auto}
- [x] CHK010 - A interface deixa de oferecer ações indisponíveis e não revela rotinas sem autorização de visualização? [Segurança, Spec §FR-MH-004, 005 e 011; Interface §INT-WEB-MAINTENANCE-001] {auto}
- [x] CHK011 - A reavaliação de autorização no momento de uma ação e a dependência da fundação definitiva de permissões estão documentadas sem inventar chaves ou papéis? [Dependência, Spec §FR-MH-011; Research Decisão 4; Interface §INT-WEB-MAINTENANCE-002] {auto}

## Contratos e Verificação

- [x] CHK012 - O contrato administrativo expõe somente operações permitidas e deixa a agenda como ação específica, não como endpoint universal? [Consistência, Contract §Operações previstas e §Regras de resposta] {auto}
- [x] CHK013 - Cada cenário crítico define resultado observável para consulta, disparo, recusa, agenda diária e retenção? [Cobertura, Quickstart §Cenários 1 a 5] {auto}
- [x] CHK014 - Os critérios de sucesso são mensuráveis e cobrem visualização, auditoria, ações não permitidas, retenção e instituições financeiras? [Mensurabilidade, Spec §Critérios de Sucesso] {auto}

## Notas

- Todos os itens `{auto}` foram resolvidos com evidência citada.
- Não há `[Gap]`, `[Ambiguity]` ou `[Conflict]` remanescente nesta rodada.
- A integração de permissões é uma dependência planejada, não uma ambiguidade: sua implementação deve ser coordenada com a fundação de autorização antes de ativar ações administrativas.
