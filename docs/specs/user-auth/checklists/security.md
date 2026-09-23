# Security Checklist: Acesso de Usuário

**Purpose**: validar se os requisitos de identidade, sessão, dados e configuração são suficientes para orientar uma implementação segura.  
**Created**: 2026-09-22  
**Feature**: [spec.md](../spec.md)

## Identidade e Credenciais

- [x] CHK001 - A elegibilidade de acesso exige e-mail confirmado e nome de exibição informado, e impede conta não validada de autenticar? [Completude, Spec FR-003–006; Data Model §User] {auto}
- [x] CHK002 - A política de senha é objetiva quanto a comprimento e categorias, sem incluir verificação de senhas vazadas não aprovada? [Clareza, Spec FR-020; Briefing §Qualidade e Padrões] {auto}
- [x] CHK003 - Link e código são definidos como aleatórios, de uso único, expirados em 10 minutos e invalidados juntos ou por substituição? [Cobertura, Constitution §III; Spec FR-004, FR-010–013, FR-022] {auto}
- [x] CHK004 - Falhas de cadastro, login, validação e limite são definidas para não confirmar a existência de uma conta? [Proteção contra enumeração, Spec FR-014–015; Contract §Erros] {auto}
- [x] CHK005 - O comportamento após exceder tentativas e emissões possui parâmetros iniciais e configuração por ambiente documentados? [Clareza, Research §Decision 4; Spec FR-014] {auto}

## Sessões, Dados e Configuração

- [x] CHK006 - A sessão atual pode ser encerrada e as demais podem ser invalidadas sem encerrar a sessão que realiza a ação? [Cobertura, Spec FR-016–017; Data Model §Session] {auto}
- [x] CHK007 - A duração de sessão, a ausência de expiração por inatividade, a opção “Manter-me conectado” e seus parâmetros configuráveis por ambiente estão definidos? [Segurança, Spec FR-023–026; Research §Decision 7] {auto}
- [x] CHK008 - Senhas e segredos de emissão são definidos para armazenamento não recuperável, e telemetria e logs excluem segredos e dados pessoais desnecessários? [Proteção de dados, Constitution §III–IV; Research §Decision 3 e §Decision 5] {auto}
- [x] CHK009 - Credenciais e parâmetros reais de e-mail são mantidos fora do repositório, com modelo de configuração comentado e versionado? [Configuração segura, Briefing §Restrições; Constitution §IV; Research §Decision 5] {auto}
- [x] CHK010 - Sessões invalidadas são excluídas imediatamente, sem retenção? [Privacidade, Spec FR-029; Data Model §Session] {auto}

## Transporte e Ameaças

- [x] CHK011 - A proteção em trânsito, a sessão server-side e a proteção CSRF são requisitos explícitos, sem introduzir token público nesta fase? [Cobertura, Briefing §Qualidade e Padrões; Research §Decision 2; Spec FR-019] {auto}
- [x] CHK012 - A modelagem considera enumeração de contas, reutilização de emissão, expiração, substituição, adivinhação de código, falha de senha e acesso persistente em outra sessão? [Cobertura, Spec §Casos de Borda; Interface §INT-WEB-001–003] {auto}
- [x] CHK014 - A invalidação das demais sessões também revoga autenticações persistentes de outros navegadores, sem revogar a credencial associada à sessão atual? [Cobertura, Spec FR-017; Data Model §Session e §PersistentAuthentication] {auto}
- [x] CHK015 - Emissões de e-mail usadas, vencidas ou substituídas são descartadas sem retenção e logs de segurança possuem retenção padrão configurável de 30 dias? [Privacidade, Spec FR-027–028; Research §Decision 8] {auto}
- [x] CHK013 - O adiamento de entregáveis formais de compliance e privacidade foi explicitamente decidido, sem remover os requisitos técnicos de segurança já aprovados? [Escopo, Spec §Clarificações; Briefing §Compliance] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Não há gaps de requisitos de segurança nesta rodada. O compliance formal permanece explicitamente fora do escopo desta fase.
