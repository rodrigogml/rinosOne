# Requirements Checklist: Fundação de Tenants

**Purpose**: validar clareza, completude, consistência e rastreabilidade da fundação de tenants antes do backlog.
**Created**: 2026-09-24
**Feature**: [spec.md](../spec.md)

## Completude e Escopo

- [x] CHK001 - Estão definidos criação, proprietário inicial, preparação, seleção, troca, encerramento e desabilitação? [Completude, Spec §Cenários de Usuário e Testes] {auto}
- [x] CHK002 - Estão explícitos os limites de escopo para convites, membros, papéis detalhados, módulos e exclusão? [Completude, Spec §FR-TEN-019] {auto}
- [x] CHK003 - A separação entre funcionalidades pessoais sempre acessíveis e recursos contextuais condicionais está definida? [Clareza, Spec §Direção de Produto; FR-TEN-006 a FR-TEN-008] {auto}
- [x] CHK004 - O ciclo de vida do tenant e as condições de disponibilidade possuem estados e transições definidos? [Completude, Data Model §tenant; §tenantProvisioning] {auto}

## Clareza, Consistência e Cenários

- [x] CHK005 - A seleção explícita, inclusive para uma única organização, é consistente entre spec, plano e interface? [Consistência, Spec §FR-TEN-008; Plan §Resolução de contexto; Interface §INT-WEB-002] {auto}
- [x] CHK006 - O requisito de contexto independente por aba é compatível com a decisão de não persistir tenant em sessão, cookie ou armazenamento do navegador? [Consistência, Spec §FR-TEN-011; Research §Decision 2] {auto}
- [x] CHK007 - Criação repetida, preparação com falha, tenant indisponível, revogação e troca simultânea possuem comportamento esperado? [Cobertura, Spec §Casos de Borda; Quickstart §Cenários 2, 3 e 5] {auto}
- [x] CHK008 - Os critérios de sucesso são verificáveis por cenários de criação, contexto vazio, abas isoladas, invalidação e idempotência? [Mensurabilidade, Spec §Critérios de Sucesso] {auto}

## Dependências e Decisões Pendentes

- [x] CHK009 - As dependências de MySQL, fila persistida, migrations independentes e credenciais separadas estão documentadas? [Completude, Plan §Migrations e conexões] {auto}
- [x] CHK010 - A política configurável de limite de criação por usuário e por intervalo está definida para evitar uso abusivo de provisionamento? [Deferred por decisão do produto, Plan §Contexto Técnico] {humano}
- [x] CHK011 - A política configurável de máximo de tentativas, intervalo e condição de retentativa do provisionamento está definida? [Resiliência, Plan §Contexto Técnico; Data Model §tenantProvisioning] {humano}

## Rastreabilidade

- [x] CHK012 - Cada interação humana possui vínculo com user stories, requisitos, critérios de sucesso e contratos aplicáveis? [Rastreabilidade, Interface §Traceability] {auto}

## Notes

- Itens `{auto}` foram resolvidos por evidência documental.
- CHK010 foi adiado explicitamente para a futura feature de limites e contratação; não faz parte do backlog desta fundação.
