# Security Checklist: Rinos Drive

**Propósito**: validar requisitos de autenticação, autorização, privacidade, validação e descarte seguro do Rinos Drive.  
**Criado em**: 2026-09-27  
**Feature**: [spec.md](../spec.md)

## Autorização e Isolamento

- [x] CHK001 - A resolução de workspace impede que o cliente escolha livremente usuário, tenant ou proprietário? [Segurança, Spec FR-DRIVE-003 e 004; Plan §Resolução de alvo] {auto}
- [x] CHK002 - O Drive Work separa membership de autorização por objeto e exige relação aplicável para membro não administrador? [Segurança, Spec FR-DRIVE-020 a 023; Research §Decisão 2] {auto}
- [x] CHK003 - O administrador ativo possui acesso integral sem relações individuais, preservando a precedência das restrições? [Consistência, Spec FR-DRIVE-022 e 024; Research §Decisão 3] {auto}
- [x] CHK004 - Relações `READ` e `EDIT` têm capacidades e herança por descendentes definidas de forma independente para workspace pessoal e Work? [Completude, Spec FR-DRIVE-018 a 021] {auto}
- [x] CHK005 - Navegação parcial evita enumeração de raiz, ancestrais, irmãos, itens e metadados não autorizados? [Privacidade, Spec §Casos de Borda; Plan §Resolução de alvo] {auto}
- [x] CHK006 - Revogação é revalidada antes de leitura, alteração, exportação e download, com resultado seguro? [Cobertura, Spec FR-DRIVE-010, 023 e 024; Plan §Exportação múltipla] {auto}

## Dados Privados, Entrada e Exportação

- [x] CHK007 - Respostas, erros, telemetria e detalhes excluem hash, backend, caminho, URL pública, conteúdo e informação de item inacessível? [Privacidade, Spec FR-DRIVE-027; Contract §Projeções e erros; Interface §§Telemetry] {auto}
- [x] CHK008 - Upload múltiplo possui limites configuráveis de arquivo, lote, tipo, tamanho e total, com validação servidor como autoridade? [Validação, Spec FR-DRIVE-007 e 008; Plan §Pastas, arquivos e uploads] {auto}
- [x] CHK009 - Nomes perigosos, reservados ou excessivos e colisões dentro de pacote exportado possuem regras explícitas de recusa ou resolução? [Validação, Spec §Casos de Borda; FR-DRIVE-009 e 011] {auto}
- [x] CHK010 - Exportação temporária é privada, expira, não entra na quota, é revalidada no download e não deixa parcial acessível? [Privacidade, Spec FR-DRIVE-010 a 014; Data Model §Transições] {auto}
- [x] CHK011 - A limpeza automática de exportação define prazo, concorrência e idempotência em configuração operacional segura? [Infraestrutura, Spec FR-DRIVE-013 e 014; Plan §Exportação múltipla] {auto}
- [x] CHK012 - Ativos gerenciados pelo sistema continuam excluídos das operações e listagens de Drive? [Isolamento, Spec FR-DRIVE-027; Spec §Casos de Borda] {auto}

## Notas

- A feature não define LGPD ou consentimento, conforme decisão de escopo vigente da plataforma; este checklist cobre somente os requisitos técnicos de segurança autorizados.
- Itens `{auto}` foram resolvidos com as referências indicadas.

## Evolução unificada — 2026-09-30

- [x] CHK013 - O catálogo de drives é filtrado por acesso efetivo, sem tratar membership ou tenant informado pelo cliente como autorização suficiente? [Autorização, Spec FR-DRIVE-002 a 004, 020 a 024; Plan §Catálogo e alvos seguros] {auto}
- [x] CHK014 - A relação direta de arquivo está limitada a `READ` e impede que o destinatário descubra ou altere pasta, ancestrais, irmãos e posse de origem? [Privacidade, Spec FR-DRIVE-033; Data Model §Evolução unificada; Research §Decisão 8] {auto}
- [x] CHK015 - A autorização de origem e destino é revalidada antes do commit lógico de transferência e revogação resulta em falha íntegra? [Autorização, Spec FR-DRIVE-035; Plan §Transferência lógica e reservas] {auto}
- [x] CHK016 - A reserva persistente bloqueia somente mutações intersectantes, preserva leitura autorizada e possui lease/recovery para não produzir bloqueio permanente? [Concorrência, Spec FR-DRIVE-034 e 036; Data Model §Nova entidade file_workspaceTransfer] {auto}
- [x] CHK017 - Status, erros, progresso e telemetria de transferência excluem titular, item, caminho, hash, conteúdo e identificação de operação concorrente? [Privacidade, Plan §Convenções de Borda; Interface INT-WEB-DRIVE-003 e 005] {auto}
- [x] CHK018 - Uma concessão direta de arquivo limita-se a um usuário, permanece read-only e não torna visível a raiz Work, ancestrais ou irmãos? [Autorização, Spec FR-DRIVE-032 e 033; Data Model §Evolução unificada: relações diretas de arquivo] {auto}
