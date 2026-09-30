# Requirements Checklist: Rinos Drive

**Propósito**: validar clareza, completude, consistência e rastreabilidade dos requisitos do Rinos Drive antes do backlog de implementação.  
**Criado em**: 2026-09-27  
**Feature**: [spec.md](../spec.md)

## Completude e Escopo

- [x] CHK001 - As apresentações Rinos Drive Pessoal e Rinos Drive Work têm atores, limites e cobertura explícitos? [Completude, Spec §Cobertura de Interfaces; FR-DRIVE-001 a 004] {auto}
- [x] CHK002 - Navegação, árvore, coleções, organização, upload, download, lixeira, visualizações e exportação possuem requisitos funcionais próprios? [Completude, Spec §Cenários; FR-DRIVE-005 a 017] {auto}
- [x] CHK003 - Os comportamentos adiados — compartilhamento, preview, thumbnails, edição, álbuns, busca global, links públicos e quotas bloqueantes — estão excluídos explicitamente? [Escopo, Spec §Cobertura de Interfaces; FR-DRIVE-026 a 028] {auto}
- [x] CHK004 - A fundação existente de arquivo, versão, lixeira, quota e backend é reutilizada sem duplicação de entidades funcionais? [Consistência, Plan §Resumo; Data Model §Reutilização da fundação] {auto}
- [x] CHK005 - A exportação temporária está distinguida de arquivo do workspace e da quota de usuário? [Clareza, Spec FR-DRIVE-012 a 014; Data Model §Nova entidade] {auto}

## Clareza e Cenários

- [x] CHK006 - O conflito de nome define preservação de ambos os itens e resolução segura, inclusive em operações concorrentes, sem sobrescrita ou versão implícita? [Clareza, Spec FR-DRIVE-009; Research §Decisão 5] {auto}
- [x] CHK007 - As regras de seleção mista e operações destrutivas exigem sucesso integral ou recusa, sem alteração parcial silenciosa? [Clareza, Spec US-4; FR-DRIVE-015; Interface INT-WEB-DRIVE-002] {auto}
- [x] CHK008 - Os cenários de expiração, cancelamento e limite de exportação definem indisponibilidade segura e ausência de pacote parcial? [Cobertura, Spec US-6; FR-DRIVE-012 a 014] {auto}
- [x] CHK009 - Os requisitos distinguem capacidade de leitura, edição e administração sem usar termos ambíguos como “acesso” sem escopo? [Clareza, Spec FR-DRIVE-018 a 025] {auto}
- [x] CHK010 - Os casos de borda abrangem concorrência, revogação, contexto alterado, perda de conexão, caminhos perigosos e ativos gerenciados? [Cobertura, Spec §Casos de Borda] {auto}

## Mensurabilidade e Rastreabilidade

- [x] CHK011 - Cada jornada principal possui cenário de aceitação independente e critério de sucesso observável? [Mensurabilidade, Spec US-1 a US-6; SC-DRIVE-001 a 006] {auto}
- [x] CHK012 - Os critérios de sucesso tratam negação, isolamento, conflito, exportação e responsividade de modo verificável? [Mensurabilidade, Spec §Critérios de Sucesso] {auto}
- [x] CHK013 - Spec, plano, contrato, modelo, quickstart e interface mantêm uma cadeia explícita até cada interação? [Rastreabilidade, Plan §Modelo de dados e contratos; Interface §Traceability] {auto}
- [x] CHK014 - As decisões de infraestrutura de job, limpeza, expiração, limites e idempotência estão como requisitos explícitos, não como pressupostos de implementação? [Completude, Spec FR-DRIVE-013 e 014; Plan §Exportação múltipla] {auto}

## Notas

- Itens `{auto}` foram resolvidos contra a SDD com a evidência indicada.
- Não há gap, ambiguidade ou conflito funcional aberto neste checklist.

## Evolução unificada — 2026-09-30

- [x] CHK015 - A ferramenta única, o catálogo de roots e a remoção da dependência de organização ativa definem claramente quais drives aparecem e quais metadados permanecem ocultos? [Completude, Spec FR-DRIVE-001 a 005; Plan §Catálogo e alvos seguros] {auto}
- [x] CHK016 - Compartilhados comigo distingue pasta concedida, arquivo concedido e origem sem conceder navegação por ancestrais, irmãos, lixeira ou quota alheios? [Clareza, Spec FR-DRIVE-032 e 033; Data Model §Evolução unificada] {auto}
- [x] CHK017 - A transferência entre drives diferencia Copy e Move, define confirmação, progresso persistente, conteúdo físico deduplicado e ausência de resultado parcial? [Completude, Spec US-7; FR-DRIVE-029 a 036; Plan §Transferência lógica e reservas] {auto}
- [x] CHK018 - As reservas especificam interseção de ramos, mutações recusadas, revalidação de autorização e recuperação após interrupção sem depender de lock manual? [Cobertura, Spec §Casos de Borda; FR-DRIVE-034 a 036; Research §Decisão 10 e 11] {auto}
- [x] CHK019 - O critério de sucesso de transferência é observável para conflito, revogação, repetição e integridade de origem/destino? [Mensurabilidade, Spec SC-DRIVE-007; Quickstart §Reserva, recuperação e revogação] {auto}
- [x] CHK020 - A entrega unificada distingue root Work navegável de arquivo diretamente concedido, mantendo este somente em Compartilhados comigo e limitado a destinatário usuário? [Completude, Spec FR-DRIVE-032 e 033; Plan §Catálogo e alvos seguros] {auto}
- [x] CHK021 - A criação de transferência define autoridade única para idempotência e limites explícitos de seleção antes da reserva? [Consistência, Spec FR-DRIVE-037; Data Model §Índices e invariantes de transferência; Contrato §Transferências entre painéis] {auto}
