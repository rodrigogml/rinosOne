# UX Checklist: Casca da Aplicação Autenticada

**Propósito**: validar a qualidade dos requisitos de orientação, interação e acessibilidade da experiência autenticada antes de implementar.  
**Criado**: 2026-09-24  
**Feature**: [spec.md](../spec.md)

## Hierarquia e Orientação

- [x] CHK-UX-001 - A hierarquia separa identidade global, área pessoal e conteúdo de produto sem criar uma segunda navegação concorrente? [Clareza, Spec §Direção de Produto; Interface §INT-WEB-SHELL-001] {auto} — barra, menu pessoal e conteúdo principal têm responsabilidades distintas.
- [x] CHK-UX-002 - A marca tem comportamento específico por form factor sem sugerir destinos inexistentes? [Consistência, Interface §INT-WEB-SHELL-001] {auto} — é identidade em desktop e acionador de painel móvel somente em telefone.
- [x] CHK-UX-003 - A área pessoal é visualmente e semanticamente separada da futura navegação de produtos? [Cobertura, Spec §US-003; Interface §INT-WEB-SHELL-002] {auto} — menu do avatar contém apenas destino reservado, utilitários e saída.

## Interação e Estados

- [x] CHK-UX-004 - O menu pessoal define item reservado sem fazer a pessoa acreditar que perfil já está disponível? [Clareza, Spec §FR-007; Interface §INT-WEB-SHELL-002] {auto} — o item não navega, não solicita dados e comunica indisponibilidade futura.
- [x] CHK-UX-005 - Os controles de preferência e idioma preservam sessão e conteúdo em uso ao serem movidos para o menu pessoal? [Consistência, Spec §FR-008–009; Interface §INT-WEB-SHELL-002] {auto} — os contratos locais existentes são reutilizados sem transição de rota ou sessão.
- [x] CHK-UX-006 - A saída possui feedback e recuperação definidos sem expor dados ou perder a sessão visual antes da confirmação? [Cobertura, Interface §INT-WEB-SHELL-002] {auto} — falha preserva menu e estado; sucesso remove a casca pelo fluxo existente.
- [x] CHK-UX-007 - O painel móvel define um estado vazio honesto e útil sem prometer módulos? [Clareza, Spec §FR-012; Interface §INT-WEB-SHELL-003] {auto} — mensagem discreta explica a ausência de destinos e não é um atalho.

## Acessibilidade e Inclusão

- [x] CHK-UX-008 - As regras de avatar preservam caracteres acentuados e alfabetos não latinos, sem normalização que reduza a identidade da pessoa? [Inclusão, Spec §Casos de Borda; Plan §Decisão 4] {auto} — a derivação é textual e não translitera nomes.
- [x] CHK-UX-009 - Foco, teclado, leitor de tela, toque, contraste e redução de movimento são especificados para os fluxos críticos? [Cobertura, Spec §FR-013; Interface §Cross-Surface Rules] {auto} — todos os métodos de entrada e requisitos estão explícitos.
- [x] CHK-UX-010 - Painéis e menus possuem meios redundantes de fechamento e devolução de foco? [Acessibilidade, Interface §INT-WEB-SHELL-002–003] {auto} — Escape, acionador, controle explícito e camada externa quando aplicável são definidos.
- [x] CHK-UX-011 - Escalas visuais confortáveis e zoom preservam área de toque, rolagem e conteúdo sem corte? [Responsividade, Spec §Casos de Borda; Interface §Responsive/Adaptive Behavior] {auto} — requisitos exigem reflow e rolagem natural sem overflow horizontal.

## Consistência Visual e Linguagem

- [x] CHK-UX-012 - Os novos elementos dependem exclusivamente de tokens e componentes compartilhados, mantendo os dois temas e as três escalas? [Consistência, Spec §FR-015; Plan §Componentes e Estilos Compartilhados] {auto} — a casca consome a fundação visual aprovada e não cria estilos exclusivos de tela.
- [x] CHK-UX-013 - A separação inferior de utilitários do menu é comunicada além de cor ou posição? [Acessibilidade, Interface §INT-WEB-SHELL-002] {auto} — divisor, rótulos acessíveis e agrupamento semântico são exigidos.
- [x] CHK-UX-014 - Os termos novos têm forma canônica e cobertura nos quatro idiomas, inclusive em rótulos acessíveis? [Localização, Interface §Shared Content and Terminology; Spec §FR-014] {auto} — a terminologia e a regra de catálogo estão registradas.

## Notas

- Todos os itens avaliam a qualidade e a completude dos requisitos UX; não são testes de implementação.
- Não há decisão de produto pendente que bloqueie a criação do backlog.
