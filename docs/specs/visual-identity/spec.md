# Feature Specification: Identidade Visual e Preferências de Interface

**Feature**: `visual-identity`
**Created**: 2026-09-23
**Status**: Draft

## Clarifications

### Session 2026-09-23

- Q: Qual paleta deve ser aplicada sem configuração de pessoa usuária e como as demais serão disponibilizadas? -> A: Rubi Industrial é o padrão, com L8 no claro e D8 no escuro; as dez famílias ficam prontas em tokens, mas a seleção individual fica para o perfil futuro.

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
| --- | --- | --- | --- | --- | --- |
| Entrada e criação de conta | Web responsiva | Visitante | FULL | Reconhece a marca, inicia acesso ou criação de conta, escolhe idioma e preferências visuais. | Alteração de dados de perfil e sincronização de preferências com uma conta. |
| Confirmação e área de acesso | Web responsiva | Visitante e usuário autenticado | FULL | Mantém a identidade visual, as preferências e os controles reutilizáveis durante os fluxos de acesso. | Preferências compartilhadas entre dispositivos. |
| Produtos e módulos futuros | Web responsiva | Usuários futuros | DEFERRED | Receberão a fundação visual e os componentes desta feature quando forem aprovados. | Criação de funcionalidades de produto ou módulo. |

## User Scenarios & Testing

### User Story 1 - Reconhecer e iniciar o acesso pela identidade da plataforma (Priority: P1)

Como visitante, quero encontrar uma entrada de acesso clara, sofisticada e coerente com a marca para iniciar sessão, entrar sem senha ou criar uma conta sem precisar entender opções técnicas.

**Why this priority**: a tela de entrada é o primeiro contato com a plataforma; a identidade e a clareza desta jornada formam a base visível da iniciativa.

**Independent Test**: abrir a entrada em computador e telefone, verificar a presença da marca, os campos e as ações exigidas, e iniciar cada jornada disponível.

**Acceptance Scenarios**:

1. **Given** que a pessoa visitante abre a entrada de acesso, **When** a tela termina de carregar, **Then** ela vê o logotipo paisagem centralizado acima do cartão de acesso, sem moldura própria, e o conjunto permanece centralizado verticalmente.
2. **Given** que a pessoa visitante está na entrada de acesso, **When** não informa senha, **Then** a ação principal é identificada como “Entrar sem senha” e inicia a jornada de acesso por e-mail após validar o endereço.
3. **Given** que a pessoa visitante informa qualquer senha, **When** observa a ação principal, **Then** ela é identificada como “Entrar” e inicia a jornada de acesso por senha quando acionada.
4. **Given** que a pessoa visitante deseja se cadastrar, **When** escolhe “Criar conta”, **Then** ela segue para uma tela própria com identidade equivalente, nome de exibição, e-mail e ação de criação de conta.

---

### User Story 2 - Usar a plataforma no tema preferido (Priority: P1)

Como visitante ou usuário autenticado, quero escolher apresentação clara ou escura, sem perder o contexto da jornada, para utilizar a plataforma confortavelmente em diferentes condições de iluminação.

**Why this priority**: os dois temas são requisito de toda a experiência, inclusive antes da autenticação.

**Independent Test**: alternar o tema em cada tela de acesso, recarregar a página e iniciar nova sessão de navegação, verificando a permanência da escolha e a legibilidade dos controles.

**Acceptance Scenarios**:

1. **Given** que ainda não há escolha visual manual, **When** a pessoa abre a plataforma, **Then** a apresentação acompanha a preferência clara ou escura do dispositivo.
2. **Given** que a pessoa está em qualquer tela de acesso, **When** aciona o seletor de tema, **Then** a apresentação alterna entre claro e escuro sem perder autenticação, dados seguros já preenchidos ou etapa da jornada.
3. **Given** que a pessoa escolheu manualmente um tema, **When** retorna à plataforma em nova sessão do navegador, **Then** a escolha é restaurada antes que a interface se torne visível.
4. **Given** qualquer um dos temas, **When** a pessoa percebe uma ação, estado de foco ou erro, **Then** ela consegue compreendê-lo sem depender apenas de cor.

---

### User Story 3 - Escolher o idioma da interface (Priority: P1)

Como visitante ou usuário autenticado, quero escolher meu idioma em qualquer ponto da jornada para compreender a plataforma sem perder o contexto atual.

**Why this priority**: a plataforma precisa nascer pronta para os quatro idiomas previstos, inclusive no acesso público.

**Independent Test**: abrir o seletor de idioma em uma tela pública e em uma autenticada, escolher cada idioma e confirmar que a tela permanece na mesma jornada com conteúdo traduzido.

**Acceptance Scenarios**:

