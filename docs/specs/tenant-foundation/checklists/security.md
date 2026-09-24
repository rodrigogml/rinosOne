# Security Checklist: Fundação de Tenants

**Purpose**: validar requisitos de autorização, isolamento, proteção de infraestrutura e auditoria do contexto de tenant.
**Created**: 2026-09-24
**Feature**: [spec.md](../spec.md)

## Autenticação e Autorização

- [x] CHK001 - A criação e o uso contextual exigem usuário autenticado e validado? [Cobertura, Spec §FR-TEN-001 e FR-TEN-009] {auto}
- [x] CHK002 - A ausência, ambiguidade ou manipulação de identificador de tenant resulta em negação segura? [Deny-by-default, Spec §FR-TEN-009, FR-TEN-010 e FR-TEN-014] {auto}
- [x] CHK003 - O modelo mínimo de associação e o papel OWNER estão definidos sem antecipar RBAC ou gestão de membros? [Escopo, Data Model §tenantMembership; Research §Decision 5] {auto}
- [x] CHK004 - Uma sessão autenticada compartilhada não compartilha implicitamente o tenant entre abas? [Isolamento, Spec §FR-TEN-011; Research §Decision 2] {auto}

## Dados, Infraestrutura e Entrada

- [x] CHK005 - A entrada da pessoa nunca forma o nome do schema físico e o identificador técnico é derivado de ULID validado? [Injection, Research §Decision 1; Data Model §tenant] {auto}
- [x] CHK006 - As credenciais de provisionamento e runtime são separadas e valores reais permanecem fora do versionamento? [Least privilege, Plan §Migrations e conexões; Constituição IV] {auto}
- [x] CHK007 - Falha de provisionamento impede contexto operacional e não expõe SQL, host, segredo ou detalhes internos? [Proteção de dados, Spec §FR-TEN-004 e FR-TEN-005; Data Model §tenantProvisioning] {auto}
- [x] CHK008 - A validação de nome, chave de intenção e parâmetro de tenant é prevista na fronteira de request? [Input validation, Plan §Convenções de Borda; Contracts §Criar tenant] {auto}

## Auditoria e Ameaças

- [x] CHK009 - Criação, disponibilidade, seleção, troca, encerramento e tentativas negadas possuem requisito de auditoria minimizada? [Auditoria, Spec §FR-TEN-018] {auto}
- [x] CHK010 - Os riscos de mistura de dados entre abas, fallback de contexto, enumeração de tenant e duplicação de criação estão cobertos por requisitos ou cenários? [Threat modeling, Spec §Casos de Borda; Research §Decisions 1 a 3] {auto}
- [x] CHK011 - A limitação configurável de solicitações de criação está definida para reduzir abuso de criação de schemas? [Deferred por decisão do produto, Requirements CHK010; Plan §Contexto Técnico] {humano}
- [x] CHK012 - Entregáveis formais de compliance e privacidade permanecem fora de escopo, sem remover os controles técnicos aprovados? [Escopo, Briefing inicial §8; Spec §FR-TEN-019] {auto}

## Notes

- CHK011 foi adiado explicitamente para a futura feature de limites e contratação.
