# Checklist de Requisitos: Fundação de Autorização

**Propósito**: validar completude, clareza, mensurabilidade e rastreabilidade da especificação funcional e do plano técnico.  
**Criada em**: 2026-09-26  
**Feature**: [spec.md](../spec.md)

## Completude e Limites

- [x] CHK001 - As três esferas-base são definidas com exemplos e fronteiras funcionais distintas? [Completude, Spec §Direção de Produto] {auto}
- [x] CHK002 - As histórias independentes cobrem decisão por esfera, concessão por roles e grupos, revogação e último administrador? [Cobertura, Spec §Cenários de Usuário e Testes] {auto}
- [x] CHK003 - Cada requisito funcional possui verbo testável e identifica a regra observável esperada? [Clareza, Spec §FR-AF-001 a FR-AF-018] {auto}
- [x] CHK004 - As exclusões de resource authorization, restrictions, delegação, cache e administração visual estão declaradas sem ambiguidade? [Escopo, Spec §FR-AF-017; Plan §Constitution Check] {auto}
- [x] CHK005 - A dependência de `tenant-foundation` e a separação de responsabilidade de membership estão documentadas? [Dependência, Spec §FR-AF-012; Plan §Resumo] {auto}

## Consistência e Rastreabilidade

- [x] CHK006 - A regra do último administrador é consistente entre histórias, requisito funcional, modelo de dados e plano? [Consistência, Spec §User Story 4; Spec §FR-AF-013; Data Model §auth_role; Plan §Desenho da Arquitetura] {auto}
- [x] CHK007 - O contrato de `check` é compatível com as regras de escopo e default deny da especificação? [Consistência, Contract §check; Spec §FR-AF-003 a FR-AF-006] {auto}
- [x] CHK008 - A projeção de capability do backend para a web não contradiz a autoridade final do backend? [Consistência, Spec §FR-AF-016; Contract §Capabilities de workspace] {auto}
- [x] CHK009 - O modelo de dados mantém o core livre de FK para recursos de tenant e usa identidades numéricas? [Constituição, Data Model §Introdução; Plan §Constitution Check] {auto}
- [x] CHK010 - Não há marcadores `[NEEDS CLARIFICATION]` pendentes nos artefatos da feature? [Clareza, Spec §Clarificações; Plan §Contexto Técnico] {auto}

## Cenários e Qualidade Não Funcional

- [x] CHK011 - Casos de borda cobrem ausência de grant, inatividade, ciclo de grupo, mudança de aba e remoção atômica? [Cobertura, Spec §Casos de Borda] {auto}
- [x] CHK012 - Critérios de sucesso são mensuráveis e mapeiam default deny, isolamento, revogação, último administrador e auditoria? [Mensurabilidade, Spec §Critérios de Sucesso] {auto}
- [x] CHK013 - O plano declara validação para decisão, persistência, contratos, interface e cenário E2E? [Verificabilidade, Plan §Validação Planejada; Quickstart §Cenário 5] {auto}
- [x] CHK014 - A ausência de cache e de agendamento no caminho de decisão é explícita, e a retenção diária de auditoria não cria dependência de infraestrutura implícita para autorizar? [Completude, Spec §FR-AF-018 e FR-AF-019; Plan §Contexto Técnico] {auto}
- [x] CHK015 - A prioridade de entrega entre grants diretos, grupos e grupos aninhados está definida de modo que o primeiro incremento continue utilizável sem antecipar administração visual? [Resolvido, Plan §Sequência de entrega] {humano}

## Notas

- Itens `{auto}` foram resolvidos com evidência rastreável.
- CHK015 foi resolvido pela sequência incremental de entrega registrada no plano.
