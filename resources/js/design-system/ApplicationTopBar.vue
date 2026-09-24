<script setup lang="ts">
import BrandMark from './BrandMark.vue';
import UserMenu from './UserMenu.vue';

defineProps<{
    displayName?: string | null;
    brandLabel: string;
    mobileNavigationLabel: string;
    avatarLabel: string;
    menuLabel: string;
    settingsLabel: string;
    settingsUnavailableLabel: string;
    signOutLabel: string;
}>();

const emit = defineEmits<{ signOut: []; openMobileNavigation: []; openPersonalMenu: [] }>();
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
            <UserMenu :display-name="displayName" :avatar-label="avatarLabel" :menu-label="menuLabel" :settings-label="settingsLabel" :settings-unavailable-label="settingsUnavailableLabel" :sign-out-label="signOutLabel" @sign-out="emit('signOut')" @opened="emit('openPersonalMenu')" />
        </div>
    </header>
</template>
