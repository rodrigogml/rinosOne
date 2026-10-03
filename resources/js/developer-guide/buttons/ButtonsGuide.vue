<script setup lang="ts">
import { ref } from 'vue';
import IconButton from '../../design-system/IconButton.vue';
import UiButton from '../../design-system/UiButton.vue';

const iconPath = (name: string): string => `/assets/icons/${name}_24.png`;

type TextButtonVariant = 'primary' | 'secondary' | 'destructive';

const screenCommands: ReadonlyArray<{
    label: string;
    icon: string;
    variant: TextButtonVariant;
    scope: string;
    notAllowed: string;
}> = [
    { label: 'Inserir', icon: 'dataInsert', variant: 'secondary', scope: 'Abre a inclusão de um novo registro ou objeto, sem confirmar a gravação.', notAllowed: 'Novo, Adicionar, Criar' },
    { label: 'Duplicar', icon: 'dataDuplicate', variant: 'secondary', scope: 'Inicia um novo registro a partir de uma única seleção existente.', notAllowed: 'Copiar, Clonar' },
    { label: 'Alterar', icon: 'dataEdit', variant: 'secondary', scope: 'Abre a alteração de um único registro existente.', notAllowed: 'Editar, Modificar' },
    { label: 'Visualizar', icon: 'dataView', variant: 'secondary', scope: 'Abre a consulta de um único registro sem iniciar edição.', notAllowed: 'Ver, Consultar' },
    { label: 'Excluir', icon: 'dataDelete', variant: 'destructive', scope: 'Confirma e executa a exclusão de um registro ou objeto.', notAllowed: 'Apagar, Deletar' },
    { label: 'Salvar', icon: 'floppyDisk', variant: 'primary', scope: 'Persiste a criação ou alteração preenchida em um formulário.', notAllowed: 'Gravar, Concluir' },
    { label: 'Cancelar', icon: 'btCancel', variant: 'secondary', scope: 'Abandona o fluxo local sem confirmar ou persistir a alteração atual.', notAllowed: 'Voltar, Fechar' },
    { label: 'Confirmar', icon: 'btConfirm', variant: 'primary', scope: 'Confirma uma consequência já descrita no diálogo; exclusões mantêm o rótulo Excluir.', notAllowed: 'OK, Prosseguir' },
];

const iconOnlyCommands: ReadonlyArray<{ label: string; icon: string; scope: string }> = [
    { label: 'Pesquisar', icon: 'search', scope: 'Executa a busca preenchida no campo adjacente.' },
    { label: 'Filtros', icon: 'funnel', scope: 'Abre ou alterna os filtros da listagem.' },
    { label: 'Manter seleção', icon: 'tableLockSelection', scope: 'Alterna a preservação dos itens selecionados entre novas buscas.' },
    { label: 'Exibir selecionados', icon: 'tableShowSelected', scope: 'Alterna a lista para mostrar somente os itens selecionados.' },
    { label: 'Incluir selecionados ocultos', icon: 'tableShowHiddenSelected', scope: 'Alterna a inclusão de itens selecionados fora dos critérios atuais.' },
    { label: 'Limpar seleção', icon: 'tableCleanSelection', scope: 'Remove a seleção mantida na listagem.' },
    { label: 'Colunas', icon: 'tableColumns', scope: 'Abre a configuração de colunas da listagem.' },
];

const keepSelection = ref(false);
const showSelected = ref(false);
</script>

