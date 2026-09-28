<script setup lang="ts">
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useTenantContextStore } from '../tenant/tenantContextStore';
import { availableWorkspaceDestinations, availableWorkspaceNavigationCategories, workspaceDestinations, workspaceNavigationCategories } from '../workspace/workspaceCatalog';
import { useWorkspaceStore } from '../workspace/workspaceStore';
import { workspaceShortcutAction } from '../workspace/workspaceShortcuts';
import type { WorkspaceDestination, WorkspaceDestinationScope } from '../workspace/workspaceTypes';
import WorkspaceMegaMenu from './WorkspaceMegaMenu.vue';
import MobileNavigationDrawer from './MobileNavigationDrawer.vue';
import WorkspaceNavigationRail from './WorkspaceNavigationRail.vue';
import WorkspaceNotificationHost from './WorkspaceNotificationHost.vue';
import WorkspaceOverlayHost from './WorkspaceOverlayHost.vue';
import WorkspaceStage from './WorkspaceStage.vue';
import WorkspaceTaskbar from './WorkspaceTaskbar.vue';
import WorkspaceMobileTaskPanel from './WorkspaceMobileTaskPanel.vue';
import type { ProfilePresentation } from '../profile/ProfileSettingsPanel.vue';

const props = withDefaults(defineProps<{
    mobileNavigationOpen?: boolean;
    brandLabel?: string;
    displayName?: string | null;
}>(), { mobileNavigationOpen: false, brandLabel: '', displayName: null });
const emit = defineEmits<{ 'update:mobileNavigationOpen': [value: boolean]; profileUpdated: [profile: ProfilePresentation] }>();

const { t } = useI18n();
const tenantContext = useTenantContextStore();
const workspace = useWorkspaceStore();
const shell = ref<HTMLElement | null>(null);
const windowArea = ref<HTMLElement | null>(null);
const activeCategoryId = ref<string | null>(null);
const megaMenuTop = ref('0px');
const mobileNavigationVisible = ref(props.mobileNavigationOpen);
const mobileEdgeGesture = ref<{ pointerId: number; startX: number; startY: number } | null>(null);
const mobileEdgePeekVisible = ref(false);
let mobileEdgePeekTimeout: ReturnType<typeof setTimeout> | null = null;
const maintenanceVisible = ref(false);
const collapsedScopes = ref<WorkspaceDestinationScope[]>([]);
const context = computed(() => ({ tenantId: tenantContext.context?.tenant.id ?? null, domainAccess: maintenanceVisible.value }));
const destinations = computed(() => availableWorkspaceDestinations(workspaceDestinations.filter((destination) => destination.id !== 'platform.maintenance' || maintenanceVisible.value), context.value));
const navigationCategories = computed(() => availableWorkspaceNavigationCategories(workspaceNavigationCategories, context.value)
    .filter((category) => destinations.value.some((destination) => destination.category === category.id)));
const scopeLabels = computed<Partial<Record<WorkspaceDestinationScope, string>>>(() => ({
    personal: props.displayName?.trim() || 'Pessoal',
    tenant: tenantContext.context?.tenant.displayName || 'Organização',
    domain: 'Domínio',
}));
const activeCategory = computed(() => navigationCategories.value.find((category) => category.id === activeCategoryId.value) ?? null);
const activeDestinations = computed(() => destinations.value.filter((destination) => destination.category === activeCategoryId.value));
const activeContextLabel = computed(() => activeCategory.value
    ? scopeLabels.value[activeCategory.value.scope] ?? activeCategory.value.scopeLabel
    : null);

function closeNavigation(restoreFocus = false): void {
    const trigger = shell.value?.querySelector<HTMLButtonElement>('.workspace-navigation-rail__category--active') ?? null;
    activeCategoryId.value = null;
    if (restoreFocus) {
        void nextTick(() => trigger?.focus());
    }
}

function selectCategory(categoryId: string): void {
    activeCategoryId.value = categoryId;
}

function previewCategory(categoryId: string): void {
    if (activeCategoryId.value !== categoryId) activeCategoryId.value = categoryId;
}

