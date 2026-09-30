# Checklist de Requisitos: Compatibilidade de Schemas e Guarda Operacional

**Finalidade**: validar completude, clareza, consistência e rastreabilidade da especificação antes da criação do backlog.
**Criado em**: 2026-09-30
**Feature**: [spec.md](../spec.md)

## Completude e consistência

- [x] CHK001 - Os requisitos distinguem explicitamente incompatibilidade global de incompatibilidade de uma única organização, incluindo o efeito de cada uma? [Completude, Spec §Contexto e FR-SCG-001 a 007] {auto}
- [x] CHK002 - A atualização global está definida como etapa explícita de implantação, sem conflito com a exigência de bloqueio no runtime? [Consistência, Spec §Contexto e FR-SCG-014; Plan §Guarda global] {auto}
- [x] CHK003 - Os requisitos definem a condição segura para histórico ausente, ilegível ou incompleto, sem assumir compatibilidade na falha? [Cobertura de borda, Spec §Casos de borda e FR-SCG-001 a 002] {auto}
- [x] CHK004 - O ciclo de atualização de organizações existentes está separado do provisionamento inicial e possui transições de sucesso, falha transitória e falha terminal? [Completude, Spec §Histórias 3 e 4; Data model §Transições de estado] {auto}
- [x] CHK005 - A regra de não executar migrations durante o boot da web está clara e preserva um caminho operacional para recuperação? [Clareza, Spec §FR-SCG-014; Research §Decisão 1; Plan §Operação e deploy] {auto}
- [x] CHK006 - A indisponibilidade administrativa e a indisponibilidade por atualização possuem semânticas distintas, embora ambas bloqueiem o uso contextual? [Consistência, Spec §Casos de borda; Data model §Estados derivados] {auto}

## Critérios e cenários

- [x] CHK007 - Cada história P1 possui teste independente e cenários de aceite que permitem verificar bloqueio, isolamento ou atualização sem depender de outra história? [Mensurabilidade, Spec §Histórias 1 a 3] {auto}
- [x] CHK008 - Os critérios de sucesso proíbem objetivamente atendimento ou gravação antes da guarda e a liberação antes do catálogo completo? [Mensurabilidade, Spec §SC-SCG-001 a 004] {auto}
- [x] CHK009 - Os cenários cobrem concorrência de atualização, recuperação sem reinício de usuário, duas organizações e falhas de leitura? [Cobertura, Spec §Casos de borda; Quickstart §Cenários 2 a 4] {auto}
- [x] CHK010 - A retenção de evidência operacional está alinhada à política configurável do ambiente e ao padrão de 90 dias, sem transformar logs em conteúdo público? [Dependência, Spec §FR-SCG-013; Plan §Modelo de dados e estados] {auto}

## Rastreabilidade

- [x] CHK011 - Todos os requisitos funcionais possuem destino no plano, contrato, modelo de dados, interface ou validação planejada? [Rastreabilidade, Plan §§Guarda global, Guarda por organização, Atualização; Interface §Traceability; Quickstart] {auto}
- [x] CHK012 - Não há marcadores de ambiguidade, placeholders ou decisões materiais postergadas para implementação? [Clareza, Spec, Plan e Interface completos] {auto}

## Notas

Todos os itens são verificáveis somente pelos artefatos da feature e foram resolvidos com evidência citável. Não há gaps ou decisões humanas pendentes nesta rodada.
