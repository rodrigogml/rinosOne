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
            <button class="user-menu__settings" type="button" @click="openSettings">{{ settingsLabel }}</button>
            <div class="user-menu__utilities">
                <VisualPreferencesPopover />
                <LanguageSelector />
                <button class="ui-icon-button" type="button" :aria-label="signOutLabel" @click="emit('signOut')">
                    <svg class="ui-icon-button__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 5H5.5A1.5 1.5 0 0 0 4 6.5v11A1.5 1.5 0 0 0 5.5 19H10" /><path d="m15 8 4 4-4 4M19 12H9" /></svg>
                </button>
            </div>
        </section>
    </div>
</template>