<template>
    <article class="developer-guide-article">
        <header id="buttons-overview" class="developer-guide-article__header" tabindex="-1">
            <p class="developer-guide-article__eyebrow">Controle · ação</p>
            <h1>Botões</h1>
            <p>
                Este é o catálogo normativo de botões do Rinos One. Toda ação acionável deve reutilizar
                <code>UiButton</code> ou <code>IconButton</code>; não são permitidas variações locais de cor,
                borda, raio, tamanho ou comportamento.
            </p>
        </header>

        <section class="developer-guide-section" aria-labelledby="buttons-variants">
            <div class="developer-guide-section__heading">
                <h2 id="buttons-variants" tabindex="-1">Variantes</h2>
                <p>Há três variantes de ação com texto. A escolha segue o momento e a consequência do comando no fluxo, nunca a preferência visual da tela.</p>
            </div>
            <div class="developer-guide-showcase">
                <div class="developer-guide-showcase__examples">
                    <UiButton>Salvar alterações</UiButton>
                    <UiButton variant="secondary">Cancelar</UiButton>
                    <UiButton variant="destructive">Excluir registro</UiButton>
                </div>
                <dl class="developer-guide-definition-list">
                    <div><dt><code>primary</code></dt><dd>Conclui e persiste a ação principal da tela ou da confirmação atual, como Salvar.</dd></div>
                    <div><dt><code>secondary</code></dt><dd>Abre, consulta ou prepara uma ação; também cancela sem persistir. Inserir, Alterar, Visualizar e Cancelar usam esta variante.</dd></div>
                    <div><dt><code>destructive</code></dt><dd>Confirma exclusão, descarte ou outra remoção. Use o verbo específico da consequência, como Excluir.</dd></div>
                </dl>
            </div>
            <aside class="developer-guide-note" role="note">
                <strong>Default e nome canônico.</strong> <code>UiButton</code> sem <code>variant</code> é <code>primary</code>. <code>Cancelar</code> é sempre <code>secondary</code>. A variante destrutiva é <code>destructive</code>; não crie nem use <code>danger</code>, mesmo que “perigo” descreva a semântica do token de cor.
            </aside>
            <pre class="developer-guide-code"><code>&lt;UiButton&gt;Salvar alterações&lt;/UiButton&gt;
&lt;UiButton variant=&quot;secondary&quot;&gt;Cancelar&lt;/UiButton&gt;
&lt;UiButton variant=&quot;destructive&quot;&gt;Excluir registro&lt;/UiButton&gt;</code></pre>
        </section>

        <section id="buttons-composition" class="developer-guide-section" aria-labelledby="buttons-composition-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="buttons-composition-title">Composição</h2>
                <p>O texto nomeia a ação. Um ícone pode reforçar o significado, mas não o substitui quando o comando cria, altera, consulta ou exclui dados.</p>
            </div>
            <div class="developer-guide-showcase developer-guide-showcase--split">
                <div>
                    <h3>Texto e ícone</h3>
                    <div class="developer-guide-showcase__examples">
                        <UiButton><img class="developer-guide__button-icon" :src="iconPath('floppyDisk')" alt="" aria-hidden="true">Salvar</UiButton>
                        <UiButton variant="secondary"><img class="developer-guide__button-icon" :src="iconPath('dataInsert')" alt="" aria-hidden="true">Inserir</UiButton>
                    </div>
                    <pre class="developer-guide-code"><code>&lt;UiButton&gt;
  &lt;img src=&quot;/assets/icons/floppyDisk_24.png&quot; alt=&quot;&quot; aria-hidden=&quot;true&quot;&gt;
  Salvar
&lt;/UiButton&gt;</code></pre>
                </div>
                <div>
                    <h3>Somente ícone</h3>
                    <div class="developer-guide-showcase__examples">
                        <IconButton label="Abrir preferências visuais"><img :src="iconPath('theme')" alt="" aria-hidden="true"></IconButton>
                        <IconButton label="Sair da conta"><img :src="iconPath('logout')" alt="" aria-hidden="true"></IconButton>
                    </div>
                    <pre class="developer-guide-code"><code>&lt;IconButton label=&quot;Abrir preferências visuais&quot;&gt;
  &lt;img src=&quot;/assets/icons/theme_24.png&quot; alt=&quot;&quot; aria-hidden=&quot;true&quot;&gt;
