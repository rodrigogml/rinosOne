<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';
import WorkspaceDemoSurface from './WorkspaceDemoSurface.vue';
import WorkspaceSettingsSurface from './WorkspaceSettingsSurface.vue';
import MaintenanceHubSurface from '../maintenance/MaintenanceHubSurface.vue';
import WorkspaceSurfaceIcon from './WorkspaceSurfaceIcon.vue';

const props = defineProps<{
    surface: WorkspaceSurface | null;
    surfaces?: readonly WorkspaceSurface[];
}>();
const emit = defineEmits<{ openWorkspaceDialog: [surfaceId: string]; notify: [surfaceId: string]; requestClose: [surfaceId: string] }>();

const { t } = useI18n();
const mountedSurfaces = computed(() => props.surfaces?.length
    ? props.surfaces
    : props.surface ? [props.surface] : []);
</script>

<template>
    <section v-if="!surface" class="workspace-stage workspace-stage--empty" aria-label="Área de janelas" />
    <section v-else-if="surface.status === 'unavailable'" class="workspace-stage workspace-stage--unavailable" aria-live="polite">
        <div class="workspace-stage__empty-content">
            <h2>{{ t('access.workspace.surface.unavailableTitle') }}</h2>
            <p>{{ t('access.workspace.surface.unavailableDescription') }}</p>
        </div>
    </section>
    <section v-else class="workspace-stage workspace-stage--active" :aria-label="surface.label ?? t(surface.titleKey)">
        <header class="workspace-stage__header">
            <WorkspaceSurfaceIcon :name="surface.icon" />
            <h2>{{ surface.label ?? t(surface.titleKey) }}</h2>
            <button class="workspace-stage__close" type="button" :aria-label="`Fechar: ${surface.label ?? t(surface.titleKey)}`" :title="`Fechar: ${surface.label ?? t(surface.titleKey)}`" @click="emit('requestClose', surface.id)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" stroke-linecap="round" /></svg></button>
        </header>
        <div class="workspace-stage__surface-content">
            <slot name="surface" :surface="surface">
                <div v-for="mountedSurface in mountedSurfaces" :key="mountedSurface.id" v-show="mountedSurface.id === surface.id" class="workspace-stage__surface-instance">
                    <WorkspaceSettingsSurface v-if="mountedSurface.destinationId === 'personal.settings'" :surface="mountedSurface" />
                    <MaintenanceHubSurface v-else-if="mountedSurface.destinationId === 'platform.maintenance'" />
                    <WorkspaceDemoSurface v-else :surface="mountedSurface" @open-workspace-dialog="emit('openWorkspaceDialog', $event)" @notify="emit('notify', $event)" />
                </div>
            </slot>
        </div>
    </section>
</template>
