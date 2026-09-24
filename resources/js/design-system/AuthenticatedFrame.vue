<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';
import ApplicationTopBar from './ApplicationTopBar.vue';
import AppShell from './AppShell.vue';
import MobileNavigationDrawer from './MobileNavigationDrawer.vue';

const props = defineProps<{ labelledBy: string; displayName?: string | null }>();
const emit = defineEmits<{ signOut: [] }>();
const { t } = useI18n();
const drawerOpen = ref(false);
const avatarLabel = computed(() => t('access.shell.avatar', { name: props.displayName || '?' }));
</script>

<template><div class="authenticated-application"><ApplicationTopBar :display-name="displayName" :brand-label="t('access.brand')" :mobile-navigation-label="t('access.shell.openNavigation')" :avatar-label="avatarLabel" :menu-label="t('access.shell.menu')" :settings-label="t('access.shell.userSettings')" :settings-unavailable-label="t('access.shell.settingsUnavailable')" :sign-out-label="t('access.shell.signOut')" @sign-out="emit('signOut')" @open-mobile-navigation="drawerOpen = true" @open-personal-menu="drawerOpen = false" /><AppShell :labelled-by="labelledBy"><div class="authenticated-frame"><slot name="header" /><slot /></div></AppShell><MobileNavigationDrawer v-model="drawerOpen" :brand-label="t('access.brand')" :title="t('access.shell.navigation')" :close-label="t('access.shell.closeNavigation')" :empty-label="t('access.shell.emptyNavigation')" /></div></template>
