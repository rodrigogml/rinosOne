<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import UserAvatar from '../design-system/UserAvatar.vue';
import UiAlert from '../design-system/UiAlert.vue';
import UIRinoButton from '../design-system/UIRinoButton.vue';

export interface ProfilePresentation {
    user: {
        displayName: string;
    };
    avatar: {
        available: boolean;
        url: string | null;
        updatedAt: string | null;
    };
}

const props = withDefaults(defineProps<{
    profile?: ProfilePresentation | null;
    loading?: boolean;
    savingName?: boolean;
    removingAvatar?: boolean;
    nameError?: string | null;
    loadError?: string | null;
}>(), {
    profile: null,
    loading: false,
    savingName: false,
    removingAvatar: false,
    nameError: null,
    loadError: null,
});

const emit = defineEmits<{
    saveName: [displayName: string];
    requestRemoveAvatar: [];
    requestEditAvatar: [];
    dirtyChanged: [dirty: boolean];
    retryLoad: [];
    retryName: [displayName: string];
    nameEdited: [];
}>();
const { t } = useI18n();
const displayName = ref('');
const nameChanged = computed(() => displayName.value.trim() !== (props.profile?.user.displayName ?? ''));
const nameEmpty = computed(() => nameChanged.value && !displayName.value.trim());
const describedBy = computed(() => nameEmpty.value || props.nameError ? 'profile-display-name-error' : undefined);
const avatarImageSource = computed(() => {
    const avatar = props.profile?.avatar;
    if (!avatar?.available || !avatar.url) return null;

    return avatar.updatedAt ? `${avatar.url}?v=${encodeURIComponent(avatar.updatedAt)}` : avatar.url;
});
watch(() => props.profile?.user.displayName, (value) => { displayName.value = value ?? ''; }, { immediate: true });
watch(nameChanged, (dirty) => emit('dirtyChanged', dirty), { immediate: true });
function saveName(): void {
    const value = displayName.value.trim();
    if (value && nameChanged.value) emit('saveName', value);
}
</script>

<template>
    <section class="profile-settings-panel" :aria-label="t('access.profile.section')">
        <header class="profile-settings-panel__header">
            <h3>{{ t('access.profile.section') }}</h3>
            <p>{{ t('access.profile.description') }}</p>
        </header>

        <p v-if="loading" class="profile-settings-panel__status" aria-live="polite">{{ t('access.profile.loading') }}</p>
        <section v-else-if="loadError" class="profile-settings-panel__feedback" aria-live="polite">
            <UiAlert tone="error">{{ loadError }}</UiAlert>
            <UIRinoButton variant="secondary" @click="emit('retryLoad')" label="access.profile.retry" />
        </section>
        <template v-else-if="profile">
        <section class="workspace-settings__panel profile-settings-panel__identity" :aria-label="t('access.profile.identity')">
            <UserAvatar
                :display-name="profile?.user.displayName"
                :image-src="avatarImageSource"
                :label="profile?.avatar.available ? t('access.profile.avatarCurrent') : t('access.profile.avatarMissing')"
            />
            <div>
                <h4>{{ t('access.profile.identity') }}</h4>
                <p>{{ profile?.avatar.available ? t('access.profile.imageCurrent') : t('access.profile.noImage') }}</p>
            </div>
            <div class="workspace-settings__setting-actions">
                <UIRinoButton variant="secondary" @click="emit('requestEditAvatar')" :label="profile?.avatar.available ? 'access.profile.changeImage' : 'access.profile.addImage'" />
                <UIRinoButton v-if="profile.avatar.available" variant="destructive" :loading="removingAvatar" @click="emit('requestRemoveAvatar')" label="access.profile.removeImage" />
            </div>
        </section>

        <form class="workspace-settings__panel profile-settings-panel__name" :aria-label="t('access.profile.name')" @submit.prevent="saveName">
            <label for="profile-display-name">{{ t('access.profile.name') }}</label>
            <input id="profile-display-name" v-model="displayName" type="text" autocomplete="name" :aria-invalid="nameEmpty || Boolean(nameError)" :aria-describedby="describedBy" :disabled="savingName" @input="emit('nameEdited')">
            <p v-if="nameEmpty" id="profile-display-name-error" class="profile-settings-panel__error" role="alert">{{ t('access.profile.nameRequired') }}</p>
            <p v-else-if="nameError" id="profile-display-name-error" class="profile-settings-panel__error" role="alert">{{ nameError }}</p>
            <div class="profile-settings-panel__name-actions">
                <UIRinoButton type="submit" :disabled="!nameChanged || nameEmpty" :loading="savingName" variant="primary" label="access.profile.save" />
                <UIRinoButton v-if="nameError" variant="secondary" :disabled="savingName" @click="emit('retryName', displayName.trim())" label="access.profile.retry" />
            </div>
        </form>
        </template>
    </section>
</template>
