<script setup lang="ts">
import { computed, ref } from 'vue';
import AssociationsGuide from './associations/AssociationsGuide.vue';
import ButtonsGuide from './buttons/ButtonsGuide.vue';
import DeveloperGuideTopBar from './DeveloperGuideTopBar.vue';

type GuideTopic = 'buttons' | 'associations';

const activeTopic = ref<GuideTopic>('buttons');
const guideTopics: ReadonlyArray<{ id: GuideTopic; label: string; sections: ReadonlyArray<{ id: string; label: string }> }> = [
    { id: 'buttons', label: 'Botões', sections: [
        { id: 'buttons-overview', label: 'Visão geral' },
        { id: 'buttons-variants', label: 'Variantes' },
        { id: 'buttons-composition', label: 'Composição' },
        { id: 'buttons-states', label: 'Estados' },
        { id: 'buttons-toggle', label: 'Alternância' },
        { id: 'buttons-accessibility', label: 'Acessibilidade' },
        { id: 'buttons-screen-commands', label: 'Comandos de tela' },
    ] },
    { id: 'associations', label: 'Associações', sections: [
        { id: 'associations-overview', label: 'Visão geral' },
        { id: 'associations-field-command', label: 'Campo e comando' },
        { id: 'associations-button-bar', label: 'Barras de botões' },
        { id: 'associations-select-command', label: 'Combo e comando' },
    ] },
];
const activeGuideTopic = computed(() => guideTopics.find((topic) => topic.id === activeTopic.value)!);
const activeTitle = computed(() => activeGuideTopic.value.label);

function openTopic(topic: GuideTopic): void {
    activeTopic.value = topic;
}

function navigateTo(sectionId: string): void {
    document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <div class="developer-guide">
        <DeveloperGuideTopBar />
        <div class="developer-guide__layout">
            <aside class="developer-guide__sidebar">
                <nav class="developer-guide__navigation" aria-label="Guia de padrões visuais">
                    <p class="developer-guide__navigation-label">Guia de interface</p>
                    <template v-for="topic in guideTopics" :key="topic.id">
                        <button
                            class="developer-guide__topic"
                            :class="{ 'developer-guide__topic--active': activeTopic === topic.id }"
                            type="button"
                            :aria-current="activeTopic === topic.id ? 'page' : undefined"
                            aria-controls="developer-guide-content"
                            @click="openTopic(topic.id)"
                        >
                            {{ topic.label }}
                        </button>
                        <div v-if="activeTopic === topic.id" class="developer-guide__subtopics">
                            <button v-for="section in topic.sections" :key="section.id" type="button" @click="navigateTo(section.id)">
                            {{ section.label }}
                            </button>
                        </div>
                    </template>
                </nav>
            </aside>
            <main id="developer-guide-content" class="developer-guide__content" :aria-label="activeTitle">
                <ButtonsGuide v-if="activeTopic === 'buttons'" />
                <AssociationsGuide v-else />
            </main>
        </div>
    </div>
</template>
