# Briefing da Iniciativa: Padrão de Listagens e Seleção Avançada

**Data**: 2026-09-30
**Status**: Em evolução
**Versão**: 1.0

---

## 1. Visão e Propósito

**O que é**: uma fundação reutilizável para telas de listagem da plataforma, aplicada inicialmente à janela de Pessoas.

**Problema que resolve**: evitar que cada módulo crie tabelas, buscas, ações e regras de seleção diferentes, ambíguas ou limitadas ao conteúdo já carregado na tela.

**Proposta de valor**: oferecer listas densas e produtivas em desktop, plenamente funcionais em telas estreitas, com ações e mensagens consistentes em toda a plataforma.

## 2. Usuários e Stakeholders

| Ator | Papel | Ações principais |
| --- | --- | --- |
| Pessoa usuária autorizada de uma organização | Consulta e administra registros de módulo. | Busca, ordena, seleciona, visualiza e executa ações autorizadas sobre registros. |
| Administrador da Plataforma | Administra catálogos ou rotinas globais. | Usa o mesmo padrão de listagem quando a funcionalidade o exigir. |

**Stakeholder de decisão**: responsável pelo produto e identidade visual da plataforma.

## 3. Interfaces e Canais

| Interface/Canal | Usuários | Dispositivos/Plataformas | Cobertura | Conectividade | Paridade esperada |
| --- | --- | --- | --- | --- |
| Web responsiva | Usuários autorizados | Desktop, tablet e telefone | MVP | Online | Completa; desktop privilegia tabela e painel lateral, telefone mantém ações e usa a área da janela com Voltar. |

**Restrições tecnológicas já obrigatórias**: Laravel, Vue 3, TypeScript, API JSON e design system existente. O motor de tabela adotado é TanStack Table com virtualização TanStack.

## 4. Escopo

### MVP (Essencial)

1. Estrutura de listagem com barra superior, tabela expansível e barra inferior de ações.
2. Catálogo central de ações canônicas e factory visual reutilizável.
3. Campo de busca com ícone integrado e acionamento por Enter.
4. Tabela virtualizada com ordenação no servidor, colunas dinâmicas, visibilidade, ordem e largura configuráveis.
5. Seleção explícita de IDs, inclusive seleção de todos os resultados de uma busca de até 10.000 itens.
6. Exibição lazy de resultados e da coleção de itens selecionados.
7. Painel local de visualização de registro, dentro da janela que o abriu.
8. Aplicação inicial na janela de Pessoas.

### Pós-MVP (Desejável)

1. Filtros específicos de cada domínio.
2. Edição em célula, menus contextuais, agrupamentos e rodapés de sumarização por coluna quando um módulo os exigir.
3. Catálogo visual próprio de `Message Dialog`.

### Fora de Escopo

- Paginação visível na interface.
- Definição visual definitiva de diálogos de mensagem.
- Filtros de Pessoas nesta etapa.
- Persistência de seleção entre recargas da página ou reaberturas da janela.

## 5. Prioridades e Trade-offs

**Ordem de prioridade**: clareza operacional > consistência entre módulos > funcionalidade completa responsiva > desempenho > velocidade de implementação.

**Decisões explícitas**:

- A tabela não expõe paginação; blocos são carregados conforme a rolagem se aproxima do fim.
- Ordenação e busca são autoridades do servidor. A tabela trabalha com uma lista ordenada de IDs e carrega objetos apenas para faixas visíveis.
- Preferências de colunas são locais ao navegador e identificadas pela tabela; existe ação para restaurar o padrão.
- A seleção é sempre uma coleção explícita de IDs; não há modelo de "todos exceto" nem linguagem de registros excluídos da seleção.
- Uma busca pode manter seleções anteriores somente quando a pessoa ativar `Manter seleção entre buscas`.
- Em qualquer ação visível, incompatibilidade de seleção é explicada no clique; comandos não são desabilitados apenas porque a seleção atual não atende à ação.

## 6. Definições Fechadas da Listagem de Pessoas

### Estrutura e colunas

- A janela abre inicialmente na listagem; a edição é uma navegação própria da mesma janela.
- Barra superior: busca e, futuramente, filtros; no extremo direito, controles de tabela e seleção.
- Tabela: Nome de exibição, Tipo, CPF/CNPJ e Situação; não exibe Contatos, que são relação 1:N.
- Barra inferior, alinhada à direita: `Inserir`, `Duplicar`, `Editar`, `Visualizar`, `Excluir`.
- Barra de status permanente abaixo da tabela, independente de rodapés de sumarização: `Y registros encontrados`, acrescido de `X itens selecionados` e, quando aplicável, `W selecionados ocultos pela busca¹`.

### Ações e ícones

| Ação | Rótulo | Ícone |
| --- | --- | --- |
| Inserir | Inserir | `dataInsert` |
| Duplicar | Duplicar | `dataDuplicate` |
| Editar | Editar | `dataEdit` |
| Visualizar | Visualizar | `dataView` |
| Excluir | Excluir | `dataDelete` |

- O destino Pessoas usa `personCompany` no menu e na janela.
- O campo de busca usa `search` internamente, sem borda, moldura ou texto no controle icônico. O hint é `Buscar...`, em itálico e tom discreto.
- Ações autorizadas permanecem acionáveis. Sem seleção, com seleção múltipla ou outra condição incompatível, a ação informa a condição e como corrigi-la. Permissões continuam controlando a visibilidade e o servidor continua sendo a autoridade.

