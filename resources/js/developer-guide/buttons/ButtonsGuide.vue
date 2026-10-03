<script setup lang="ts">
import { ref } from 'vue';
import UIRinoButton from '../../design-system/UIRinoButton.vue';
import UiControlGroup from '../../design-system/UiControlGroup.vue';
import { rinoButtonCommands } from '../../design-system/rinoButtonCommands';
import { createRinoToggleGroup } from '../../design-system/rinoToggleGroup';

const commands = Object.entries(rinoButtonCommands).map(([command, definition]) => ({ command: command as keyof typeof rinoButtonCommands, ...definition }));
const keepSelection = ref(false);
const showSelected = ref(false);
const toggleGroup = createRinoToggleGroup();
const optionalToggleGroup = createRinoToggleGroup({ required: false });
</script>

<template>
    <article class="developer-guide-article">
        <header id="buttons-overview" class="developer-guide-article__header" tabindex="-1">
            <p class="developer-guide-article__eyebrow">Controle · ação</p><h1>Botões</h1>
            <p>Esta página é a norma obrigatória de botões do Rinos One. Todos os modelos e exemplos usam UIRinoButton: um componente próprio da aplicação, não da linguagem nem do framework. Os exemplos aplicam o contrato; não são sugestões de variações locais.</p>
        </header>
        <section id="buttons-norms" class="developer-guide-section" tabindex="-1" aria-labelledby="buttons-norms-title">
            <div class="developer-guide-section__heading"><h2 id="buttons-norms-title">Normas obrigatórias</h2></div>
            <aside class="developer-guide-note developer-guide-note--mandatory"><strong>Obrigatório.</strong> Use exclusivamente <code>UIRinoButton</code> para ações por texto, ícone ou ambos. Comandos conhecidos usam <code>command</code> do registro único <code>rinoButtonCommands</code>. Tradução, ícone, variante, estados e acessibilidade são centralizados.</aside>
            <aside class="developer-guide-note developer-guide-note--prohibited"><strong>Proibido.</strong> Não recrie botões por HTML, CSS ou componentes paralelos. Não renomeie comandos transversais com sinônimos nem use slots para remontar ícone e texto. Não há uma classe separada de botão de listagem.</aside>
            <aside class="developer-guide-note developer-guide-note--exception"><strong>Exceção.</strong> Configurações explícitas podem sobrepor o preset para um contexto específico. A sobreposição deve conservar o sentido da ação e seguir estas normas; ela não autoriza CSS local. Novos comandos transversais evoluem o registro e este guia antes da adoção.</aside>
        </section>
        <section id="buttons-centralizer" class="developer-guide-section" tabindex="-1" aria-labelledby="buttons-centralizer-title">
            <div class="developer-guide-section__heading"><h2 id="buttons-centralizer-title">Centralizador de comandos</h2><p>UIRinoButton renderiza diretamente o botão HTML. Não delega a componentes diferentes conforme o local de uso. O formato é inferido: sem label, compacto; sem icon, somente texto; com ambos, ícone e texto.</p></div>
            <div class="developer-guide-showcase__examples"><UIRinoButton command="insert" /><UIRinoButton command="save" /><UIRinoButton command="delete" /><UIRinoButton command="search" /></div>
            <p>A resolução segue: propriedade explícita → preset de command → default. Omitir uma propriedade herda; <code>:label="null"</code> ou <code>:icon="null"</code> remove o valor herdado; <code>:toggle="false"</code> desliga a alternância herdada. toggleGroup sempre força toggle, inclusive quando toggle é false.</p>
            <dl class="developer-guide-definition-list">
                <div><dt><code>command [null]</code></dt><dd>Identificador do preset de variante, ícone, chave de tradução, nome acessível e alternância. Comando desconhecido gera erro.</dd></div>
                <div><dt><code>variant [secondary]</code></dt><dd>primary, secondary ou destructive. Uma configuração explícita sobrepõe o preset.</dd></div>
                <div><dt><code>label [null]</code></dt><dd>Chave i18n do texto visível, nunca texto já traduzido. labelParams informa parâmetros de tradução.</dd></div>
                <div><dt><code>icon [null]</code></dt><dd>Nome do ativo aprovado, sem caminho ou tamanho. O componente escolhe resolução adequada, preservando a dimensão dos tokens.</dd></div>
                <div><dt><code>accessibleLabel [null]</code></dt><dd>Chave i18n para nome acessível. Obrigatória para ícone sem texto, salvo quando já fornecida pelo command. Com texto, o próprio texto identifica a ação.</dd></div>
                <div><dt><code>toggle [false]</code></dt><dd>Guarda selecionado/desselecionado e gera aria-pressed automaticamente.</dd></div>
                <div><dt><code>toggleGroup [null]</code></dt><dd>Referência criada por createRinoToggleGroup. Por padrão, exige um selecionado: ativar outro troca a seleção e clicar no ativo não o desliga. required: false permite deixar o grupo vazio.</dd></div>
            </dl>
            <pre class="developer-guide-code"><code>import UIRinoButton from '../../design-system/UIRinoButton.vue';

