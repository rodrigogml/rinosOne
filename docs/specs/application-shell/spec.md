# Especificação da Feature: Casca da Aplicação Autenticada

**Feature**: `application-shell`  
**Criada em**: 2026-09-24  
**Status**: Implementada

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Aplicação autenticada | Web responsiva | Usuário autenticado e validado | FULL | Barra global, identidade reduzida, menu pessoal, avatar de fallback, utilitários pessoais e abertura de navegação móvel. | Produtos, módulos, itens de navegação de produto, edição de perfil, envio e recorte de imagem de perfil. |
| Consumidores futuros autorizados | Other | Não definido | DEFERRED | Nenhum nesta feature. | Interfaces nativas, integrações e contratos específicos de módulos. |

## Direção de Produto

A casca autenticada deve ser a camada de orientação estável da plataforma: a pessoa reconhece onde está, acessa sua área pessoal e encontra a navegação sem que essas funções concorram com o conteúdo de cada produto.

A evolução recomendada é progressiva: a barra superior preserva identidade e acesso pessoal; a navegação lateral recebe, somente quando aprovados, os produtos e módulos; e cada produto ocupa o conteúdo principal sem reinventar estruturas globais. Isso evita tanto uma tela inicial vazia e burocrática quanto menus concorrentes quando a plataforma crescer.

## Cenários de Usuário e Testes

### User Story 1 - Orientar-se na área autenticada (Prioridade: P1)

Como usuário autenticado, quero encontrar uma barra superior consistente enquanto navego para reconhecer a plataforma e manter acesso imediato aos controles pessoais.

**Por que esta prioridade**: uma identidade e uma estrutura constantes são a base para a entrada de produtos e módulos futuros sem fragmentar a experiência.

**Teste independente**: após autenticar-se, a pessoa encontra a marca reduzida à esquerda e seu acesso pessoal à direita, mantendo ambos visíveis em cada área autenticada disponível.

**Cenários de aceitação**:

1. **Dado** que concluí a autenticação, **quando** visualizo uma área autenticada, **então** encontro uma única barra superior com a marca reduzida à esquerda e o acesso à minha área pessoal à direita.
2. **Dado** que alterno entre áreas autenticadas disponíveis, **quando** o conteúdo principal muda, **então** a barra superior mantém sua identidade, posição e controles globais.
3. **Dado** que ainda não existem produtos ou módulos aprovados, **quando** uso a casca autenticada, **então** ela não apresenta itens de produto fictícios nem promete funcionalidades indisponíveis.

---

### User Story 2 - Reconhecer a identidade de uma pessoa usuária (Prioridade: P1)

Como usuário autenticado, quero que meu acesso pessoal seja representado por um avatar reconhecível mesmo sem imagem de perfil, para localizar rapidamente o menu da minha conta.

**Por que esta prioridade**: o avatar é o ponto de entrada consistente das ações pessoais e permanece útil antes da futura gestão de perfil.

**Teste independente**: com nome composto, nome único, nome de uma letra e ausência de nome, a pessoa visualiza respectivamente as iniciais ou o símbolo de fallback corretos no avatar circular.

**Cenários de aceitação**:

1. **Dado** que meu nome possui duas ou mais palavras, **quando** não tenho imagem de perfil, **então** o avatar mostra em maiúsculas a primeira letra da primeira e da última palavra do nome.
2. **Dado** que meu nome possui uma única palavra com ao menos duas letras, **quando** não tenho imagem de perfil, **então** o avatar mostra a primeira letra em maiúscula e a segunda em minúscula.
3. **Dado** que meu nome possui uma única letra, **quando** não tenho imagem de perfil, **então** o avatar mostra essa letra em maiúscula.
4. **Dado** que não possuo nome informado, **quando** não tenho imagem de perfil, **então** o avatar mostra o símbolo de interrogação.
5. **Dado** que uma imagem de perfil venha a estar disponível em fase futura, **quando** ela for apresentada, **então** ela ocupa um enquadramento quadrado e é exibida em avatar circular sem alterar o ponto de acesso ao menu pessoal.

