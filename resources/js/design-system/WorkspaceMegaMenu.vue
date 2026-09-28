<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkspaceDestination, WorkspaceNavigationCategory } from '../workspace/workspaceTypes';
import WorkspaceSurfaceIcon from './WorkspaceSurfaceIcon.vue';

const emit = defineEmits<{ openDestination: [destination: WorkspaceDestination] }>();
const { t } = useI18n();
const props = defineProps<{
    category: WorkspaceNavigationCategory | null;
    destinations: readonly WorkspaceDestination[];
    emptyLabel: string;
    positionTop?: string;
    contextLabel?: string | null;
}>();
const headerContextLabel = computed(() => props.contextLabel ?? props.category?.scopeLabel ?? '');
const destinationGroups = computed(() => {
    const groups = new Map<string, { label?: string; destinations: WorkspaceDestination[] }>();
    const fallbackGroupKey = props.category?.titleKey ?? '';

    for (const destination of props.destinations) {
        const groupKey = destination.groupKey ?? fallbackGroupKey;
        const group = groups.get(groupKey) ?? { label: destination.groupLabel, destinations: [] };
        groups.set(groupKey, { ...group, destinations: [...group.destinations, destination] });
    }

    return [...groups].map(([titleKey, group]) => ({ titleKey, ...group }));
});
</script>

<template>
    <section
        v-if="category"
        id="workspace-mega-menu"
        class="workspace-mega-menu"
        role="region"
        :aria-label="category.label ?? t(category.titleKey)"
        :style="{ '--workspace-mega-menu-top': positionTop ?? '0px' }"
    >
        <header class="workspace-mega-menu__header">
            <p class="workspace-mega-menu__context">{{ headerContextLabel }}</p>
            <h2>{{ category.label ?? t(category.titleKey) }}</h2>
        </header>
        <div v-if="destinations.length" class="workspace-mega-menu__columns">
            <div v-for="group in destinationGroups" :key="group.titleKey" class="workspace-mega-menu__group">
                <h3 class="workspace-mega-menu__group-title">{{ group.label ?? t(group.titleKey) }}</h3>
                <button
                    v-for="destination in group.destinations"
                    :key="destination.id"
                    class="workspace-mega-menu__destination"
                    type="button"
                    @click="emit('openDestination', destination)"
                >
                    <WorkspaceSurfaceIcon class="workspace-mega-menu__destination-icon" :name="destination.icon" />
                    <span>{{ destination.navigationLabel ?? destination.label ?? t(destination.titleKey) }}</span>
                </button>
            </div>
        </div>
        <p v-else class="workspace-mega-menu__empty">{{ emptyLabel }}</p>
    </section>
</template>
