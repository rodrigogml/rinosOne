<script setup lang="ts">
import BrandMark from './BrandMark.vue';
import TenantSelector from './TenantSelector.vue';
import UserMenu from './UserMenu.vue';

defineProps<{
    displayName?: string | null;
    brandLabel: string;
    mobileNavigationLabel: string;
    mobileTasksLabel: string;
    mobileTasksVisible?: boolean;
    avatarLabel: string;
    menuLabel: string;
    settingsLabel: string;
    signOutLabel: string;
}>();

const emit = defineEmits<{ signOut: []; openMobileNavigation: []; openMobileTasks: []; openPersonalMenu: []; openSettings: [] }>();
</script>

<template>
    <header class="application-top-bar">
        <div class="application-top-bar__brand">
            <BrandMark class="application-top-bar__desktop-brand" :alt="brandLabel" />
            <button class="application-top-bar__mobile-trigger" type="button" :aria-label="mobileNavigationLabel" @click="emit('openMobileNavigation')">
                <BrandMark :alt="brandLabel" />
            </button>
        </div>
        <div class="application-top-bar__personal">
            <button v-if="mobileTasksVisible" class="application-top-bar__mobile-task-trigger" type="button" :aria-label="mobileTasksLabel" @click="emit('openMobileTasks')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M8 10h7m0 0-2-2m2 2-2 2M16 14H9m0 0 2-2m-2 2m2 2-2 2" /></svg>
            </button>
            <TenantSelector @changed="emit('openPersonalMenu')" />
            <UserMenu :display-name="displayName" :avatar-label="avatarLabel" :menu-label="menuLabel" :settings-label="settingsLabel" :sign-out-label="signOutLabel" @sign-out="emit('signOut')" @open-settings="emit('openSettings')" @opened="emit('openPersonalMenu')" />
        </div>
    </header>
</template>