&lt;/IconButton&gt;</code></pre>
                </div>
            </div>
        </section>

        <section id="buttons-states" class="developer-guide-section" aria-labelledby="buttons-states-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="buttons-states-title">Estados e comportamento</h2>
                <p>O componente mantém altura, foco, contraste e escala derivados dos tokens do sistema em todos os temas e preferências de densidade.</p>
            </div>
            <div class="developer-guide-showcase__examples">
                <UiButton disabled>Indisponível</UiButton>
                <UiButton loading>Salvando alterações</UiButton>
                <UiButton variant="secondary" disabled>Cancelar</UiButton>
            </div>
            <ul class="developer-guide-rule-list">
                <li><code>loading</code> desabilita o comando e expõe <code>aria-busy</code>; mantenha no texto o que está sendo processado.</li>
                <li><code>disabled</code> é reservado a uma condição objetiva que a pessoa ainda consegue compreender ou resolver.</li>
                <li>Um botão de formulário usa <code>type=&quot;submit&quot;</code>. Os demais mantêm <code>type=&quot;button&quot;</code>, o padrão do componente.</li>
                <li>A ação principal bloqueia repetição durante o processamento; a alternativa segura permanece disponível apenas quando não conflitar com a operação.</li>
            </ul>
            <pre class="developer-guide-code"><code>&lt;UiButton type=&quot;submit&quot; :loading=&quot;saving&quot;&gt;
  &#123;&#123; saving ? 'Salvando alterações' : 'Salvar alterações' &#125;&#125;
&lt;/UiButton&gt;</code></pre>
        </section>

        <section id="buttons-toggle" class="developer-guide-section" aria-labelledby="buttons-toggle-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="buttons-toggle-title">Botões de alternância</h2>
                <p>Também chamados de <em>toggle buttons</em>, mantêm e tornam visível um estado ligado ou desligado. São usados para ativar modos persistentes da própria tela, não para executar uma ação pontual.</p>
            </div>
            <div class="developer-guide-showcase developer-guide-showcase--split developer-guide-toggle-demo">
                <div>
                    <h3>Compacto</h3>
                    <div class="developer-guide-showcase__examples">
                        <IconButton label="Manter seleção" :aria-pressed="keepSelection" @click="keepSelection = !keepSelection"><img :src="iconPath('tableLockSelection')" alt="" aria-hidden="true"></IconButton>
                    </div>
                    <p class="developer-guide-toggle-demo__state" role="status">Manter seleção: {{ keepSelection ? 'ligado' : 'desligado' }}.</p>
                    <pre class="developer-guide-code"><code>&lt;IconButton
  label=&quot;Manter seleção&quot;
  :aria-pressed=&quot;keepSelection&quot;
  @click=&quot;keepSelection = !keepSelection&quot;
&gt;…&lt;/IconButton&gt;</code></pre>
                </div>
                <div>
                    <h3>Ícone e texto</h3>
                    <div class="developer-guide-showcase__examples">
                        <UiButton variant="secondary" :aria-pressed="showSelected" @click="showSelected = !showSelected"><img class="developer-guide__button-icon" :src="iconPath('tableShowSelected')" alt="" aria-hidden="true">Exibir selecionados</UiButton>
                    </div>
                    <p class="developer-guide-toggle-demo__state" role="status">Exibir selecionados: {{ showSelected ? 'ligado' : 'desligado' }}.</p>
                    <pre class="developer-guide-code"><code>&lt;UiButton
  variant=&quot;secondary&quot;
  :aria-pressed=&quot;showSelected&quot;
  @click=&quot;showSelected = !showSelected&quot;
&gt;
  &lt;img src=&quot;/assets/icons/tableShowSelected_24.png&quot; alt=&quot;&quot; aria-hidden=&quot;true&quot;&gt;
  Exibir selecionados
