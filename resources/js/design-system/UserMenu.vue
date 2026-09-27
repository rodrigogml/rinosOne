<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import LanguageSelector from './LanguageSelector.vue';
import UserAvatar from './UserAvatar.vue';
import VisualPreferencesPopover from './VisualPreferencesPopover.vue';

defineProps<{
    displayName?: string | null;
    avatarUrl?: string | null;
    avatarLabel: string;
    menuLabel: string;
    settingsLabel: string;
    signOutLabel: string;
}>();

const emit = defineEmits<{ signOut: []; opened: []; openSettings: [] }>();
const root = ref<HTMLElement | null>(null);
const opener = ref<HTMLButtonElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const open = ref(false);
const settingsIcon = '/assets/icons/rinoUser-tweek_32.png';
const signOutIcon = '/assets/icons/logout_32.png';

function close() { open.value = false; }
function toggle() { open.value = !open.value; }
function openSettings() { close(); emit('openSettings'); }
function handleKeydown(event: KeyboardEvent) { if (!event.defaultPrevented && event.key === 'Escape') { event.preventDefault(); close(); } }
function handlePointerDown(event: PointerEvent) { if (open.value && root.value && !root.value.contains(event.target as Node)) close(); }

watch(open, async (visible) => {
    if (visible) {
        emit('opened');
        await nextTick();
        panel.value?.querySelector<HTMLButtonElement>('button:not(:disabled)')?.focus();
        return;
    }

    opener.value?.focus();
});
onMounted(() => document.addEventListener('pointerdown', handlePointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', handlePointerDown));
</script>

<template>
    <div ref="root" class="user-menu">
        <button ref="opener" class="user-menu__trigger" type="button" :aria-label="avatarLabel" :aria-expanded="open" aria-haspopup="dialog" @click="toggle">
            <UserAvatar :display-name="displayName" :image-src="avatarUrl" :label="avatarLabel" />
        </button>
        <section v-if="open" ref="panel" class="user-menu__panel" role="dialog" :aria-label="menuLabel" tabindex="-1" @keydown="handleKeydown">
            <button class="user-menu__settings" type="button" @click="openSettings"><img class="user-menu__settings-icon" :src="settingsIcon" alt="" aria-hidden="true"><span>{{ settingsLabel }}</span></button>
            <div class="user-menu__utilities">
                <VisualPreferencesPopover />
                <LanguageSelector />
                <button class="ui-icon-button" type="button" :aria-label="signOutLabel" @click="emit('signOut')">
                    <img :src="signOutIcon" alt="" aria-hidden="true">
                </button>
            </div>
        </section>
    </div>
</template>
