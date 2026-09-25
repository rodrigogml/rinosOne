<script setup lang="ts">
import { computed, ref } from 'vue';
import UiButton from './UiButton.vue';
import WorkspaceWindowDialogHost from './WorkspaceWindowDialogHost.vue';
import type { WorkspaceSurface, WorkspaceWindowDialog } from '../workspace/workspaceTypes';

const props = defineProps<{ surface: WorkspaceSurface }>();
const emit = defineEmits<{
    openWorkspaceDialog: [surfaceId: string];
    notify: [surfaceId: string];
}>();

const windowDialogs = ref<WorkspaceWindowDialog[]>([]);
const identifier = computed(() => props.surface.id.replace('workspace-surface-', '#'));

function openWindowDialog(title = 'Diálogo desta janela', description = 'Este bloqueio pertence somente à janela atual. Menu, taskbar e outras janelas continuam disponíveis.'): void {
    windowDialogs.value = [...windowDialogs.value, { id: `${props.surface.id}-dialog-${windowDialogs.value.length + 1}`, title, description }];
}
function dismissWindowDialog(dialogId: string): void { windowDialogs.value = windowDialogs.value.filter((dialog) => dialog.id !== dialogId); }
</script>

<template>
    <div class="workspace-demo-surface">
        <section class="workspace-demo-surface__intro" aria-label="Resumo da janela">
            <p class="workspace-demo-surface__eyebrow">Ambiente de demonstração</p>
            <h3>{{ surface.label ?? surface.titleKey }}</h3>
            <p>Conteúdo visual temporário para validar janelas, taskbar, diálogos, notificações e comportamento responsivo antes dos módulos reais.</p>
        </section>
        <section class="workspace-demo-surface__metrics" aria-label="Indicadores de demonstração">
            <article><span>Em acompanhamento</span><strong>24</strong><small>itens no período</small></article>
            <article><span>Em processamento</span><strong>8</strong><small>ações pendentes</small></article>
            <article><span>Janela</span><strong>{{ identifier }}</strong><small>instância atual</small></article>
        </section>
        <section class="workspace-demo-surface__actions" aria-label="Ações de demonstração">
            <UiButton @click="emit('notify', surface.id)">Exibir notificação</UiButton>
            <UiButton variant="secondary" @click="emit('openWorkspaceDialog', surface.id)">Diálogo da aplicação</UiButton>
            <UiButton variant="secondary" @click="openWindowDialog()">Diálogo desta janela</UiButton>
        </section>
        <WorkspaceWindowDialogHost :dialogs="windowDialogs" @dismiss="dismissWindowDialog">
            <template #default="{ dialog, dismiss }">
                <p class="dialog-description">{{ dialog.description }}</p>
                <div class="dialog-actions"><UiButton variant="secondary" @click="openWindowDialog('Detalhe do diálogo', 'Este diálogo foi aberto acima do diálogo local anterior.')">Abrir diálogo acima</UiButton><UiButton @click="dismiss">Fechar</UiButton></div>
            </template>
        </WorkspaceWindowDialogHost>
    </div>
</template>
