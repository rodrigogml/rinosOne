<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';
import WorkspaceSurfaceIcon from './WorkspaceSurfaceIcon.vue';

defineProps<{
    surfaces: readonly WorkspaceSurface[];
    activeSurfaceId: string | null;
    label: string;
    dirtyLabel: string;
}>();

const emit = defineEmits<{
    activate: [surfaceId: string];
}>();
const { t } = useI18n();
</script>

<template>
    <nav v-if="surfaces.length" class="workspace-taskbar" :aria-label="label">
        <div class="workspace-taskbar__items" role="tablist">
            <div v-for="surface in surfaces" :key="surface.id" class="workspace-taskbar__item" :class="{ 'workspace-taskbar__item--active': activeSurfaceId === surface.id }">
                <button
                    :id="`workspace-task-${surface.id}`"
                    class="workspace-taskbar__activate"
                    :class="{ 'workspace-taskbar__activate--active': activeSurfaceId === surface.id, 'workspace-taskbar__activate--unavailable': surface.status === 'unavailable' }"
                    type="button"
                    role="tab"
                    :aria-selected="activeSurfaceId === surface.id"
                    :aria-disabled="surface.status === 'unavailable'"
                    :aria-label="surface.label ?? t(surface.titleKey)"
                    :title="surface.label ?? t(surface.titleKey)"
                    @click="emit('activate', surface.id)"
                >
                    <WorkspaceSurfaceIcon :name="surface.icon" :size="surface.id === activeSurfaceId ? 'lg' : 'md'" />
                    <span v-if="surface.dirty" class="workspace-taskbar__dirty" :aria-label="dirtyLabel">●</span>
                    <span v-if="surface.id === activeSurfaceId" class="workspace-taskbar__active-pill" aria-hidden="true" />
                </button>
            </div>
        </div>
    </nav>
</template>