function updateMegaMenuPosition(): void {
    void nextTick(() => {
        const area = windowArea.value;
        const menu = shell.value?.querySelector<HTMLElement>('#workspace-mega-menu') ?? null;
        const trigger = activeCategoryId.value
            ? shell.value?.querySelector<HTMLElement>(`.workspace-navigation-rail__category[data-category-id="${activeCategoryId.value}"]`) ?? null
            : null;
        if (!area || !menu || !trigger) return;

        const areaRect = area.getBoundingClientRect();
        const triggerRect = trigger.getBoundingClientRect();
        const naturalHeight = menu.offsetHeight;
        const idealTop = triggerRect.top - areaRect.top + (triggerRect.height - naturalHeight) / 2;
        const maximumTop = Math.max(0, area.clientHeight - naturalHeight);
        megaMenuTop.value = `${Math.round(Math.min(Math.max(0, idealTop), maximumTop))}px`;
    });
}

function toggleRail(): void {
    if (!workspace.menuCollapsed) collapsedScopes.value = [];
    workspace.menuCollapsed = !workspace.menuCollapsed;
}

function toggleScope(scope: WorkspaceDestinationScope): void {
    const isCollapsing = !collapsedScopes.value.includes(scope);
    if (isCollapsing && activeCategory.value?.scope === scope) closeNavigation();
    collapsedScopes.value = collapsedScopes.value.includes(scope)
        ? collapsedScopes.value.filter((candidate) => candidate !== scope)
        : [...collapsedScopes.value, scope];
}

function openDestination(destination: WorkspaceDestination): void {
    const opened = workspace.openDestination(destination, context.value);
    if (!opened) return;

    closeNavigation();
    workspace.menuCollapsed = true;
    setMobileNavigationOpen(false);
    workspace.mobileTaskPanelOpen = false;
}

function activateSurface(surfaceId: string): boolean {
    return workspace.activateSurface(surfaceId);
}

function requestCloseSurface(surfaceId: string): boolean {
    const result = workspace.requestCloseSurface(surfaceId);
    if (result === 'closed' && !workspace.surfaces.length) workspace.menuCollapsed = false;
    if (!workspace.surfaces.length) workspace.mobileTaskPanelOpen = false;
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

function setMobileNavigationOpen(value: boolean): void {
    mobileNavigationVisible.value = value;
    emit('update:mobileNavigationOpen', value);
}

function clearMobileEdgeGesture(): void {
    if (mobileEdgePeekTimeout !== null) clearTimeout(mobileEdgePeekTimeout);
    mobileEdgePeekTimeout = null;
    mobileEdgeGesture.value = null;
    mobileEdgePeekVisible.value = false;
}

function startMobileEdgeGesture(event: PointerEvent): void {
    if (event.pointerType === 'mouse' || mobileNavigationVisible.value) return;

    clearMobileEdgeGesture();
    mobileEdgeGesture.value = { pointerId: event.pointerId, startX: event.clientX, startY: event.clientY };
    mobileEdgePeekTimeout = setTimeout(() => {
        if (mobileEdgeGesture.value?.pointerId === event.pointerId) mobileEdgePeekVisible.value = true;
    }, 180);
    if (event.currentTarget instanceof HTMLElement && typeof event.currentTarget.setPointerCapture === 'function') {
        event.currentTarget.setPointerCapture(event.pointerId);
    }
}

function moveMobileEdgeGesture(event: PointerEvent): void {
    const gesture = mobileEdgeGesture.value;
    if (!gesture || gesture.pointerId !== event.pointerId) return;

    const horizontalDistance = event.clientX - gesture.startX;
    const verticalDistance = Math.abs(event.clientY - gesture.startY);
    if (verticalDistance > horizontalDistance) {
        clearMobileEdgeGesture();
        return;
    }

    if (horizontalDistance < 36) {
        if (horizontalDistance > 8) mobileEdgePeekVisible.value = true;
        return;
    }
    if (event.cancelable) event.preventDefault();
    clearMobileEdgeGesture();
    setMobileNavigationOpen(true);
}

watch(() => tenantContext.context?.tenant.id ?? null, () => {
    closeNavigation();
    setMobileNavigationOpen(false);
    workspace.mobileTaskPanelOpen = false;
});
watch(() => workspace.dialogStack.length, (count) => {
    if (count) {
        setMobileNavigationOpen(false);
        workspace.mobileTaskPanelOpen = false;
    }
});
watch(() => workspace.mobileTaskPanelOpen, (open) => {
    if (open) setMobileNavigationOpen(false);
});
watch(() => props.mobileNavigationOpen, (open) => { mobileNavigationVisible.value = open; });
watch(activeCategoryId, updateMegaMenuPosition);
watch(() => destinations.value, () => {
    if (activeCategoryId.value && !navigationCategories.value.some((category) => category.id === activeCategoryId.value)) {
        closeNavigation();
    }
});
onMounted(() => {
    void axios.get('/api/v1/platform/maintenance/routines').then((response) => { maintenanceVisible.value = Array.isArray(response.data?.routines) && response.data.routines.length > 0; }).catch(() => { maintenanceVisible.value = false; });
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onDocumentKeydown);
    window.addEventListener('resize', updateMegaMenuPosition);
});
onBeforeUnmount(() => {
    clearMobileEdgeGesture();
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onDocumentKeydown);
    window.removeEventListener('resize', updateMegaMenuPosition);
});
</script>

