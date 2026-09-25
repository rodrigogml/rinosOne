<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useTenantContextStore } from '../tenant/tenantContextStore';
import { availableWorkspaceDestinations, workspaceDestinations, workspaceNavigationCategories } from '../workspace/workspaceCatalog';
import { useWorkspaceStore } from '../workspace/workspaceStore';
import { workspaceShortcutAction } from '../workspace/workspaceShortcuts';
import type { WorkspaceDestination } from '../workspace/workspaceTypes';
import WorkspaceMegaMenu from './WorkspaceMegaMenu.vue';
import MobileNavigationDrawer from './MobileNavigationDrawer.vue';
import WorkspaceNavigationRail from './WorkspaceNavigationRail.vue';
import WorkspaceNotificationHost from './WorkspaceNotificationHost.vue';
import WorkspaceOverlayHost from './WorkspaceOverlayHost.vue';
import WorkspaceStage from './WorkspaceStage.vue';
import WorkspaceTaskbar from './WorkspaceTaskbar.vue';
import WorkspaceMobileTaskPanel from './WorkspaceMobileTaskPanel.vue';

const props = withDefaults(defineProps<{
    titleId: string;
    title: string;
    emptyTitle: string;
    emptyDescription: string;
    mobileNavigationOpen?: boolean;
    brandLabel?: string;
}>(), { mobileNavigationOpen: false, brandLabel: '' });
const emit = defineEmits<{ 'update:mobileNavigationOpen': [value: boolean] }>();

const { t } = useI18n();
const tenantContext = useTenantContextStore();
const workspace = useWorkspaceStore();
const shell = ref<HTMLElement | null>(null);
const activeCategoryId = ref<string | null>(null);
const mobileTaskPanelOpen = ref(false);
const mobileNavigationVisible = ref(props.mobileNavigationOpen);
const context = computed(() => ({ tenantId: tenantContext.context?.tenant.id ?? null }));
const destinations = computed(() => availableWorkspaceDestinations(workspaceDestinations, context.value));
const activeCategory = computed(() => workspaceNavigationCategories.find((category) => category.id === activeCategoryId.value) ?? null);
const activeDestinations = computed(() => destinations.value.filter((destination) => destination.category === activeCategoryId.value));

function closeNavigation(restoreFocus = false): void {
    const trigger = shell.value?.querySelector<HTMLButtonElement>('.workspace-navigation-rail__category--active') ?? null;
    activeCategoryId.value = null;
    if (restoreFocus) {
        void nextTick(() => trigger?.focus());
    }
}

function selectCategory(categoryId: string): void {
    if (activeCategoryId.value === categoryId) {
        closeNavigation(true);
        return;
    }

    activeCategoryId.value = categoryId;
}

function toggleRail(): void {
    workspace.menuCollapsed = !workspace.menuCollapsed;
}

function openDestination(destination: WorkspaceDestination): void {
    const opened = workspace.openDestination(destination, context.value);
    if (!opened) return;

    closeNavigation();
    workspace.menuCollapsed = true;
    setMobileNavigationOpen(false);
    mobileTaskPanelOpen.value = false;
}

function activateSurface(surfaceId: string): boolean {
    return workspace.activateSurface(surfaceId);
}

function requestCloseSurface(surfaceId: string): boolean {
    const result = workspace.requestCloseSurface(surfaceId);
    if (result === 'closed' && !workspace.surfaces.length) workspace.menuCollapsed = false;
    if (!workspace.surfaces.length) mobileTaskPanelOpen.value = false;
    return result !== 'not-found';
}

function activateAdjacentSurface(direction: -1 | 1): boolean {
    const currentIndex = workspace.surfaces.findIndex((surface) => surface.id === workspace.activeSurfaceId);
    if (currentIndex < 0 || workspace.surfaces.length < 2) return false;

    const nextIndex = (currentIndex + direction + workspace.surfaces.length) % workspace.surfaces.length;
    return workspace.activateSurface(workspace.surfaces[nextIndex]!.id);
}

function onDocumentPointerDown(event: PointerEvent): void {
    if (!activeCategoryId.value || !(event.target instanceof Element)) return;
    if (event.target.closest('.workspace-navigation-rail, #workspace-mega-menu')) return;
    closeNavigation(true);
}

function onDocumentKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && activeCategoryId.value) {
        event.preventDefault();
        closeNavigation(true);
        return;
    }

    const action = workspaceShortcutAction(event);
    if (!action) return;

    const accepted = action === 'previous-surface'
        ? activateAdjacentSurface(-1)
        : action === 'next-surface'
            ? activateAdjacentSurface(1)
            : workspace.activeSurfaceId !== null && requestCloseSurface(workspace.activeSurfaceId);

    if (accepted) event.preventDefault();
}

function openMobileTaskPanel(): void {
    if (!workspace.surfaces.length || workspace.dialogStack.length) return;
    setMobileNavigationOpen(false);
    mobileTaskPanelOpen.value = true;
}

function setMobileNavigationOpen(value: boolean): void {
    mobileNavigationVisible.value = value;
    emit('update:mobileNavigationOpen', value);
}

watch(() => tenantContext.context?.tenant.id ?? null, () => {
    closeNavigation();
    setMobileNavigationOpen(false);
    mobileTaskPanelOpen.value = false;
});
watch(() => workspace.dialogStack.length, (count) => {
    if (count) {
        setMobileNavigationOpen(false);
        mobileTaskPanelOpen.value = false;
    }
});
watch(() => props.mobileNavigationOpen, (open) => { mobileNavigationVisible.value = open; });
watch(() => destinations.value, () => {
    if (activeCategoryId.value && !workspaceNavigationCategories.some((category) => category.id === activeCategoryId.value)) {
        closeNavigation();
    }
});
onMounted(() => document.addEventListener('pointerdown', onDocumentPointerDown));
onMounted(() => document.addEventListener('keydown', onDocumentKeydown));
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onDocumentKeydown);
});
</script>

<template>
    <section ref="shell" class="workspace-shell">
        <h1 :id="titleId" class="workspace-shell__title">{{ title }}</h1>
        <div class="workspace-layout">
            <WorkspaceNavigationRail
                :categories="workspaceNavigationCategories"
                :active-category-id="activeCategoryId"
                :collapsed="workspace.menuCollapsed"
                :collapse-label="t('access.workspace.navigation.collapse')"
                :expand-label="t('access.workspace.navigation.expand')"
                @select-category="selectCategory"
                @toggle-collapsed="toggleRail"
            />
            <div class="workspace-content">
                <WorkspaceMegaMenu
                    :category="activeCategory"
                    :destinations="activeDestinations"
                    :empty-label="t('access.workspace.navigation.empty')"
                    @open-destination="openDestination"
                />
                <button v-if="workspace.surfaces.length" class="workspace-mobile-task-trigger" type="button" @click="openMobileTaskPanel">{{ t('access.workspace.taskbar.label') }}</button>
                <WorkspaceStage :surface="workspace.activeSurface" :empty-title="emptyTitle" :empty-description="emptyDescription" />
                <WorkspaceTaskbar
                    :surfaces="workspace.surfaces"
                    :active-surface-id="workspace.activeSurfaceId"
                    :label="t('access.workspace.taskbar.label')"
                    :close-label="t('access.workspace.taskbar.close')"
                    :dirty-label="t('access.workspace.taskbar.dirty')"
                    @activate="activateSurface"
                    @request-close="requestCloseSurface"
                />
            </div>
        </div>
        <WorkspaceOverlayHost :dialogs="workspace.dialogStack" @resolve="workspace.resolveDialog" />
        <WorkspaceNotificationHost :notifications="workspace.notificationQueue" :dismiss-label="t('access.workspace.notification.dismiss')" @dismiss="workspace.dismissNotification" />
        <MobileNavigationDrawer :model-value="mobileNavigationVisible" :brand-label="props.brandLabel" :title="t('access.workspace.navigation.title')" :close-label="t('access.shell.closeNavigation')" :empty-label="t('access.workspace.navigation.empty')" :categories="workspaceNavigationCategories" :destinations="destinations" @update:model-value="setMobileNavigationOpen" @open-destination="openDestination" />
        <WorkspaceMobileTaskPanel v-model="mobileTaskPanelOpen" :surfaces="workspace.surfaces" :active-surface-id="workspace.activeSurfaceId" :title="t('access.workspace.taskbar.label')" :close-label="t('access.shell.closeNavigation')" :empty-label="t('access.workspace.mobile.noSurfaces')" :close-surface-label="t('access.workspace.taskbar.close')" :dirty-label="t('access.workspace.taskbar.dirty')" @activate="activateSurface" @request-close="requestCloseSurface" />
    </section>
</template>
