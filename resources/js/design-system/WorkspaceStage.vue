<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';
import WorkspaceSettingsSurface from './WorkspaceSettingsSurface.vue';
import MaintenanceHubSurface from '../maintenance/MaintenanceHubSurface.vue';
import WorkspaceFoldersSurface from '../workspace/WorkspaceFoldersSurface.vue';
import AuthorizationAdministrationSurface from '../authorization/AuthorizationAdministrationSurface.vue';
import AdvancedAuthorizationControls from '../authorization/AdvancedAuthorizationControls.vue';
import WorkspaceSurfaceIcon from './WorkspaceSurfaceIcon.vue';
import type { ProfilePresentation } from '../profile/ProfileSettingsPanel.vue';

const props = defineProps<{
    surface: WorkspaceSurface | null;
    surfaces?: readonly WorkspaceSurface[];
}>();
const emit = defineEmits<{ requestClose: [surfaceId: string]; profileUpdated: [profile: ProfilePresentation] }>();

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
            <WorkspaceSurfaceIcon :name="surface.icon" size="lg" />
            <h2>{{ surface.label ?? t(surface.titleKey) }}</h2>
            <button class="workspace-stage__close" type="button" :aria-label="`Fechar: ${surface.label ?? t(surface.titleKey)}`" :title="`Fechar: ${surface.label ?? t(surface.titleKey)}`" @click="emit('requestClose', surface.id)"><svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path d="M14 14 34 34M34 14 14 34" stroke-linecap="round" /></svg></button>
        </header>
        <div class="workspace-stage__surface-content">
            <slot name="surface" :surface="surface">
                <div v-for="mountedSurface in mountedSurfaces" :key="mountedSurface.id" v-show="mountedSurface.id === surface.id" class="workspace-stage__surface-instance">
                    <WorkspaceSettingsSurface v-if="mountedSurface.destinationId === 'personal.settings'" :surface="mountedSurface" @profile-updated="emit('profileUpdated', $event)" />
                    <MaintenanceHubSurface v-else-if="mountedSurface.destinationId === 'platform.maintenance'" />
                    <WorkspaceFoldersSurface v-else-if="mountedSurface.destinationId === 'personal.drive'" />
                    <template v-else-if="mountedSurface.destinationId === 'tenant.authorization-administration'"><AuthorizationAdministrationSurface :surface="mountedSurface" /><AdvancedAuthorizationControls :surface="mountedSurface" /></template>
                    <p v-else class="workspace-stage__empty-content">{{ t('access.workspace.surface.unavailableDescription') }}</p>
                </div>
            </slot>
        </div>
    </section>
</template>