---

### User Story 3 - Usar o menu pessoal (Prioridade: P1)

Como usuário autenticado, quero abrir meu menu pessoal para localizar as configurações particulares, preferências de apresentação, idioma e saída da conta.

**Por que esta prioridade**: estas são ações transversais que não pertencem a produtos ou módulos específicos.

**Teste independente**: a pessoa abre o avatar, identifica o item de configurações do usuário, altera preferências visuais ou idioma e encerra a sessão por uma ação explícita.

**Cenários de aceitação**:

1. **Dado** que estou autenticado, **quando** aciono meu avatar, **então** o menu pessoal é aberto e posso fechá-lo sem alterar minha sessão.
2. **Dado** que abro o menu pessoal, **quando** examino seu início, **então** encontro a ação “Configurações do usuário” como destino reservado, sem funcionalidade de perfil nesta fase.
3. **Dado** que abro o menu pessoal, **quando** examino sua região inferior visualmente separada, **então** encontro ações por ícone para preferências visuais, idioma e encerramento da sessão.
4. **Dado** que altero preferências visuais ou idioma pelo menu pessoal, **quando** a escolha é aplicada, **então** minha autenticação e a área autenticada em uso são preservadas.
5. **Dado** que seleciono o encerramento da sessão, **quando** confirmo essa intenção pela ação existente, **então** deixo de estar autenticado e retorno ao acesso público.

---

### User Story 4 - Acessar a navegação em tela estreita (Prioridade: P2)

Como usuário autenticado em telefone, quero abrir a navegação pelo lado esquerdo para que a interface permaneça organizada quando não houver largura para todos os elementos globais.

**Por que esta prioridade**: a navegação móvel precisa existir como uma base consistente antes da chegada de opções de produto.

**Teste independente**: em uma tela estreita, a pessoa abre o acionador de marca, vê um painel vindo da esquerda e o fecha por controle explícito, tecla de escape ou interação fora do painel quando aplicável.

**Cenários de aceitação**:

1. **Dado** que utilizo uma tela estreita, **quando** aciono a marca reduzida, **então** a navegação é aberta a partir da esquerda sem ocultar permanentemente o conteúdo atual.
2. **Dado** que a navegação móvel está aberta antes da aprovação de módulos, **quando** a examino, **então** encontro somente a estrutura de navegação, sem links fictícios de produtos ou funcionalidades.
3. **Dado** que a navegação móvel está aberta, **quando** a fecho, **então** retorno ao mesmo conteúdo, foco e estado de sessão de antes da abertura.

### Casos de Borda

- Nomes com espaços extras são tratados como se possuíssem apenas as palavras preenchidas.
- Caracteres não latinos e letras acentuadas podem ser usados como iniciais; a regra não deve substituí-los por transliteração.
- A ausência, falha ou carregamento incompleto de uma futura imagem de perfil usa imediatamente o fallback textual do avatar.
- A abertura de um menu não pode encobrir indefinidamente controles de confirmação, erro ou segurança do conteúdo principal; em telas estreitas, o conteúdo deve permanecer acessível após fechar o painel.
- A perda de sessão enquanto um menu está aberto encerra os menus e segue o fluxo de acesso público já definido.
- Preferências de apresentação extremas continuam preservando controles tocáveis, foco visível e conteúdo rolável em barra, painel e menu pessoal.

## Requisitos

### Requisitos Funcionais

