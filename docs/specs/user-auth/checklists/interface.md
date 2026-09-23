# Interface Checklist: Acesso de Usuário

**Purpose**: validar a clareza, cobertura e rastreabilidade do contrato da interface web de acesso antes da criação do backlog.  
**Created**: 2026-09-22  
**Feature**: [spec.md](../spec.md)

## Cobertura e Inventário

- [x] CHK001 - A única superfície humana aprovada possui cobertura `FULL`, e superfícies futuras estão explicitamente adiadas? [Completude, Interface §Interface Coverage; Spec §Cobertura de Interfaces] {auto}
- [x] CHK002 - Cada fluxo novo de acesso possui um identificador `INT-*`, ponto de entrada e mudança `NEW` definidos? [Rastreabilidade, Interface §Interaction Inventory] {auto}
- [x] CHK003 - O estado atual da interface está documentado como inexistente, sem assumir rota ou componente prévio? [Completude, Interface §Current-State Evidence] {auto}
- [x] CHK004 - O escopo de cada interação evita perfil, recuperação de senha e segundo fator, conforme os itens explicitamente adiados? [Consistência, Interface §INT-WEB-001–003; Spec §Fora de Escopo] {auto}

## Fluxos, Estados e Responsividade

- [x] CHK005 - As jornadas de cadastro, confirmação por código ou link, login por senha, acesso sem senha e controle de sessões têm transições e destinos definidos? [Completude, Interface §INT-WEB-001–003; Spec §Cenários de Usuário] {auto}
- [x] CHK006 - Cada interação resolve os estados initial, loading, empty, ready, processing, success, validation-error, remote-error, offline, access-denied e partial-stale ou justifica `N/A`? [Cobertura, Interface §INT-WEB-001–003 States] {auto}
- [x] CHK007 - O uso alternativo de link em outra aba e de código na página original está definido sem permitir o uso duplo de uma emissão? [Clareza, Interface §INT-WEB-002; Spec FR-004, FR-012] {auto}
- [x] CHK008 - As regras para desktop, tablet, telefone, teclado físico, toque e teclado virtual estão declaradas para cada interação aplicável? [Cobertura, Interface §INT-WEB-001–003] {auto}
- [x] CHK009 - As ações destrutivas de encerramento e invalidação de sessões distinguem a sessão atual das demais e exigem confirmação quando necessário? [Clareza, Interface §INT-WEB-003; Spec FR-016–017] {auto}

## Acessibilidade, Conteúdo e Contratos

- [x] CHK010 - Foco, teclado, leitor de tela, contraste, avisos dinâmicos e alvos de toque são definidos nos fluxos críticos? [Acessibilidade, Interface §INT-WEB-001–003; Interface §Shared Accessibility and Input] {auto}
- [x] CHK011 - Termos e mensagens em português do Brasil evitam revelar e-mail cadastrado, credenciais, códigos, links ou sessões? [Segurança de conteúdo, Interface §Shared Content and Terminology; Spec FR-015, FR-018] {auto}
- [x] CHK012 - Cada interação referencia o contrato de acesso e a rastreabilidade liga `INT-*` a stories, requisitos e critérios de sucesso? [Rastreabilidade, Interface §Traceability; Plan §Convenções de Borda] {auto}
- [x] CHK013 - Cada tela nova possui wireframe de baixa fidelidade e o texto da interface especifica o comportamento que prevalece sobre o desenho? [Completude, Interface §Wireframes; Interface §Interaction Details] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Não há itens `{humano}` ou gaps de interface nesta rodada.
