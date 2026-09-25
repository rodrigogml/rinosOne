<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';

defineProps<{
    surface: WorkspaceSurface | null;
    emptyTitle: string;
    emptyDescription: string;
}>();

const { t } = useI18n();
</script>

<template>
    <section v-if="!surface" class="workspace-stage workspace-stage--empty" aria-live="polite">
        <div class="workspace-stage__empty-content">
            <h2>{{ emptyTitle }}</h2>
            <p>{{ emptyDescription }}</p>
        </div>
    </section>
    <section v-else-if="surface.status === 'unavailable'" class="workspace-stage workspace-stage--unavailable" aria-live="polite">
        <div class="workspace-stage__empty-content">
            <h2>{{ t('access.workspace.surface.unavailableTitle') }}</h2>
            <p>{{ t('access.workspace.surface.unavailableDescription') }}</p>
        </div>
    </section>
    <section v-else class="workspace-stage workspace-stage--active" :aria-label="t(surface.titleKey)">
        <header class="workspace-stage__header">
            <span class="workspace-stage__surface-icon" aria-hidden="true"></span>
            <h2>{{ t(surface.titleKey) }}</h2>
        </header>
        <div class="workspace-stage__surface-content">
            <slot name="surface" :surface="surface">
                <p>{{ t('access.workspace.surface.readyDescription') }}</p>
            </slot>
        </div>
    </section>
</template>