- **FR-001**: O sistema DEVE apresentar uma única barra superior compartilhada em toda área web autenticada.
- **FR-002**: A barra superior DEVE apresentar a identidade reduzida da plataforma à esquerda e o acionador da área pessoal do usuário à direita.
- **FR-003**: A identidade reduzida DEVE usar os ativos de marca derivados já aprovados, sem alterar seus arquivos de origem.
- **FR-004**: O sistema DEVE manter a barra superior estável enquanto o conteúdo de áreas autenticadas muda.
- **FR-005**: O sistema DEVE exibir o avatar pessoal como elemento circular e usá-lo como acionador do menu pessoal.
- **FR-006**: Sem imagem de perfil disponível, o sistema DEVE gerar o conteúdo do avatar a partir do nome: iniciais da primeira e última palavras para nomes compostos; primeira letra maiúscula e segunda minúscula para nome único com duas ou mais letras; letra maiúscula para nome único de uma letra; e interrogação na ausência de nome.
- **FR-007**: O menu pessoal DEVE disponibilizar “Configurações do usuário” como destino reservado e não deve simular edição ou gestão de perfil antes de essa capacidade ser aprovada.
- **FR-008**: O menu pessoal DEVE reunir, em sua região inferior separada, os controles reutilizáveis de preferências visuais e idioma e uma ação explícita de encerramento da sessão.
- **FR-009**: A alteração de preferência visual ou idioma pelo menu pessoal DEVE preservar a sessão autenticada e o conteúdo autenticado atual quando seguro.
- **FR-010**: A ação de encerramento de sessão no menu pessoal DEVE usar o fluxo de saída já aprovado e impedir acesso autenticado após sua conclusão.
- **FR-011**: Em tela estreita, a identidade reduzida DEVE abrir e fechar um painel de navegação a partir do lado esquerdo.
- **FR-012**: Enquanto nenhum produto ou módulo estiver aprovado, o painel de navegação móvel NÃO DEVE apresentar itens fictícios, atalhos sem destino ou funcionalidades de produto.
- **FR-013**: Barra superior, menu pessoal e painel móvel DEVEM manter funcionamento por teclado, toque, leitor de tela, foco visível, contraste suficiente, rolagem segura e preferência por redução de movimento.
- **FR-014**: Todos os textos, rótulos acessíveis, estados e mensagens dessa feature DEVEM estar disponíveis em português do Brasil, inglês, espanhol e francês.
- **FR-015**: Barra superior, avatar, menu pessoal e painel móvel DEVEM usar os tokens e componentes compartilhados da identidade visual e respeitar as preferências já existentes de tema, escala de texto, espaçamento e tamanho dos elementos.

> Decisões de infraestrutura: N/A (feature de composição da interface, sem processamento periódico, chaves persistentes, recursos externos com expiração ou dados críticos de infraestrutura).

### Entidades Principais

- **Casca autenticada**: contexto permanente da plataforma após a autenticação, que organiza identidade global, navegação e conteúdo principal.
- **Área pessoal**: conjunto de controles associados somente à pessoa autenticada, incluindo destino de configurações reservado, preferências e saída.
- **Avatar de usuário**: representação visual circular da pessoa autenticada, derivada de imagem disponível ou das regras de fallback de nome.
- **Painel de navegação móvel**: região temporária aberta lateralmente em tela estreita, preparada para receber navegação aprovada de produtos e módulos.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-001**: 100% das áreas autenticadas disponíveis exibem a mesma barra superior com marca reduzida e acesso à área pessoal.
- **SC-002**: 100% dos quatro estados de avatar definidos — nome composto, nome único, nome de uma letra e ausência de nome — exibem o fallback correto.
- **SC-003**: 100% das ações do menu pessoal — configurações reservadas, preferências visuais, idioma e saída — são alcançáveis por teclado e toque nos temas claro e escuro.
- **SC-004**: Em telefone, tablet e computador, 100% dos testes de abertura e fechamento da navegação e do menu pessoal preservam o conteúdo autenticado atual e a sessão, exceto quando a pessoa escolhe encerrar a sessão.
- **SC-005**: 100% dos textos e rótulos acessíveis introduzidos pela casca estão completos nos quatro idiomas previstos.
