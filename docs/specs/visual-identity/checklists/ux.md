# UX Checklist: Identidade Visual e Preferências de Interface

**Purpose**: validar a qualidade dos requisitos de experiência, hierarquia, adaptação, acessibilidade e microinterações, sem testar a implementação.
**Created**: 2026-09-23
**Feature**: [spec.md](../spec.md)

## Hierarquia e Identidade

- [x] CHK001 - A direção visual aprovada define papéis de cor, tipografia e tratamento premium sem depender de adjetivos vagos? [Clareza, Spec §FR-001; Plan §Temas e paleta; Plan §Tipografia, ícones e ativos] {auto}
- [x] CHK002 - A posição, ausência de moldura e proporção aproximada de 80% do logotipo estão especificadas para a entrada e a criação de conta? [Mensurabilidade, Spec §FR-009; Interface §INT-WEB-VIS-001–002] {auto}
- [x] CHK003 - A hierarquia da entrada remove explicitamente marca textual, descrição e barra de modos anteriores, além de definir os novos controles? [Completude, Interface §INT-WEB-VIS-001] {auto}
- [x] CHK004 - A criação de conta possui conteúdo próprio e não reutiliza indevidamente campos ou estado de login? [Consistência, Spec §FR-012; Interface §INT-WEB-VIS-002] {auto}

## Preferências e Estados de Interação

- [x] CHK005 - Tema, texto, espaçamento e elementos são descritos como quatro grupos de escolhas no mesmo popup, com opções fechadas e independentes? [Completude, Spec §FR-003 e FR-006; Interface §INT-WEB-VIS-005] {auto}
- [x] CHK006 - Tema, idioma e densidades definem aplicação imediata, persistência, restauração e comportamento para configuração inválida? [Cobertura, Spec §FR-002–007; Plan §Preferências e inicialização; Interface §INT-WEB-VIS-005] {auto}
- [x] CHK007 - Estados de processamento, erro, indisponibilidade e sucesso preservam intenção segura e não expõem dados de acesso? [Cobertura, Interface §INT-WEB-VIS-001–004 States] {auto}
- [x] CHK008 - Durações, curva e condição de redução de movimento estão quantificadas sem tornar animação necessária para o entendimento? [Clareza, Plan §Movimento e responsividade; Spec §FR-015] {auto}

## Acessibilidade e Localização

- [x] CHK009 - Critérios de contraste, foco, teclado, leitor de tela, zoom e toque são aplicáveis aos dois temas e às três escalas? [Cobertura, Spec §FR-015; Plan §Temas e paleta; Interface §Shared Accessibility and Input] {auto}
- [x] CHK010 - Os grupos de escolha e os menus descrevem ordem e retorno de foco, Escape, seleção anunciada e identificação textual de idioma? [Acessibilidade, Interface §INT-WEB-VIS-005] {auto}
- [x] CHK011 - O idioma inicial, os quatro catálogos, fallback e preservação de rota e dados seguros estão definidos? [Cobertura, Spec §FR-004–005; Plan §Internacionalização; Interface §INT-WEB-VIS-005] {auto}
- [x] CHK012 - Textos longos e maiores escalas possuem requisitos de reflow sem rolagem horizontal ou sobreposição impeditiva? [Responsividade, Spec §SC-001 e SC-003; Interface §Shared Accessibility and Input] {auto}

## Consistência e Casos de Borda

- [x] CHK013 - Tokens em camadas e componentes compartilhados definem como evitar valores e variações exclusivos de tela? [Consistência, Plan §Tokens; Plan §Componentes compartilhados] {auto}
- [x] CHK014 - Falha de ativo de marca, preferência ausente ou inválida e tradução incompleta possuem comportamento seguro ou fallback definido? [Cobertura de borda, Spec §Edge Cases; Plan §Preferências e inicialização; Plan §Internacionalização] {auto}
- [x] CHK015 - Dados sensíveis são explicitamente excluídos de persistência de preferências, idioma, telemetria e mensagens de interface? [Segurança, Spec §Edge Cases; Interface §INT-WEB-VIS-001–005 Telemetry] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Não há itens `{humano}` nem gaps de requisito abertos neste domínio.
