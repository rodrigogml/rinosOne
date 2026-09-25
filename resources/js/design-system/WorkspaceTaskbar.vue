<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';

defineProps<{
    surfaces: readonly WorkspaceSurface[];
    activeSurfaceId: string | null;
    label: string;
    closeLabel: string;
    dirtyLabel: string;
}>();

const emit = defineEmits<{
    activate: [surfaceId: string];
    requestClose: [surfaceId: string];
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
                    :aria-label="t(surface.titleKey)"
                    :title="t(surface.titleKey)"
                    @click="emit('activate', surface.id)"
                >
                    <span class="workspace-taskbar__icon" aria-hidden="true"></span>
                    <span class="workspace-taskbar__title">{{ t(surface.titleKey) }}</span>
                    <span v-if="surface.dirty" class="workspace-taskbar__dirty" :aria-label="dirtyLabel">●</span>
                </button>
                <button
                    class="workspace-taskbar__close"
                    type="button"
                    :aria-label="`${closeLabel}: ${t(surface.titleKey)}`"
                    :title="`${closeLabel}: ${t(surface.titleKey)}`"
                    @click="emit('requestClose', surface.id)"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path d="m7 7 10 10M17 7 7 17" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
        </div>
    </nav>
</template>