<template>
    <section ref="shell" class="workspace-shell">
        <div class="workspace-layout">
            <WorkspaceNavigationRail
                :categories="navigationCategories"
                :active-category-id="activeCategoryId"
                :collapsed="workspace.menuCollapsed"
                :collapsed-scopes="collapsedScopes"
                :scope-labels="scopeLabels"
                :collapse-label="t('access.workspace.navigation.collapse')"
                :expand-label="t('access.workspace.navigation.expand')"
                @select-category="selectCategory"
                @preview-category="previewCategory"
                @toggle-scope="toggleScope"
                @toggle-collapsed="toggleRail"
            />
            <div class="workspace-content">
                <div ref="windowArea" class="workspace-window-area">
                    <WorkspaceMegaMenu
                        :category="activeCategory"
                        :destinations="activeDestinations"
                        :empty-label="t('access.workspace.navigation.empty')"
                        :position-top="megaMenuTop"
                        :context-label="activeContextLabel"
                        @open-destination="openDestination"
                    />
                    <WorkspaceStage :surface="workspace.activeSurface" :surfaces="workspace.surfaces" @request-close="requestCloseSurface" @profile-updated="emit('profileUpdated', $event)" />
                </div>
                <WorkspaceTaskbar
                    :surfaces="workspace.surfaces"
                    :active-surface-id="workspace.activeSurfaceId"
                    :label="t('access.workspace.taskbar.label')"
                    :dirty-label="t('access.workspace.taskbar.dirty')"
                    @activate="activateSurface"
                />
            </div>
        </div>
        <WorkspaceOverlayHost :dialogs="workspace.dialogStack" @resolve="workspace.resolveDialog" />
        <WorkspaceNotificationHost :notifications="workspace.notificationQueue" :dismiss-label="t('access.workspace.notification.dismiss')" @dismiss="workspace.dismissNotification" />
        <div class="workspace-mobile-edge-gesture" aria-hidden="true" @pointerdown="startMobileEdgeGesture" @pointermove="moveMobileEdgeGesture" @pointerup="clearMobileEdgeGesture" @pointercancel="clearMobileEdgeGesture" />
        <div v-if="mobileEdgePeekVisible" class="workspace-mobile-edge-peek" aria-hidden="true" />
        <MobileNavigationDrawer :model-value="mobileNavigationVisible" :brand-label="props.brandLabel" :title="t('access.workspace.navigation.title')" :close-label="t('access.shell.closeNavigation')" :empty-label="t('access.workspace.navigation.empty')" :categories="navigationCategories" :destinations="destinations" :scope-labels="scopeLabels" @update:model-value="setMobileNavigationOpen" @open-destination="openDestination" />
        <WorkspaceMobileTaskPanel v-model="workspace.mobileTaskPanelOpen" :surfaces="workspace.surfaces" :active-surface-id="workspace.activeSurfaceId" :title="t('access.workspace.taskbar.label')" :close-label="t('access.shell.closeNavigation')" :empty-label="t('access.workspace.mobile.noSurfaces')" :close-surface-label="t('access.workspace.taskbar.close')" :dirty-label="t('access.workspace.taskbar.dirty')" @activate="activateSurface" @request-close="requestCloseSurface" />
    </section>
</template>
