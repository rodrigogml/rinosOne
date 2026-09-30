<script setup lang="ts">
import BrandMark from './BrandMark.vue';
import TenantSelector from './TenantSelector.vue';
import UserMenu from './UserMenu.vue';

withDefaults(defineProps<{
    displayName?: string | null;
    avatarUrl?: string | null;
    brandLabel: string;
    mobileNavigationLabel: string;
    mobileTasksLabel: string;
    mobileTasksVisible?: boolean;
    avatarLabel: string;
    menuLabel: string;
    settingsLabel: string;
    signOutLabel: string;
    driveLabel?: string;
}>(), { driveLabel: 'Rinos Drive' });

const emit = defineEmits<{ signOut: []; openMobileNavigation: []; openMobileTasks: []; openPersonalMenu: []; openSettings: []; openDrive: [] }>();
const mobileTasksIcon = '/assets/icons/taskbar2_32.png';
const driveIcon = '/assets/icons/drive_32.png';
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
                <img class="application-top-bar__mobile-task-icon" :src="mobileTasksIcon" alt="" aria-hidden="true">
            </button>
            <button class="application-top-bar__drive-trigger" type="button" :aria-label="driveLabel" :title="driveLabel" @click="emit('openDrive')">
                <img :src="driveIcon" alt="" aria-hidden="true">
            </button>
            <TenantSelector @changed="emit('openPersonalMenu')" />
            <UserMenu :display-name="displayName" :avatar-url="avatarUrl" :avatar-label="avatarLabel" :menu-label="menuLabel" :settings-label="settingsLabel" :sign-out-label="signOutLabel" @sign-out="emit('signOut')" @open-settings="emit('openSettings')" @opened="emit('openPersonalMenu')" />
        </div>
    </header>
</template>
