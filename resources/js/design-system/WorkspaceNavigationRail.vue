<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkspaceDestinationScope, WorkspaceNavigationCategory } from '../workspace/workspaceTypes';
import WorkspaceSurfaceIcon from './WorkspaceSurfaceIcon.vue';

const emit = defineEmits<{
    selectCategory: [categoryId: string];
    previewCategory: [categoryId: string];
    toggleScope: [scope: WorkspaceDestinationScope];
    toggleCollapsed: [];
}>();
const { t } = useI18n();
const props = withDefaults(defineProps<{
    categories: readonly WorkspaceNavigationCategory[];
    activeCategoryId: string | null;
    collapsed: boolean;
    collapsedScopes?: readonly WorkspaceDestinationScope[];
    scopeLabels?: Partial<Record<WorkspaceDestinationScope, string>>;
    collapseLabel: string;
    expandLabel: string;
}>(), {
    collapsedScopes: () => [],
});
const scopedCategories = computed(() => {
    const groups = new Map<WorkspaceDestinationScope, { scope: WorkspaceDestinationScope; label: string; categories: WorkspaceNavigationCategory[] }>();
    for (const category of props.categories) {
        const group = groups.get(category.scope) ?? { scope: category.scope, label: category.scopeLabel, categories: [] };
        group.categories.push(category);
        groups.set(category.scope, group);
    }

    return [...groups.values()];
});
function scopeToggleLabel(scope: string, collapsed: boolean): string {
    return `${collapsed ? 'Expandir' : 'Recolher'} ${scope}`;
}
function scopeLabel(scope: WorkspaceDestinationScope, fallback: string): string {
    return props.scopeLabels?.[scope] ?? fallback;
}
</script>

<template>
    <aside class="workspace-navigation-rail" :class="{ 'workspace-navigation-rail--collapsed': collapsed }" :aria-label="t('access.workspace.navigation.title')">
        <div class="workspace-navigation-rail__categories" role="list">
            <section v-for="group in scopedCategories" :key="group.scope" class="workspace-navigation-rail__scope" :class="{ 'workspace-navigation-rail__scope--collapsed': collapsedScopes.includes(group.scope) }" :aria-label="scopeLabel(group.scope, group.label)">
                <header v-if="!collapsed" class="workspace-navigation-rail__scope-header">
                    <button type="button" class="workspace-navigation-rail__scope-toggle" :aria-label="scopeToggleLabel(scopeLabel(group.scope, group.label), collapsedScopes.includes(group.scope))" :aria-expanded="!collapsedScopes.includes(group.scope)" @click="emit('toggleScope', group.scope)">
                        <span>{{ scopeLabel(group.scope, group.label) }}</span>
                        <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path :d="collapsedScopes.includes(group.scope) ? 'm17 20 7 7 7-7' : 'm17 28 7-7 7 7'" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                </header>
                <button v-else type="button" class="workspace-navigation-rail__scope-compact-toggle" :aria-label="scopeToggleLabel(scopeLabel(group.scope, group.label), collapsedScopes.includes(group.scope))" :aria-expanded="!collapsedScopes.includes(group.scope)" @click="emit('toggleScope', group.scope)">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path :d="collapsedScopes.includes(group.scope) ? 'm17 20 7 7-7 7' : 'm17 28 7-7 7 7'" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
                <div class="workspace-navigation-rail__scope-content">
                    <div v-show="!collapsedScopes.includes(group.scope)" class="workspace-navigation-rail__scope-categories">
                        <button
                            v-for="category in group.categories"
                            :key="category.id"
                            class="workspace-navigation-rail__category"
                            :class="{ 'workspace-navigation-rail__category--active': activeCategoryId === category.id }"
                            type="button"
                            :aria-controls="'workspace-mega-menu'"
                            :aria-expanded="activeCategoryId === category.id"
                            :aria-label="`${scopeLabel(group.scope, group.label)}: ${category.label ?? t(category.titleKey)}`"
                            :title="`${scopeLabel(group.scope, group.label)}: ${category.label ?? t(category.titleKey)}`"
                            :data-category-id="category.id"
                            @click="emit('selectCategory', category.id)"
                            @mouseenter="emit('previewCategory', category.id)"
                        >
                            <WorkspaceSurfaceIcon class="workspace-navigation-rail__category-icon" :name="category.icon" />
                            <span v-if="!collapsed" class="workspace-navigation-rail__category-label">{{ category.label ?? t(category.titleKey) }}</span>
                        </button>
                    </div>
                </div>
            </section>
        </div>
        <button
            class="workspace-navigation-rail__toggle"
            type="button"
            :aria-label="collapsed ? expandLabel : collapseLabel"
            :title="collapsed ? expandLabel : collapseLabel"
            @click="emit('toggleCollapsed')"
        >
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                <path :d="collapsed ? 'm19 13 11 11-11 11' : 'm29 13-11 11 11 11'" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </aside>
</template>