&lt;UIRinoButton command="insert" @click="openCreate" /&gt;
&lt;UIRinoButton command="save" :label="null" /&gt;
&lt;UIRinoButton command="showSelected" label="rinoButtons.commands.showSelected" /&gt;
&lt;UIRinoButton label="access.profile.changeImage" /&gt;
&lt;UIRinoButton icon="theme" accessible-label="access.presentation.visualPreferences" /&gt;</code></pre>
            <aside class="developer-guide-note"><strong>Contrato inválido.</strong> Sem label e icon, o componente gera erro claro. Um ícone sem nome acessível também gera erro. Não há fallback silencioso para comando inexistente.</aside>
        </section>
        <section id="buttons-variants" class="developer-guide-section" tabindex="-1" aria-labelledby="buttons-variants-title">
            <div class="developer-guide-section__heading"><h2 id="buttons-variants-title">Variantes</h2><p>A variante comunica a consequência do fluxo, não uma preferência de cor. A mesma regra vale para botões compactos e com texto.</p></div>
            <div class="developer-guide-showcase__examples"><UIRinoButton command="save" /><UIRinoButton command="insert" /><UIRinoButton command="delete" /></div>
            <dl class="developer-guide-definition-list">
                <div><dt>primary</dt><dd>Conclui ou confirma a ação principal, como Salvar. Sem borda visível.</dd></div>
                <div><dt>secondary</dt><dd>Abre, prepara ou consulta; também cancela sem persistir. É o default sem command. Usa a borda padrão dos controles.</dd></div>
                <div><dt>destructive</dt><dd>Confirma uma remoção ou descarte, como Excluir. Sem borda visível.</dd></div>
            </dl>
            <p>Cancelar usa command="cancel", com ícone e secondary herdados. Não é um tipo de botão nem uma variante.</p>
        </section>
        <section id="buttons-composition" class="developer-guide-section" tabindex="-1" aria-labelledby="buttons-composition-title">
            <div class="developer-guide-section__heading"><h2 id="buttons-composition-title">Composição</h2></div>
            <div class="developer-guide-showcase__examples"><UIRinoButton command="confirm" /><UIRinoButton command="confirm" :icon="null" /><UIRinoButton command="confirm" :label="null" /><UIRinoButton icon="theme" accessible-label="access.presentation.visualPreferences" /></div>
            <p>As três composições acima são produzidas pelo mesmo componente. Para customização específica, forneça chaves i18n e o identificador de ícone; não passe imagens ou texto em slots. Ícones são decorativos para leitores de tela.</p>
            <pre class="developer-guide-code"><code>&lt;UIRinoButton command="confirm" /&gt;
&lt;UIRinoButton command="confirm" :icon="null" /&gt;
&lt;UIRinoButton command="confirm" :label="null" /&gt;</code></pre>
        </section>
        <section id="buttons-states" class="developer-guide-section" tabindex="-1" aria-labelledby="buttons-states-title">
            <div class="developer-guide-section__heading"><h2 id="buttons-states-title">Estados e comportamento</h2></div>
            <div class="developer-guide-showcase__examples"><UIRinoButton command="insert" disabled /><UIRinoButton command="save" loading /></div>
            <ul class="developer-guide-rule-list">
                <li>disabled e loading têm default false; ambos impedem clique e mudança de seleção. loading também expõe aria-busy.</li>
                <li>type tem default button; use submit para enviar formulários, ou reset quando esse for o comportamento necessário.</li>
                <li>@click entrega o MouseEvent original. A referência do componente expõe focus() para foco inicial e restauração.</li>
                <li>Não simule indisponibilidade com cor ou opacidade local; não reduza dimensões ou alvo de toque.</li>
            </ul>
            <pre class="developer-guide-code"><code>&lt;UIRinoButton command="save" type="submit" :loading="saving" /&gt;</code></pre>
        </section>
        <section id="buttons-toggle" class="developer-guide-section" tabindex="-1" aria-labelledby="buttons-toggle-title">
            <div class="developer-guide-section__heading"><h2 id="buttons-toggle-title">Botões de alternância</h2><p>Toggle buttons representam modos persistentes ligados/desligados. A aparência padrão usa aria-pressed, sem pills, marcas inferiores ou estilos locais.</p></div>
            <div class="developer-guide-showcase developer-guide-showcase--split developer-guide-toggle-demo">
                <div><h3>Compacto</h3><div class="developer-guide-showcase__examples"><UIRinoButton command="keepSelection" v-model:selected="keepSelection" /></div><p role="status">Manter seleção: {{ keepSelection ? 'ligado' : 'desligado' }}.</p></div>
                <div><h3>Ícone e texto</h3><div class="developer-guide-showcase__examples"><UIRinoButton command="showSelected" label="rinoButtons.commands.showSelected" v-model:selected="showSelected" /></div><p role="status">Exibir selecionados: {{ showSelected ? 'ligado' : 'desligado' }}.</p></div>
            </div>
            <p>Sem selected, o componente guarda internamente um estado inicialmente false. Com v-model:selected, a tela controla o estado e recebe update:selected. Não escreva aria-pressed nem inverta manualmente o estado no @click. Um selected somente de leitura pode representar estado confirmado externamente, sem aceitar a mudança solicitada pelo botão.</p>
            <h3>Seleção exclusiva por instância</h3>
            <aside class="developer-guide-note developer-guide-note--mandatory"><strong>Obrigatório.</strong> O grupo padrão mantém exatamente um selecionado enquanto houver membros disponíveis. Clicar novamente no ativo não o desseleciona; para trocar, clique em outro membro. Esta regra pertence ao grupo, não a handlers da tela.</aside>
            <UiControlGroup label="Demonstração de seleção exclusiva"><UIRinoButton command="keepSelection" :toggle-group="toggleGroup" /><UIRinoButton command="showSelected" :toggle-group="toggleGroup" /></UiControlGroup>
            <pre class="developer-guide-code"><code>const group = createRinoToggleGroup();
