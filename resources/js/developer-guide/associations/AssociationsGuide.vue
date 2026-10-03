<script setup lang="ts">
import { ref } from 'vue';
import IconButton from '../../design-system/IconButton.vue';
import UiButton from '../../design-system/UiButton.vue';

const iconPath = (name: string): string => `/assets/icons/${name}_24.png`;
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

        <section id="associations-field-command" class="developer-guide-section" aria-labelledby="associations-field-command-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="associations-field-command-title">Campo e comando</h2>
                <p>Use esta associação quando uma entrada textual recebe comandos diretamente relacionados ao seu conteúdo. É o padrão da busca e dos filtros da listagem de Pessoas.</p>
            </div>
            <div class="developer-guide-showcase">
                <div class="developer-guide-association__search-tools" role="group" aria-label="Busca de pessoas">
                    <label class="developer-guide-association__search-field">
                        <span class="developer-guide__sr-only">Pesquisar pessoas</span>
                        <input v-model="search" type="search" placeholder="Buscar pessoas">
                    </label>
                    <IconButton label="Pesquisar"><img :src="iconPath('search')" alt="" aria-hidden="true"></IconButton>
                    <IconButton label="Filtros" :aria-pressed="filtersOpen" @click="filtersOpen = !filtersOpen"><img :src="iconPath('funnel')" alt="" aria-hidden="true"></IconButton>
                </div>
                <p v-if="filtersOpen" class="developer-guide-association__state" role="status">Filtros ativos na demonstração.</p>
            </div>
            <ul class="developer-guide-rule-list">
                <li>O campo ocupa o espaço flexível; os comandos ficam no fim e possuem largura fixa de controle.</li>
                <li>O grupo compartilha o contorno e o anel de foco. Há divisor entre campo e botão; entre dois botões consecutivos, não há divisor.</li>
                <li>O botão de filtro é um alternador e expõe <code>aria-pressed</code>; pesquisar executa a consulta do texto atual.</li>
            </ul>
        </section>

        <section id="associations-button-bar" class="developer-guide-section" aria-labelledby="associations-button-bar-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="associations-button-bar-title">Barras de botões</h2>
                <p>Uma barra reúne comandos irmãos de mesmo contexto. A ordem é estável, os botões encostam visualmente e cada um continua sendo um comando independente.</p>
            </div>
            <div class="developer-guide-showcase developer-guide-showcase--split">
                <div>
                    <h3>Controles de seleção</h3>
                    <div class="developer-guide-association__icon-bar" role="toolbar" aria-label="Controles de seleção">
                        <IconButton label="Manter seleção" :aria-pressed="keepSelection" @click="keepSelection = !keepSelection"><img :src="iconPath('tableLockSelection')" alt="" aria-hidden="true"></IconButton>
                        <IconButton label="Exibir selecionados" :aria-pressed="showSelected" @click="showSelected = !showSelected"><img :src="iconPath('tableShowSelected')" alt="" aria-hidden="true"></IconButton>
                        <IconButton label="Incluir selecionados ocultos" :aria-pressed="includeHidden" @click="includeHidden = !includeHidden"><img :src="iconPath('tableShowHiddenSelected')" alt="" aria-hidden="true"></IconButton>
                        <IconButton label="Limpar seleção" @click="keepSelection = false; showSelected = false; includeHidden = false"><img :src="iconPath('tableCleanSelection')" alt="" aria-hidden="true"></IconButton>
                    </div>
                </div>
                <div>
                    <h3>Comandos de registro</h3>
                    <div class="developer-guide-association__action-bar" role="toolbar" aria-label="Comandos do registro">
                        <UiButton variant="secondary"><img class="developer-guide__button-icon" :src="iconPath('dataInsert')" alt="" aria-hidden="true">Inserir</UiButton>
                        <UiButton variant="secondary"><img class="developer-guide__button-icon" :src="iconPath('dataEdit')" alt="" aria-hidden="true">Alterar</UiButton>
                        <UiButton variant="secondary"><img class="developer-guide__button-icon" :src="iconPath('dataView')" alt="" aria-hidden="true">Visualizar</UiButton>
                    </div>
                </div>
            </div>
            <ul class="developer-guide-rule-list">
                <li>Não misture, na mesma barra compacta, comandos de seleção, navegação e ações destrutivas.</li>
                <li>Em uma barra de texto, o comando destrutivo fica separado visualmente ou após as ações seguras.</li>
                <li>Em telas estreitas, a barra textual quebra entre botões; a barra compacta preserva a sequência e não reduz o alvo de toque. Ícones usam superfícies transparentes, espaçamento entre comandos e uma pílula discreta abaixo do estado ativo.</li>
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
                    <div class="developer-guide-association__select-command-controls" role="group" aria-labelledby="association-field-label">
                        <select v-model="selectedField" aria-label="Campo"><option>Nome</option><option>Tipo de pessoa</option><option>Situação</option></select>
                        <UiButton variant="secondary"><img class="developer-guide__button-icon" :src="iconPath('dataInsert')" alt="" aria-hidden="true">Inserir condição</UiButton>
                    </div>
                </div>
                <p class="developer-guide-association__state" role="status">Campo selecionado: {{ selectedField }}.</p>
            </div>
            <ul class="developer-guide-rule-list">
                <li>O rótulo do combo identifica o dado escolhido; o botão descreve o que fará com essa escolha.</li>
                <li>Entre combo e botão há divisor; somente sequências de botões usam uma superfície contínua, sem divisores internos.</li>
                <li>Não use um botão como substituto de <code>select</code> quando a pessoa precisa escolher entre opções fechadas.</li>
                <li>Use <code>secondary</code> enquanto a associação prepara a condição; a confirmação final da busca ou gravação segue o padrão de Botões.</li>
            </ul>
        </section>
    </article>
</template>
