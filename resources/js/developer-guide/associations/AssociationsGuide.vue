<script setup lang="ts">
import { ref } from 'vue';
import UIRinoButton from '../../design-system/UIRinoButton.vue';
import UiControlGroup from '../../design-system/UiControlGroup.vue';

const search = ref('');
const filtersOpen = ref(false);
const keepSelection = ref(false);
const showSelected = ref(false);
const includeHidden = ref(false);
const selectedField = ref('Nome');
</script>

<template>
    <article class="developer-guide-article">
        <header id="associations-overview" class="developer-guide-article__header" tabindex="-1">
            <p class="developer-guide-article__eyebrow">Composição · controles</p>
            <h1>Associações</h1>
            <p>Uma associação organiza controles que cooperam para uma mesma tarefa imediata, sem transformar controles independentes em um único botão. Cada campo, combo e comando preserva seu nome acessível, foco e comportamento próprio.</p>
        </header>

        <section id="associations-norms" class="developer-guide-section" aria-labelledby="associations-norms-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="associations-norms-title">Contrato obrigatório</h2>
                <p>Estes critérios definem a aparência e as fronteiras de qualquer associação de controles no sistema.</p>
            </div>
            <div class="developer-guide-norms" aria-label="Regras obrigatórias para associações">
                <p class="developer-guide-note developer-guide-note--mandatory"><strong>Obrigatório.</strong> Use <code>UiControlGroup</code> para a associação. Ela usa um único contorno externo, com a mesma espessura, cor e raio dos botões secondary. Cada controle preserva o fundo, o estado e o comportamento do seu componente padrão.</p>
                <p class="developer-guide-note developer-guide-note--prohibited"><strong>Proibido.</strong> Não remova o fundo padrão, não substitua o estado pressionado e não crie marcas locais, pills, sublinhados ou pseudo-elementos para diferenciar um botão alternador.</p>
                <p class="developer-guide-note developer-guide-note--exception"><strong>Exceção única.</strong> A divisória interna é removida somente entre dois botões adjacentes. Toda fronteira que envolva campo, combo ou outro controle não botão mantém a divisória.</p>
            </div>
        </section>

        <section id="associations-field-command" class="developer-guide-section" aria-labelledby="associations-field-command-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="associations-field-command-title">Campo e comando</h2>
                <p>Use esta associação quando uma entrada textual recebe comandos diretamente relacionados ao seu conteúdo. É o padrão da busca e dos filtros da listagem de Pessoas.</p>
            </div>
            <div class="developer-guide-showcase">
                <UiControlGroup class="developer-guide-association__search-tools" label="Busca de pessoas">
                    <label class="developer-guide-association__search-field">
                        <span class="developer-guide__sr-only">Pesquisar pessoas</span>
                        <input v-model="search" type="search" placeholder="Buscar...">
                    </label>
                    <UIRinoButton command="search" />
                    <UIRinoButton command="filters" v-model:selected="filtersOpen" toggle  />
                </UiControlGroup>
                <p v-if="filtersOpen" class="developer-guide-association__state" role="status">Filtros ativos na demonstração.</p>
            </div>
            <ul class="developer-guide-rule-list">
                <li>O campo ocupa o espaço flexível; os comandos ficam no fim e possuem largura fixa de controle.</li>
                <li>O grupo compartilha contorno, altura e anel de foco do padrão de controles. Há divisor entre campo e botão; entre dois botões consecutivos, não há divisor.</li>
                <li>O botão de filtro é um alternador configurado por <code>toggle</code> e <code>v-model:selected</code>; o componente gera <code>aria-pressed</code>; pesquisar executa a consulta do texto atual.</li>
            </ul>
        </section>

        <section id="associations-button-bar" class="developer-guide-section" aria-labelledby="associations-button-bar-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="associations-button-bar-title">Barras de botões</h2>
                <p>Os botões são sempre UIRinoButton, com o mesmo registro e contrato de Botões. Uma barra reúne comandos irmãos de mesmo contexto. A ordem é estável, os botões encostam visualmente e cada um continua sendo um comando independente.</p>
            </div>
            <div class="developer-guide-showcase developer-guide-showcase--split">
                <div>
                    <h3>Controles de seleção</h3>
                    <UiControlGroup class="developer-guide-association__icon-bar" label="Controles de seleção">
                        <UIRinoButton command="keepSelection" v-model:selected="keepSelection" toggle  />
                        <UIRinoButton command="showSelected" v-model:selected="showSelected" toggle  />
                        <UIRinoButton command="includeHidden" v-model:selected="includeHidden" toggle  />
                        <UIRinoButton command="clearSelection" @click="keepSelection = false; showSelected = false; includeHidden = false" />
                    </UiControlGroup>
                </div>
                <div>
                    <h3>Comandos de registro</h3>
                    <UiControlGroup class="developer-guide-association__action-bar" label="Comandos do registro">
                        <UIRinoButton command="insert" />
                        <UIRinoButton command="alter" />
                        <UIRinoButton command="view" />
                    </UiControlGroup>
                </div>
            </div>
            <ul class="developer-guide-rule-list">
                <li>A barra possui um contorno único e elimina somente as divisórias entre seus botões. Fundo, foco e estado de cada botão continuam sendo os do componente padrão.</li>
                <li>Alternadores compactos usam <code>UIRinoButton</code> com <code>v-model:selected</code> e a aparência pressionada definida em Botões; comandos pontuais não usam esse atributo nem marcadores adicionais.</li>
                <li>Em largura insuficiente, a barra permanece em uma linha e oferece rolagem horizontal; nunca reduz o alvo de toque, oculta ou quebra um botão da associação. <code>UiControlGroup</code> usa <code>role=&quot;group&quot;</code>, pois cada botão continua usando a navegação natural por Tab.</li>
                <li>Não misture, na mesma barra compacta, comandos de seleção, navegação e ações destrutivas.</li>
            </ul>
        </section>

        <section id="associations-select-command" class="developer-guide-section" aria-labelledby="associations-select-command-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="associations-select-command-title">Combo e comando</h2>
                <p>Associe um combo à ação que consome sua seleção, como escolher o campo de uma condição e inserir a condição na composição atual.</p>
            </div>
            <div class="developer-guide-showcase">
                <div class="developer-guide-association__select-command">
                    <span id="association-field-label">Campo</span>
                    <UiControlGroup class="developer-guide-association__select-command-controls" label="Inserir condição pelo campo selecionado">
                        <select v-model="selectedField" aria-label="Campo"><option>Nome</option><option>Tipo de pessoa</option><option>Situação</option></select>
                        <UIRinoButton command="insert" label="rinoButtons.context.insertCondition" />
                    </UiControlGroup>
                </div>
                <p class="developer-guide-association__state" role="status">Campo selecionado: {{ selectedField }}.</p>
            </div>
            <ul class="developer-guide-rule-list">
                <li>O rótulo do combo identifica o dado escolhido; o botão descreve o que fará com essa escolha.</li>
                <li>Entre combo e botão há divisor; somente sequências de botões usam uma superfície contínua, sem divisores internos. Esta fronteira não pode ser removida.</li>
                <li>Não use um botão como substituto de <code>select</code> quando a pessoa precisa escolher entre opções fechadas.</li>
                <li>Use <code>secondary</code> enquanto a associação prepara a condição; a confirmação final da busca ou gravação segue o padrão de Botões.</li>
            </ul>
        </section>
        <section id="associations-adaptive-toolbar" class="developer-guide-section">
            <div class="developer-guide-section__heading"><h2>Barras adaptativas de ações</h2></div>
            <p class="developer-guide-note developer-guide-note--mandatory"><strong>Obrigatório.</strong> Uma toolbar com ações independentes pode manter navegação e atualização visíveis e reunir ações secundárias no UiActionPopover em larguras restritas. Não se ocultam membros de uma associação UiControlGroup: a associação permanece inteira. O popover usa UIRinoButton, fecha ao escolher uma ação, ao pressionar Escape ou ao interagir fora dele, e permanece dentro da viewport.</p>
            <p>O identificador central moreActions representa reticências vetoriais; ícones de objetos continuam usando o catálogo PNG. Não desenhe esse comando novamente na tela.</p>
            <p><strong>Proibido.</strong> Eliminar comandos por falta de largura, reconstruir o popover localmente ou separar uma associação para caber na barra.</p>
            <p><strong>Exceção.</strong> Se nenhuma distribuição preservar os controles principais na largura suportada, proponha um novo padrão no guia antes de alterar a toolbar da tela.</p>
        </section>
    </article>
</template>