&lt;/UiButton&gt;</code></pre>
                </div>
            </div>
            <ul class="developer-guide-rule-list">
                <li>O estado é obrigatório e é exposto com <code>aria-pressed</code> recebendo um booleano reativo. A aparência de pressionado é consequência desse atributo; não a simule somente com uma classe ou cor local.</li>
                <li>O nome da função permanece estável nos dois estados, como “Manter seleção”; <code>aria-pressed</code> informa se ela está ligada ou desligada. Botões compactos sempre recebem <code>label</code>.</li>
                <li>Use para modos binários persistentes, como manter seleção ou exibir somente os selecionados. Em uma barra de controles, alternadores compactos podem ser colocados lado a lado.</li>
                <li>Não use <code>aria-pressed</code> para abrir ou fechar conteúdo: nesse caso, o padrão é <code>aria-expanded</code>. Uma ação sem estado persistente continua sendo um botão comum.</li>
            </ul>
        </section>

        <section id="buttons-accessibility" class="developer-guide-section" aria-labelledby="buttons-accessibility-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="buttons-accessibility-title">Acessibilidade e regras de adoção</h2>
                <p>O componente é a fonte de verdade para foco, alvo de toque, cores e estados. O contexto da ação continua sendo responsabilidade de quem o compõe.</p>
            </div>
            <ul class="developer-guide-rule-list">
                <li>Todo botão somente com ícone usa <code>IconButton</code> e recebe <code>label</code> com verbo e objeto da ação.</li>
                <li>Ícones decorativos usam <code>alt=&quot;&quot;</code> e <code>aria-hidden=&quot;true&quot;</code>; a imagem não substitui um nome acessível.</li>
                <li>Não use cor, ícone ou posição como único sinal de consequência, seleção, erro ou indisponibilidade.</li>
                <li>Não escreva <code>&lt;button class=&quot;ui-button…&quot;&gt;</code> em novas telas. Use os componentes para preservar o contrato.</li>
                <li>Novos modelos de botão exigem atualização deste guia e do componente compartilhado antes de qualquer uso na aplicação.</li>
            </ul>
        </section>

        <section id="buttons-screen-commands" class="developer-guide-section" aria-labelledby="buttons-screen-commands-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="buttons-screen-commands-title">Comandos padrão das telas</h2>
                <p>Estes são os únicos nomes e ícones para comandos transversais. O rótulo visível e o ícone formam uma unidade: não substitua um pelo outro, nem crie sinônimos locais.</p>
            </div>
            <div class="developer-guide-table-wrap" tabindex="0">
                <table class="developer-guide-table">
                    <caption>Comandos textuais de registros, formulários e confirmações</caption>
                    <thead><tr><th scope="col">Modelo</th><th scope="col">Ícone obrigatório</th><th scope="col">Variante</th><th scope="col">Escopo e sentido</th><th scope="col">Não usar</th></tr></thead>
                    <tbody>
                        <tr v-for="command in screenCommands" :key="command.label">
                            <td><UiButton :variant="command.variant"><img class="developer-guide__button-icon" :src="iconPath(command.icon)" alt="" aria-hidden="true">{{ command.label }}</UiButton></td>
                            <td><code>{{ command.icon }}_24.png</code></td>
                            <td><code>{{ command.variant }}</code></td>
                            <td>{{ command.scope }}</td>
                            <td>{{ command.notAllowed }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="developer-guide-table-wrap" tabindex="0">
                <table class="developer-guide-table">
                    <caption>Controles compactos de listagem</caption>
                    <thead><tr><th scope="col">Modelo</th><th scope="col">Ícone obrigatório</th><th scope="col">Rótulo obrigatório</th><th scope="col">Escopo e sentido</th></tr></thead>
                    <tbody>
                        <tr v-for="command in iconOnlyCommands" :key="command.label">
                            <td><IconButton :label="command.label"><img :src="iconPath(command.icon)" alt="" aria-hidden="true"></IconButton></td>
                            <td><code>{{ command.icon }}_24.png</code></td>
                            <td><code>label=&quot;{{ command.label }}&quot;</code></td>
                            <td>{{ command.scope }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <aside class="developer-guide-note" role="note">
                <strong>Regra de seleção.</strong> Comandos de uma listagem que operam sobre um registro exigem exatamente uma seleção; o clique deve explicar como corrigir ausência ou excesso de seleção. A disponibilidade por permissão continua sendo controlada pelo servidor.
            </aside>
        </section>
    </article>
</template>
