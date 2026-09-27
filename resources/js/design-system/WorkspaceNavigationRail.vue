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
                <svg class="workspace-navigation-rail__category-icon" width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                    <path d="M16 13h22M16 24h22M16 35h22" stroke-linecap="round" />
                    <circle cx="10" cy="13" r="2.2" fill="currentColor" stroke="none" />
                    <circle cx="10" cy="24" r="2.2" fill="currentColor" stroke="none" />
                    <circle cx="10" cy="35" r="2.2" fill="currentColor" stroke="none" />
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
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                <path :d="collapsed ? 'm19 13 11 11-11 11' : 'm29 13-11 11 11 11'" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </aside>
</template>
