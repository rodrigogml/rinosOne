---
name: rinos-ui-development
description: Desenvolva ou altere a interface Vue do Rinos One conforme os componentes compartilhados e o guia visual vivo. Use em telas, controles, diálogos, toasts, formulários e padrões de interação; não use para trabalho exclusivamente de backend.
---

# Desenvolvimento de UI do Rinos One

Use esta skill sempre que uma alteração afetar a interface Vue, seus controles ou sua experiência de interação.

## Fonte de verdade e leitura inicial

Antes de implementar, identifique a categoria visual afetada no guia em `resources/js/developer-guide/` e leia o componente compartilhado correspondente em `resources/js/design-system/`. A rota `/dev` é a fonte normativa para os padrões visuais; o contrato em código do componente compartilhado deve implementá-la. Se o guia e uma especificação divergirem, reporte a divergência antes de escolher uma referência.

Não copie tokens, regras de aparência ou estruturas de componentes para a tela. Reutilize o componente compartilhado. Quando uma regra ainda não puder ser expressa pelo componente, evolua o design system e o guia antes de adotá-la em uma tela.

## Força normativa do guia

Trate os blocos **Normas obrigatórias**, **Obrigatório**, **Proibido** e **Exceção** do guia como contrato, não como exemplos. Um exemplo demonstra a aplicação do contrato; ele não autoriza sinônimos, ícones, variantes ou CSS alternativos.

- **Obrigatório:** use o componente, comando, modelo e API central definidos para a categoria.
- **Proibido:** não recrie localmente uma combinação já normatizada, mesmo que a aparência pareça equivalente.
- **Exceção:** se a necessidade não estiver coberta pelo guia ou pelo registro central, interrompa a implementação visual e proponha a evolução do padrão. Não escolha uma variação local.

Ao atualizar uma página do guia, separe visualmente o que é exemplo do que é norma e inclua, para cada padrão novo, as formas obrigatória, proibida e excepcional quando aplicáveis.

## Ações e comandos

- Use exclusivamente `UIRinoButton` para botões de ação, com texto, ícone ou ambos, em qualquer contexto. Não existem componentes públicos separados para botões compactos ou de listagem. Elementos de semântica especializada (tabs, árvores e links) preservam seus próprios contratos; não servem de alternativa para comandos comuns.
- Leia `resources/js/design-system/UIRinoButton.vue`, `rinoButtonCommands.ts` e `resources/js/developer-guide/buttons/ButtonsGuide.vue` antes de alterar botões. Comandos conhecidos usam `command` do registro único; novos comandos transversais devem evoluir o registro e o guia antes da adoção.
- A resolução obrigatória é propriedade explícita → preset do comando → default. O default sem comando é secondary, label/icon null e toggle false. `undefined` herda; `null` remove label/icon herdados; `false` sobrepõe toggle herdado. Sobreposições são permitidas para contextos específicos, desde que preservem a semântica e não criem CSS local ou sinônimos para comandos transversais.
- `label` e `accessibleLabel` são chaves i18n, não textos traduzidos; use `labelParams` para parâmetros. `icon` recebe o identificador do catálogo, não caminho ou imagem em slot. Sem label/icon é erro; somente ícone exige nome acessível, pelo preset ou accessibleLabel explícito.
- Alternância usa `toggle` ou preset, com estado interno inicialmente false ou `v-model:selected`. Não inverta o estado manualmente no click nem escreva aria-pressed: o componente central gera esse atributo. Atributo aria-expanded permanece para abrir/fechar conteúdo.
- Para exclusividade, crie `createRinoToggleGroup()` por janela/formulário e compartilhe a referência entre os membros. toggleGroup força toggle true. O grupo tem seleção obrigatória por padrão: clicar no ativo não o desliga, e outro membro troca a seleção. Sem seleção inicial, após a montagem o primeiro disponível é selecionado; se o ativo sair ou a tela limpar a seleção, o grupo recompõe a seleção. Quando não houver nenhum selecionado nem membro disponível, aguarda disponibilidade. Use `createRinoToggleGroup({ required: false })` somente se o contexto permitir zero selecionados. É proibido implementar esta política em handlers locais, usar nome/string global ou compartilhar grupos entre janelas. Com v-model, aceite update:selected inclusive na inicialização e recomposição; estados somente de leitura não garantem os invariantes do grupo.
- disabled/loading bloqueiam clique e alternância; type tem default button; click entrega MouseEvent e a referência expõe focus(). Use o mesmo componente para os botões do serviço de diálogos.
- Ícones decorativos são ocultos de leitores de tela; controles somente por ícone têm nome acessível explícito.

## Diálogos, mensagens e validação

- Para diálogos padronizados de mensagem ou decisão, é **obrigatório** usar o serviço `dialog` e seus modelos. Use `dialog.openValidation` para a lista de validações após tentativa de salvar.
- Erros de validação continuam junto aos campos. O diálogo Validation é o índice para localizar problemas em abas, seções ou áreas fora da viewport; devolva `fieldId` para que a tela revele e foque o controle correto.
- Use Toast apenas para comunicação transitória não bloqueante. Não substitua diálogo, validação de campo ou alerta persistente por Toast.
- É **proibido** introduzir modelos, variantes ou comportamentos visuais locais. Evolua primeiro o componente central, o registro aplicável e a página correspondente do guia.

## Estilo, documentação e validação

- Não use cores, bordas, raios, tamanhos de controles ou animações locais para imitar componentes existentes. Use tokens e CSS do design system somente ao evoluir o padrão compartilhado.
- Em uma associação de controles, use obrigatoriamente `UiControlGroup`; não recrie seus contornos ou fronteiras em CSS da tela. Ela mantém o contorno externo com a borda padrão de botão, preserva fundo, foco e estado dos componentes originais e remove a borda interna somente na fronteira botão–botão. Toda fronteira que envolva campo, combo ou outro componente mantém a divisória. Alternadores usam exclusivamente o estado visual padrão de `aria-pressed`, sem pills, sublinhados ou marcadores locais. O grupo usa `role="group"`; mantenha a navegação natural por Tab, salvo evolução explícita para uma toolbar com navegação por setas.
- Ao mudar um padrão, atualize a página viva em `/dev` na mesma alteração. A documentação Markdown deve apontar ao guia para regras visuais, evitando conteúdo duplicado.
- Revise o diff procurando por botões HTML, comandos canônicos definidos localmente, imagens de ícone subdimensionadas e diálogos criados fora do serviço central. Trate cada ocorrência nova como falha de conformidade, salvo uma exceção registrada no guia.
- Execute testes de interface afetados, `npm run type-check` e `npm run build`. Para botões, inclua obrigatoriamente `tests/js/design-system/UIRinoButton.spec.ts` e `buttonConformance.spec.ts`: esta barreira impede componentes aposentados, HTML que imite as classes de botão e slots nos exemplos do guia. Não remova ou enfraqueça a barreira para acomodar uma tela. Registre qualquer validação que não possa ser executada.

Se o pedido exigir um padrão novo ou houver conflito com o guia, pare antes de criar uma solução local e peça a decisão de design necessária.
