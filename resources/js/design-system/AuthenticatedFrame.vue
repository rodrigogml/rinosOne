<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { computed, onBeforeUnmount, ref } from 'vue';
import ApplicationTopBar from './ApplicationTopBar.vue';
import AppShell from './AppShell.vue';
import WorkspaceShell from './WorkspaceShell.vue';
import { useTenantContextStore } from '../tenant/tenantContextStore';
import { useWorkspaceStore } from '../workspace/workspaceStore';

const props = defineProps<{ displayName?: string | null }>();
const emit = defineEmits<{ signOut: [] }>();
const { t } = useI18n();
const mobileNavigationOpen = ref(false);
const avatarLabel = computed(() => t('access.shell.avatar', { name: props.displayName || '?' }));
const tenantContext = useTenantContextStore();
const workspace = useWorkspaceStore();
function signOut() { tenantContext.discard(); workspace.discard(); emit('signOut'); }
onBeforeUnmount(() => workspace.discard());
</script>

<template>
    <div class="authenticated-application">
        <ApplicationTopBar :display-name="displayName" :brand-label="t('access.brand')" :mobile-navigation-label="t('access.shell.openNavigation')" :avatar-label="avatarLabel" :menu-label="t('access.shell.menu')" :settings-label="t('access.shell.userSettings')" :settings-unavailable-label="t('access.shell.settingsUnavailable')" :sign-out-label="t('access.shell.signOut')" @sign-out="signOut" @open-mobile-navigation="mobileNavigationOpen = true" @open-personal-menu="mobileNavigationOpen = false" />
        <AppShell :label="t('access.workspace.title')">
            <WorkspaceShell v-model:mobile-navigation-open="mobileNavigationOpen" :brand-label="t('access.brand')" />
        </AppShell>
    </div>
</template>