&lt;UIRinoButton command="keepSelection" :toggle-group="group" /&gt;
&lt;UIRinoButton command="showSelected" :toggle-group="group" /&gt;</code></pre>
            <p>Se a tela não informar seleção inicial, o grupo escolhe o primeiro membro disponível após montar. Com v-model:selected, aceite update:selected também para esta inicialização. Se o selecionado sair do grupo ou a tela limpar a seleção externamente, o grupo seleciona o primeiro disponível. Um botão já selecionado pode permanecer ativo enquanto disabled/loading; a seleção não muda apenas por indisponibilidade temporária. Se não houver nenhum selecionado nem membro disponível, a inicialização aguarda um membro disponível.</p>
            <h3>Grupo com seleção opcional</h3>
            <UiControlGroup label="Demonstração de seleção opcional"><UIRinoButton command="keepSelection" :toggle-group="optionalToggleGroup" /><UIRinoButton command="showSelected" :toggle-group="optionalToggleGroup" /></UiControlGroup>
            <pre class="developer-guide-code"><code>const optionalGroup = createRinoToggleGroup({ required: false });</code></pre>
            <p>Use required: false somente quando o contexto permitir nenhum modo ativo: nesse grupo, clicar no selecionado o desliga. Toggles fora de um grupo continuam independentes e podem ser desligados normalmente.</p>
            <p>Compartilhe a mesma referência somente entre membros do mesmo conjunto. Crie uma referência diferente para cada janela ou formulário; não use strings ou grupos globais. Os grupos não implementam navegação de radio group: preservam Tab e aria-pressed. Desabilitados não mudam por clique, mas podem ser desselecionados quando outro membro é ativado. Para abrir/fechar conteúdo, use aria-expanded, não toggle.</p>
        </section>
        <section id="buttons-accessibility" class="developer-guide-section" tabindex="-1" aria-labelledby="buttons-accessibility-title">
            <div class="developer-guide-section__heading"><h2 id="buttons-accessibility-title">Acessibilidade e regras de adoção</h2></div>
            <p>O nome acessível e o tooltip dos botões sem texto são traduzidos centralmente. Ícones usam alt vazio e aria-hidden; foco visível e dimensões seguem os tokens. Não use cor ou posição como único indicador de consequência. Elementos especializados como tabs, árvores e links não são exemplos de botão de ação e conservam seu contrato semântico específico.</p>
        </section>
        <section id="buttons-screen-commands" class="developer-guide-section" tabindex="-1" aria-labelledby="buttons-screen-commands-title">
            <div class="developer-guide-section__heading"><h2 id="buttons-screen-commands-title">Comandos padrão das telas</h2><p>Registro único de botões padronizados, em qualquer contexto. O Modelo é renderizado diretamente por UIRinoButton com o command indicado; ícone e rótulo não são repetidos em outras colunas.</p></div>
            <p>Nas colunas de configuração, <strong>—</strong> significa o default de UIRinoButton: variant secondary e toggle false. São mostradas apenas diferenças do preset. As demais opções não são redefinidas por estes comandos; seu contrato está no centralizador acima.</p>
            <div class="developer-guide-table-wrap" tabindex="0">
                <table class="developer-guide-table">
                    <caption>Registro dos comandos padronizados</caption>
                    <thead><tr><th scope="col">Modelo</th><th scope="col">command</th><th scope="col">variant</th><th scope="col">toggle</th><th scope="col">Escopo, sentido e restrições</th></tr></thead>
                    <tbody><tr v-for="command in commands" :key="command.command"><td><UIRinoButton :command="command.command" /></td><td><code>{{ command.command }}</code></td><td><code v-if="command.variant !== 'secondary'">{{ command.variant }}</code><span v-else>—</span></td><td><code v-if="command.toggle">true</code><span v-else>—</span></td><td>{{ command.scope }}</td></tr></tbody>
                </table>
            </div>
        </section>
    </article>
</template>
