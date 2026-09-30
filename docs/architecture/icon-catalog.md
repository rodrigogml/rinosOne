# Catálogo de ícones

Este catálogo estabelece a linguagem visual e a semântica dos ícones de Rinos One. Um ícone comunica uma ação ou domínio estável; não deve ser reutilizado apenas porque sua forma parece conveniente em outro contexto.

## Criação técnica e responsividade

Todo ícone vetorial deve obedecer a estas regras:

- SVG com `width="48"`, `height="48"` e `viewBox="0 0 48 48"`.
- Usar `currentColor` para traços e preenchimentos. A cor pertence ao contexto do componente, não ao desenho.
- Usar `fill="none"`, `stroke-linecap="round"`, `stroke-linejoin="round"` e `stroke-width="2.25"`, salvo exceção aprovada no próprio catálogo. O ícone `performance` usa traço de `4` nos indicadores de barra.
- Não codificar a dimensão de exibição no SVG. Os atributos de 48 px representam a área de desenho; CSS define o tamanho exibido.
- No desktop, os tokens de ícone exibem 32 px na escala padrão e acompanham a preferência de tamanho de componentes. No mobile, os tokens usam 16, 20 ou 24 px conforme o contexto para preservar área útil e toque.
- Um SVG dentro de `IconButton` deve preencher o invólucro `.ui-icon-button__icon`; ele nunca deve transbordar o botão.

> [!IMPORTANT]
> O `viewBox` de 48 px é obrigatório mesmo quando o ícone aparece visualmente menor. Não reduza paths para 24 px e não introduza um segundo padrão de viewport.

## Ativos raster aprovados

Ícones raster aprovados pertencem ao catálogo versionado em `public/assets/icons/`. Esse é o único local para ativos distribuídos com a aplicação: a interface os referencia por `/assets/icons/<arquivo>`, e o core, relatórios e e-mails podem obter o mesmo arquivo com `public_path('assets/icons/<arquivo>')`.

O diretório `etc/Icon Treatment/output/` é uma área local e descartável de revisão humana; por isso é ignorado pelo Git. Após a aprovação, execute `etc/Icon Treatment/import_approved_icons.bat`. O importador considera somente arquivos no padrão `<nome>_<tamanho>.png`, valida que cada nome possui exatamente as variantes PNG quadradas `_512`, `_48`, `_32` e `_24`, e então as **move** para o catálogo, imprimindo uma linha por variante. Cada conjunto é processado independentemente: um conjunto incompleto ou já publicado é informado sem impedir os demais. Assim, um item publicado não volta a ser encontrado numa execução posterior. Ele não sobrescreve um ativo publicado sem `--replace` explícito.

> [!NOTE]
> O disco `storage/app/public` é destinado a conteúdo operacional e é ignorado pelo Git. Não o utilize para ícones internos versionados.

### Preload ocioso de ícones de interface

Após a primeira renderização autenticada, a aplicação pré-carrega no tempo ocioso do navegador as variantes de interface `_24`, `_32` e `_48` de todos os ícones raster registrados em `resources/js/design-system/rasterIconAssets.ts`. O preload usa `requestIdleCallback` quando disponível e um atraso curto como fallback; assim, não concorre com o conteúdo inicial da tela. A variante `_512` não é pré-carregada porque não é usada na interface corrente.

Esse registro é a única manutenção necessária para um novo ícone PNG. Menus, janelas, taskbar e demais componentes que usam a chave registrada passam a compartilhar a mesma resolução de URL e o cache já aquecido, sem listas específicas por tela.

| Ativo raster | Semântica aprovada | Uso atual |
| --- | --- | --- |
| `logout` | Encerrar a sessão autenticada | Ação Sair no menu pessoal |
| `theme` | Abrir preferências visuais locais | Botão de aparência no menu pessoal |
| `rinoUser-tweek` | Configurações da conta do usuário | Ação Configurações do usuário no menu pessoal |
| `taskbar2` | Alternar janelas abertas | Botão de alternância de janelas na topbar responsiva |
| `maintenance` | Central de Manutenções da plataforma | Menu, cabeçalho da janela e taskbar da Central de Manutenções |

## Acessibilidade e cor

Ícones decorativos usam `aria-hidden="true"`. Um botão composto somente por ícone deve possuir `aria-label` com o verbo da ação, como `Fechar janela` ou `Sair da conta`. O SVG não substitui o rótulo acessível.

Estados de interação — normal, hover, foco, indisponível e destrutivo — são definidos pelo componente e pelos tokens de cor. Não fixe branco, preto ou cores de tema no SVG.

## Uso semântico obrigatório