### Visualização

- `Visualizar` abre somente por comando explícito sobre uma seleção única.
- Duplo clique visualiza exclusivamente a linha recebida no evento e não altera a seleção existente.
- No desktop, o painel entra pela lateral **dentro da própria janela**. No responsivo, ocupa a área da janela e oferece `Voltar`.

### Seleção e resultados lazy

- `Selecionar todos` alcança todo resultado da busca atual, até 10.000 registros, inclusive itens ainda não carregados na viewport.
- Acima do limite, a interface informa o total encontrado, confirma que nenhum registro foi selecionado e orienta a refinar a busca.
- `Manter seleção entre buscas` é botão liga/desliga, desligado por padrão. Desligado, nova busca, filtro ou ordenação limpa a seleção; ligado, preserva e acrescenta IDs selecionados.
- `Exibir selecionados` é botão liga/desliga e mantém a tabela lazy: troca a fonte pela lista de IDs selecionados, carregando objetos em blocos.
- `Incluir selecionados ocultos` é botão liga/desliga. A busca usa a união lógica entre seus critérios e os IDs selecionados, respeita a ordenação atual e identifica com `¹` as linhas fora dos critérios. Esses itens não alteram o total encontrado.
- A seleção pertence somente à instância aberta da janela e não sobrevive a recarga do navegador ou reabertura.

### Mensagens

- Mensagens descrevem o fato, o efeito e a próxima ação. Não usar textos genéricos como "Não foi possível" sem contexto.
- Exemplos: informar que apenas um registro deve estar selecionado para editar; que nenhum registro foi selecionado; ou que a busca excedeu 10.000 itens e precisa ser refinada antes de selecionar todos.
- A aparência e o ciclo de foco do `Message Dialog` serão definidos em SDD própria. Até lá, reutilizar a solução existente do sistema.

## 7. Stack Técnica

| Camada | Tecnologia | Justificativa |
| --- | --- | --- |
| Interface | Vue 3 e TypeScript | Stack da aplicação e componentes do design system. |
| Motor de tabela | TanStack Table | Estado controlado de tabela, colunas, ordenação e seleção sem impor sistema visual concorrente. |
| Virtualização | TanStack Virtual | Renderiza somente a faixa visível sem paginação exposta. |
| API | Laravel JSON versionada | Busca, ordenação e carregamento lazy são autoridades do servidor. |

## 8. Qualidade e Padrões

**Padrões adotados**:

- Teclado, ponteiro e toque têm alternativas equivalentes; nenhuma ação depende exclusivamente de hover, duplo clique ou gesto.
- As ações mantêm nome acessível, ícone consistente e textos localizados.
- O modo responsivo não reduz capacidade funcional; apenas adapta a apresentação da tabela e dos painéis.
- Controles, estados de seleção e resultados preservam contraste, foco e mensagens compreensíveis nos dois temas.

**Compliance**: o padrão não cria nova categoria de dado. Módulos consumidores preservam suas próprias regras de autorização e privacidade.

## 9. Visão de Futuro

**6 meses**: os módulos de catálogo da plataforma usam componentes e ações consistentes de listagem.

**12 meses**: o padrão contempla filtros especializados, agregações, edição de célula, menus contextuais e preferências de tabela mais amplas quando houver requisitos aprovados.

**Riscos conhecidos**:

- Retornar ou transmitir listas muito grandes de IDs pode prejudicar memória e tráfego; o limite de 10.000 protege a seleção total nesta fase.
- A união de uma busca com IDs selecionados requer contrato explícito e validação de tenant/autorização no servidor.
- Uma mensagem explicativa mal escrita pode manter a ação tecnicamente correta, mas ainda deixar a pessoa sem orientação útil.

---

## Itens a Definir

| Item | Dimensão | Impacto |
| --- | --- | --- |
| Contrato exato para lista ordenada de IDs, faixas lazy e união com IDs selecionados | API e desempenho | Alto |
| Comandos e critérios específicos de filtros de Pessoas | Funcional | Alto |
| Detalhes e agrupamentos do painel de visualização de Pessoas | Interface | Médio |
| Padrão visual e comportamental de `Message Dialog` | Design system | Médio |
| Interações de seleção por teclado e toque para seleção em massa | Acessibilidade | Médio |

**Próximo passo acordado**: implementar as decisões fechadas na janela de Pessoas, validar visualmente e continuar a evoluir este briefing antes de produzir a SDD formal do padrão.

## Registro de Implementação em Andamento

- A janela de Pessoas já usa TanStack Table para o modelo de linhas e TanStack Virtual para limitar a quantidade de linhas renderizadas no DOM.
- A API de Pessoas consulta e ordena no servidor e entrega blocos de até 200 registros; a interface não expõe paginação.
- A seleção mantém IDs explícitos, inclusive para `Selecionar todos`, limitada a 10.000 registros. A API calcula os itens selecionados que não pertencem à busca atual sem reativar ou materializar seus objetos.
- A preferência local de colunas de Pessoas já guarda visibilidade, ordem e largura, com restauração explícita do padrão.
- O painel de visualização é local à janela de Pessoas. Ele não é aberto por seleção simples e o duplo clique visualiza apenas o registro clicado.

> [!NOTE]
> A validação visual e a definição dos filtros de Pessoas continuam pendentes. Este registro não substitui a SDD visual, que só será iniciada depois que o briefing estiver fechado.
