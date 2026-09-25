<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkspaceDestination, WorkspaceNavigationCategory } from '../workspace/workspaceTypes';

const emit = defineEmits<{ openDestination: [destination: WorkspaceDestination] }>();
const { t } = useI18n();
const props = defineProps<{
    category: WorkspaceNavigationCategory | null;
    destinations: readonly WorkspaceDestination[];
    emptyLabel: string;
}>();
const destinationGroups = computed(() => {
    const groups = new Map<string, WorkspaceDestination[]>();
    const fallbackGroupKey = props.category?.titleKey ?? '';

    for (const destination of props.destinations) {
        const groupKey = destination.groupKey ?? fallbackGroupKey;
        groups.set(groupKey, [...(groups.get(groupKey) ?? []), destination]);
    }

    return [...groups].map(([titleKey, destinations]) => ({ titleKey, destinations }));
});
</script>

<template>
    <section
        v-if="category"
        id="workspace-mega-menu"
        class="workspace-mega-menu"
        role="region"
        :aria-label="t(category.titleKey)"
    >
        <header class="workspace-mega-menu__header">
            <h2>{{ t(category.titleKey) }}</h2>
        </header>
        <div v-if="destinations.length" class="workspace-mega-menu__columns">
            <div v-for="group in destinationGroups" :key="group.titleKey" class="workspace-mega-menu__group">
                <h3 class="workspace-mega-menu__group-title">{{ t(group.titleKey) }}</h3>
                <button
                    v-for="destination in group.destinations"
                    :key="destination.id"
                    class="workspace-mega-menu__destination"
                    type="button"
                    @click="emit('openDestination', destination)"
                >
                    <span class="workspace-mega-menu__destination-icon" aria-hidden="true"></span>
                    <span>{{ t(destination.titleKey) }}</span>
                </button>
            </div>
        </div>
        <p v-else class="workspace-mega-menu__empty">{{ emptyLabel }}</p>
    </section>
</template>
