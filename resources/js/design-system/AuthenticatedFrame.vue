<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { computed, onBeforeUnmount, ref } from 'vue';
import ApplicationTopBar from './ApplicationTopBar.vue';
import AppShell from './AppShell.vue';
import WorkspaceShell from './WorkspaceShell.vue';
import { useTenantContextStore } from '../tenant/tenantContextStore';
import { useWorkspaceStore } from '../workspace/workspaceStore';
import { personalSettingsDestination } from '../workspace/workspaceCatalog';

const props = defineProps<{ displayName?: string | null }>();
const emit = defineEmits<{ signOut: [] }>();
const { t } = useI18n();
const mobileNavigationOpen = ref(false);
const avatarLabel = computed(() => t('access.shell.avatar', { name: props.displayName || '?' }));
const tenantContext = useTenantContextStore();
const workspace = useWorkspaceStore();
function signOut() { tenantContext.discard(); workspace.discard(); emit('signOut'); }
function openSettings() {
    workspace.openDestination(personalSettingsDestination, { tenantId: tenantContext.context?.tenant.id ?? null });
    workspace.menuCollapsed = true;
}
function openMobileTasks() {
    if (!workspace.surfaces.length || workspace.dialogStack.length) return;
    mobileNavigationOpen.value = false;
    workspace.mobileTaskPanelOpen = true;
}
onBeforeUnmount(() => workspace.discard());
</script>

<template>
    <div class="authenticated-application">
        <ApplicationTopBar :display-name="displayName" :brand-label="t('access.brand')" :mobile-navigation-label="t('access.shell.openNavigation')" :mobile-tasks-label="t('access.workspace.taskbar.label')" :mobile-tasks-visible="workspace.surfaces.length > 0" :avatar-label="avatarLabel" :menu-label="t('access.shell.menu')" :settings-label="t('access.shell.userSettings')" :sign-out-label="t('access.shell.signOut')" @sign-out="signOut" @open-settings="openSettings" @open-mobile-navigation="mobileNavigationOpen = true" @open-mobile-tasks="openMobileTasks" @open-personal-menu="mobileNavigationOpen = false" />
        <AppShell :label="t('access.workspace.title')">
            <WorkspaceShell v-model:mobile-navigation-open="mobileNavigationOpen" :brand-label="t('access.brand')" />
        </AppShell>
    </div>
</template>
