<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { WorkspaceNavigationCategory } from '../workspace/workspaceTypes';

defineProps<{
    categories: readonly WorkspaceNavigationCategory[];
    activeCategoryId: string | null;
    collapsed: boolean;
    collapseLabel: string;
    expandLabel: string;
}>();

const emit = defineEmits<{
    selectCategory: [categoryId: string];
    previewCategory: [categoryId: string];
    toggleCollapsed: [];
}>();
const { t } = useI18n();
</script>

<template>
    <aside class="workspace-navigation-rail" :class="{ 'workspace-navigation-rail--collapsed': collapsed }" :aria-label="t('access.workspace.navigation.title')">
        <div class="workspace-navigation-rail__categories" role="list">
            <button
                v-for="category in categories"
                :key="category.id"
                class="workspace-navigation-rail__category"
                :class="{ 'workspace-navigation-rail__category--active': activeCategoryId === category.id }"
                type="button"
                :aria-controls="'workspace-mega-menu'"
                :aria-expanded="activeCategoryId === category.id"
                :aria-label="category.label ?? t(category.titleKey)"
                :title="category.label ?? t(category.titleKey)"
                :data-category-id="category.id"
                @click="emit('selectCategory', category.id)"
                @mouseenter="emit('previewCategory', category.id)"
            >
                <svg class="workspace-navigation-rail__category-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M4 5.5h16M4 12h16M4 18.5h16" stroke-linecap="round" />
                    <circle cx="7" cy="5.5" r="1" fill="currentColor" stroke="none" />
                    <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none" />
                    <circle cx="17" cy="18.5" r="1" fill="currentColor" stroke="none" />
                </svg>
                <span v-if="!collapsed" class="workspace-navigation-rail__category-label">{{ category.label ?? t(category.titleKey) }}</span>
            </button>
        </div>
        <button
            class="workspace-navigation-rail__toggle"
            type="button"
            :aria-label="collapsed ? expandLabel : collapseLabel"
            :title="collapsed ? expandLabel : collapseLabel"
            @click="emit('toggleCollapsed')"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path :d="collapsed ? 'm9 5 7 7-7 7' : 'm15 5-7 7 7 7'" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </aside>
</template>
