<script setup lang="ts">
import { computed, ref } from 'vue';
import AssociationsGuide from './associations/AssociationsGuide.vue';
import ButtonsGuide from './buttons/ButtonsGuide.vue';
import DeveloperGuideTopBar from './DeveloperGuideTopBar.vue';
import ToastMessagesGuide from './toast-messages/ToastMessagesGuide.vue';
import ToastHost from '../design-system/toast/ToastHost.vue';
import DialogsGuide from './dialogs/DialogsGuide.vue';
import DialogHost from '../design-system/dialog/DialogHost.vue';
import FieldsGuide from './fields/FieldsGuide.vue';

type GuideTopic = 'buttons' | 'associations' | 'toast-messages' | 'dialogs' | 'fields';

const activeTopic = ref<GuideTopic>('buttons');
const guideTopics: ReadonlyArray<{ id: GuideTopic; label: string; sections: ReadonlyArray<{ id: string; label: string }> }> = [
    { id: 'buttons', label: 'Botões', sections: [
        { id: 'buttons-overview', label: 'Visão geral' },
        { id: 'buttons-centralizer', label: 'Centralizador' },
        { id: 'buttons-variants', label: 'Variantes' },
        { id: 'buttons-composition', label: 'Composição' },
        { id: 'buttons-states', label: 'Estados' },
        { id: 'buttons-toggle', label: 'Alternância' },
        { id: 'buttons-accessibility', label: 'Acessibilidade' },
        { id: 'buttons-screen-commands', label: 'Comandos de tela' },
    ] },
    { id: 'associations', label: 'Associações', sections: [
        { id: 'associations-overview', label: 'Visão geral' },
        { id: 'associations-norms', label: 'Contrato obrigatório' },
        { id: 'associations-field-command', label: 'Campo e comando' },
        { id: 'associations-button-bar', label: 'Barras de botões' },
        { id: 'associations-select-command', label: 'Combo e comando' },
    ] },
    { id: 'toast-messages', label: 'Toast messages', sections: [
        { id: 'toast-overview', label: 'Visão geral' },
        { id: 'toast-types', label: 'Tipos e posição' },
        { id: 'toast-lifecycle', label: 'Ciclo e fila' },
        { id: 'toast-emission', label: 'Emissão centralizada' },
    ] },
    { id: 'dialogs', label: 'Caixas de diálogo', sections: [
        { id: 'dialogs-overview', label: 'Visão geral' },
        { id: 'dialogs-models', label: 'Modelos visuais' },
        { id: 'dialogs-behavior', label: 'Foco e Escape' },
        { id: 'dialogs-emission', label: 'Emissão centralizada' },
    ] },
    { id: 'fields', label: 'Campos', sections: [
        { id: 'fields-overview', label: 'Visão geral' },
        { id: 'fields-models', label: 'Modelos' },
        { id: 'fields-temporal', label: 'Data e hora' },
        { id: 'fields-validation', label: 'Dados validados' },
        { id: 'fields-adoption', label: 'Adoção' },
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
                <AssociationsGuide v-else-if="activeTopic === 'associations'" />
                <ToastMessagesGuide v-else-if="activeTopic === 'toast-messages'" />
                <DialogsGuide v-else-if="activeTopic === 'dialogs'" />
                <FieldsGuide v-else />
            </main>
        </div>
        <ToastHost has-top-bar />
        <DialogHost />
    </div>
</template>