1. **Given** uma primeira visita sem preferência anterior, **When** a interface é aberta, **Then** ela é apresentada em português do Brasil.
2. **Given** que a pessoa abre o seletor de idioma, **When** visualiza suas opções, **Then** encontra português do Brasil, inglês, espanhol e francês, cada qual com bandeira e nome legível.
3. **Given** que a pessoa escolhe outro idioma durante uma jornada, **When** a escolha é concluída, **Then** a interface é recarregada no mesmo idioma sem encerrar a autenticação, trocar a rota ou descartar a funcionalidade em curso quando ela puder ser preservada com segurança.
4. **Given** que a pessoa escolheu um idioma, **When** retorna em nova sessão do navegador, **Then** o idioma escolhido é restaurado.

---

### User Story 4 - Adaptar a densidade visual às necessidades pessoais (Priority: P2)

Como visitante ou usuário autenticado, quero ajustar fonte, espaçamento e tamanho dos controles de forma independente para trabalhar com mais conforto ou maior densidade de informação.

**Why this priority**: a adaptação é parte da proposta premium e amplia a acessibilidade sem criar versões divergentes da interface.

**Independent Test**: selecionar cada nível de cada ajuste, navegar pelas telas de acesso e recarregar o navegador, verificando que a escolha se mantém e que textos, campos, ícones e ações permanecem utilizáveis.

**Acceptance Scenarios**:

1. **Given** que a pessoa acessa as preferências visuais, **When** escolhe uma escala de fonte, **Then** pode selecionar compacta, padrão ou confortável e toda a interface se adapta de maneira proporcional.
2. **Given** que a pessoa acessa as preferências visuais, **When** escolhe espaçamento, **Then** pode selecionar compacto, padrão ou confortável e os agrupamentos se adaptam de maneira coerente.
3. **Given** que a pessoa acessa as preferências visuais, **When** escolhe tamanho de componentes, **Then** pode selecionar compacto, padrão ou amplo e campos, ícones e controles se adaptam juntos.
4. **Given** que uma preferência foi alterada, **When** a pessoa segue para outra tela ou retorna em nova sessão do navegador, **Then** a escolha permanece ativa sem afetar as demais dimensões de preferência.

---

### User Story 5 - Encontrar uma experiência consistente e acessível (Priority: P2)

Como pessoa usuária, quero que telas e controles semelhantes tenham aparência e comportamento coerentes para navegar com confiança, independentemente do tema, idioma ou dispositivo.

**Why this priority**: a reutilização visual evita inconsistências no acesso atual e estabelece a fundação para módulos futuros.

**Independent Test**: comparar os controles equivalentes nas quatro telas de acesso nos dois temas e nas três escalas, verificando rótulos, estados, foco, contraste, teclado e toque.

**Acceptance Scenarios**:

1. **Given** as telas de acesso, **When** a pessoa encontra campos, ações, alertas ou seletores equivalentes, **Then** eles têm aparência, estados e comportamento consistentes.
2. **Given** que a pessoa navega por teclado ou por toque, **When** chega a qualquer controle interativo, **Then** consegue operá-lo e identificar claramente o foco e o resultado de sua ação.
3. **Given** que o dispositivo solicita redução de movimento, **When** a interface é exibida ou muda de estado, **Then** os efeitos visuais são reduzidos de forma a não dificultar a compreensão.
4. **Given** que a pessoa amplia textos ou escolhe as maiores escalas visuais, **When** navega em telefone, tablet ou computador, **Then** os controles e conteúdos permanecem legíveis, alcançáveis e sem sobreposição impeditiva.

### Edge Cases

- A escolha de idioma durante um formulário preserva somente os dados que podem ser mantidos com segurança; senhas e segredos nunca são persistidos para esse fim.
- Se uma tradução não estiver disponível, a interface usa o texto em português do Brasil de maneira compreensível, sem exibir identificadores internos.
- Uma preferência visual inválida, ausente ou incompatível é substituída por seu valor padrão sem impedir o uso da plataforma.
- Em telas muito estreitas, o logotipo, cartão, controles e seletor de preferências permanecem em uma única coluna operável sem rolagem horizontal.
- Contraste, foco e distinção de estado permanecem suficientes em todos os temas e níveis de ajuste, inclusive para ações destrutivas ou indisponíveis.

## Requirements

### Functional Requirements

