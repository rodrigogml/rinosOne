<script setup lang="ts">
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ApplicationTopBar from './ApplicationTopBar.vue';
import AppShell from './AppShell.vue';
import WorkspaceShell from './WorkspaceShell.vue';
import { useTenantContextStore } from '../tenant/tenantContextStore';
import { useWorkspaceStore } from '../workspace/workspaceStore';
import { globalDriveDestination, personalSettingsDestination } from '../workspace/workspaceCatalog';
import type { ProfilePresentation } from '../profile/ProfileSettingsPanel.vue';
import { scheduleRegisteredRasterIconPreload } from './rasterIconAssets';

const props = defineProps<{ displayName?: string | null }>();
const emit = defineEmits<{ signOut: []; profileUpdated: [profile: ProfilePresentation] }>();
const { t } = useI18n();
const mobileNavigationOpen = ref(false);
const tenantContext = useTenantContextStore();
const workspace = useWorkspaceStore();
const profile = ref<ProfilePresentation | null>(null);
const effectiveDisplayName = computed(() => profile.value?.user.displayName ?? props.displayName);
const avatarUrl = computed(() => {
    const avatar = profile.value?.avatar;
    if (!avatar?.available || !avatar.url) return null;

    return avatar.updatedAt ? `${avatar.url}?v=${encodeURIComponent(avatar.updatedAt)}` : avatar.url;
});
const avatarLabel = computed(() => t('access.shell.avatar', { name: effectiveDisplayName.value || '?' }));
let cancelRasterIconPreload: (() => void) | null = null;
function profileUpdated(value: ProfilePresentation): void { profile.value = value; emit('profileUpdated', value); }
async function loadProfile(): Promise<void> {
    try { profileUpdated((await axios.get('/api/v1/profile')).data as ProfilePresentation); } catch { /* A imagem é complementar; a topbar mantém as iniciais enquanto o perfil não estiver disponível. */ }
}
function signOut() { tenantContext.discard(); workspace.discard(); emit('signOut'); }
function openSettings() {
    workspace.openDestination(personalSettingsDestination, { tenantId: tenantContext.context?.tenant.id ?? null });
    workspace.menuCollapsed = true;
}
function openDrive() {
    workspace.openDestination(globalDriveDestination, { tenantId: tenantContext.context?.tenant.id ?? null });
    workspace.menuCollapsed = true;
}
function openMobileTasks() {
    if (!workspace.surfaces.length || workspace.dialogStack.length) return;
    mobileNavigationOpen.value = false;
    workspace.mobileTaskPanelOpen = true;
}
onMounted(() => { cancelRasterIconPreload = scheduleRegisteredRasterIconPreload(); void loadProfile(); });
onBeforeUnmount(() => { cancelRasterIconPreload?.(); workspace.discard(); });
</script>

<template>
    <div class="authenticated-application">
        <ApplicationTopBar :display-name="effectiveDisplayName" :avatar-url="avatarUrl" :brand-label="t('access.brand')" :mobile-navigation-label="t('access.shell.openNavigation')" :mobile-tasks-label="t('access.workspace.taskbar.label')" :mobile-tasks-visible="workspace.surfaces.length > 0" :avatar-label="avatarLabel" :menu-label="t('access.shell.menu')" :settings-label="t('access.shell.userSettings')" :sign-out-label="t('access.shell.signOut')" drive-label="Rinos Drive" @sign-out="signOut" @open-drive="openDrive" @open-settings="openSettings" @open-mobile-navigation="mobileNavigationOpen = true" @open-mobile-tasks="openMobileTasks" @open-personal-menu="mobileNavigationOpen = false" />
        <AppShell :label="t('access.workspace.title')">
            <WorkspaceShell v-model:mobile-navigation-open="mobileNavigationOpen" :brand-label="t('access.brand')" :display-name="effectiveDisplayName" @profile-updated="profileUpdated" />
        </AppShell>
    </div>
</template>