| Chave | Representa | Contextos permitidos | Não usar para |
| --- | --- | --- | --- |
| `close` | Fechar, dispensar ou encerrar uma superfície sem confirmar ação de negócio | Janela, painel, alerta e drawer | Cancelar uma operação pendente ou voltar de navegação |
| `logout` | Encerrar sessão autenticada | Menu de perfil e controles de sessão | Fechar painel, remover registro ou revogar outro usuário |
| `palette` | Preferências visuais locais | Tema, densidade e aparência | Cor de produto, edição gráfica ou configurações gerais |
| `navigation-list` | Acesso à navegação por categorias | Trilho de navegação | Lista de dados, filtros ou opções contextuais |
| `chevron-left` | Recolher ou mover para a direção anterior | Recolher trilho; ação explícita de retorno | Fechar, cancelar ou apagar |
| `chevron-right` | Expandir ou mover para a direção seguinte | Expandir trilho; ação explícita de avanço | Confirmar, salvar ou abrir menu genérico |
| `task-switcher` | Alternar entre janelas abertas | Controle de tarefas no mobile | Abrir menu, duplicar ou trocar tenant |

As ações abaixo são reservadas para uma próxima inclusão visual. Até que possuam SVG aprovado e estejam no componente central, não devem ser improvisadas com ícones existentes.

| Ação reservada | Semântica futura | Regra de padronização |
| --- | --- | --- |
| Confirmar | Concluir uma escolha ou operação | Um único ícone de confirmação em toda a plataforma; texto da ação continua obrigatório em ações críticas |
| Cancelar | Abandonar uma operação em andamento | Diferente de `close`; não usar `close` quando houver alteração descartável ou confirmação explícita |
| Voltar | Retornar à superfície ou etapa anterior | Diferente de recolher navegação e de cancelar |
| Buscar | Iniciar ou focar consulta textual | Não usar como filtro |
| Filtrar | Restringir uma lista por critérios | Não usar para ordenação ou busca textual |

## Ícones de domínio

| Chave | Representa | Contextos permitidos |
| --- | --- | --- |
| `drive` | Workspace de arquivos privado | Rinos Drive Pessoal, Rinos Drive Work e seus destinos de navegação |
| `overview` | Visão consolidada de áreas ou indicadores | Categoria Visão geral, dashboard de alto nível |
| `documents` | Conjunto de documentos organizado | Categoria Documentos, repositório documental |
| `attachments` | Arquivo vinculado a um registro | Anexos, arquivos e evidências relacionadas |
| `cashflow` | Fluxo, saldo ou movimentação financeira | Financeiro, fluxo de caixa e pagamentos |
| `contacts` | Pessoas ou organizações relacionadas | Agenda, cadastro de contatos e relacionamentos |
| `opportunities` | Oportunidade, meta ou objetivo comercial | Pipeline comercial e oportunidades |
| `items` | Item, produto ou unidade de catálogo | Catálogo, estoque e itens comercializáveis |
| `invoices` | Fatura ou documento de cobrança | Cobrança, contas a receber e faturas |
| `ledger` | Lançamentos ou registros contábeis | Livro razão e consulta contábil |
| `performance` | Métrica comparativa ou evolução quantitativa | Indicadores e desempenho |
| `settings` | Configuração de sistema ou de área | Preferências, manutenção e administração |
| `fallback` | Recurso ainda sem identidade semântica | Somente contingência técnica durante desenvolvimento |

> [!CAUTION]
> `fallback` não pode ser escolhido como ícone de uma funcionalidade entregue. Antes de expor uma nova área ao usuário, crie e aprove um ícone com chave semântica própria.

## Componentes e ativos relacionados

- `WorkspaceSurfaceIcon` centraliza os ícones de domínio para menu, taskbar e título da janela.
- Ícones de controles transversais permanecem junto do componente que representa sua interação, mas seguem este catálogo.
- A marca horizontal e o símbolo da marca são ativos PNG, não ícones de interface.
- Idiomas usam emojis de bandeira no desktop e no mobile: 🇧🇷, 🇺🇸, 🇪🇸 e 🇫🇷. Aceitamos a variação visual do sistema operacional para manter cor e evitar uma segunda biblioteca de SVGs.
- Os caracteres `●` para pendência e `▣` para dispositivo são marcadores legados; devem ser substituídos por ícones semânticos aprovados quando a respectiva superfície for revisada.

## Processo de evolução

1. Definir nome, significado único e contextos permitidos antes de desenhar.
2. Submeter o SVG de 48 px para aprovação visual nos temas claro e escuro, desktop e mobile.
3. Registrar a chave e as restrições neste catálogo antes de aplicar o ícone em uma tela entregue.
4. Centralizar o ícone quando ele representar domínio ou for reutilizado entre superfícies.
5. Executar testes de componente, type-check, build e validação visual após a aplicação.

### Composição de ícones raster

Quando a semântica exigir dois ícones já aprovados — por exemplo, usuário e configurações — use `etc/Icon Treatment/compose_icons.bat` arrastando exatamente duas imagens. O script pergunta qual é o ícone principal e gera o conjunto em `output/`: o principal ocupa 85% da área, no canto superior esquerdo e na camada inferior; o secundário ocupa 50%, no canto inferior direito e na camada superior. A sobreposição de 35% é intencional.