- **FR-001**: O sistema DEVE apresentar uma identidade tecnológica, moderna e premium, usando Rubi Industrial como paleta padrão, neutros grafite e prata e acabamento discreto de fibra de carbono no tema escuro.
- **FR-002**: O sistema DEVE disponibilizar apresentação clara e escura e usar a preferência do dispositivo quando não houver escolha manual.
- **FR-003**: O sistema DEVE permitir que a pessoa abra um único controle reutilizável de preferências visuais por ícone e altere nele tema, escala de fonte, espaçamento e tamanho de componentes, mantendo cada escolha em novas sessões do navegador.
- **FR-004**: O sistema DEVE disponibilizar português do Brasil, inglês, espanhol e francês, iniciando em português do Brasil na ausência de escolha prévia.
- **FR-005**: O sistema DEVE permitir a troca de idioma por um seletor reutilizável que exiba bandeira e nome de cada idioma e preserve a jornada em curso sempre que isso for seguro.
- **FR-006**: O sistema DEVE permitir ajustes independentes de fonte, espaçamento e tamanho de componentes nos níveis compacto, padrão e confortável/amplo, respectivamente.
- **FR-007**: O sistema DEVE preservar no navegador as escolhas de tema, idioma e os três ajustes visuais, restaurando-as em novas sessões sem exigir autenticação.
- **FR-008**: O sistema DEVE garantir que elementos visuais equivalentes utilizem os mesmos padrões de apresentação, interação, foco, estados e mensagens em todas as telas de acesso.
- **FR-009**: O sistema DEVE apresentar o logotipo paisagem centralizado acima do cartão de acesso, com largura visual equivalente a aproximadamente 80% da largura do cartão e sem moldura própria.
- **FR-010**: O sistema DEVE usar o ícone de marca como identidade reduzida da aplicação, incluindo o ícone de navegador e a instalação como aplicativo web.
- **FR-011**: A entrada de acesso DEVE conter e-mail, senha, opção “Manter-me conectado” e uma ação principal cujo texto e comportamento dependam de a senha estar vazia ou preenchida.
- **FR-012**: A entrada de acesso DEVE oferecer uma ação “Criar conta” que leve a uma jornada própria contendo nome de exibição, e-mail e ação de criação de conta.
- **FR-013**: A entrada de acesso e a criação de conta DEVEM disponibilizar, após seus controles principais, os dois controles centralizados de preferências visuais e idioma.
- **FR-014**: O sistema DEVE aplicar a identidade e os controles reutilizáveis às telas de entrada, criação de conta, confirmação por e-mail e área autenticada.
- **FR-015**: O sistema DEVE manter contraste suficiente, foco visível, operação por teclado, comunicação não dependente só de cor, suporte a zoom e adaptação a redução de movimento em todos os temas e níveis de preferência.
- **FR-016**: O sistema NÃO DEVE alterar os arquivos originais de logotipo e ícone fornecidos; as cópias de uso na aplicação serão tratadas como ativos derivados.
- **FR-017**: O sistema DEVE manter em tokens centrais as dez famílias nomeadas Ametista Técnica, Teal Arquitetônico, Cobre Refinado, Vinho Imperial, Esmeralda Sóbria, Ouro Mineral, Índigo Orbital, Rubi Industrial, Ciano Profundo e Coral Executivo, cada uma com variantes claro e escuro.
- **FR-018**: Na ausência de escolha persistida por perfil, o sistema DEVE aplicar Rubi Industrial (`L8` claro e `D8` escuro); a seleção individual de família é futura e não DEVE ser exposta nos controles atuais.

> Decisões de infraestrutura: N/A (a feature não cria processamento periódico, chaves persistentes, recursos externos com expiração ou dados críticos de infraestrutura).

### Key Entities

- **Preferência visual local**: conjunto de escolhas da pessoa usuária para tema, idioma, escala de fonte, espaçamento e tamanho de componentes, independente de autenticação nesta fase; não inclui escolha de família cromática.
- **Família cromática**: catálogo estático de tokens com dez combinações claro/escuro; Rubi Industrial é a resolução padrão até a futura preferência de perfil.
- **Token visual**: definição central de um atributo de apresentação ou de uma relação visual reutilizável, capaz de permanecer coerente quando uma preferência muda.
- **Componente compartilhado**: elemento de interação ou apresentação usado de forma consistente por mais de uma tela ou preparado para essa reutilização.
- **Ativo de marca derivado**: cópia de uso do logotipo ou ícone original, adaptada ao local em que será exibida sem modificar a fonte fornecida.

## Success Criteria

### Measurable Outcomes

- **SC-001**: 100% das telas de acesso previstas são apresentadas nos temas claro e escuro sem quebra de layout em telefone, tablet e computador.
- **SC-002**: 100% dos controles de tema e idioma preservam a autenticação e a rota atual ao serem acionados durante uma jornada compatível.
- **SC-003**: 100% das combinações dos três ajustes de fonte, espaçamento e tamanho de componentes permanecem operáveis por teclado e toque nas telas de acesso.
- **SC-004**: 100% dos textos, ações e mensagens das telas de acesso possuem conteúdo completo nos quatro idiomas previstos; o fallback em português do Brasil é usado somente como proteção para uma falha inesperada de catálogo.
- **SC-005**: 100% dos fluxos visuais avaliados atendem aos critérios definidos de contraste, foco visível, zoom e redução de movimento nos dois temas.
