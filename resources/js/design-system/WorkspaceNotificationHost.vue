<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkspaceNotification } from '../workspace/workspaceTypes';
import UiAlert from './UiAlert.vue';

const props = withDefaults(defineProps<{ notifications: readonly WorkspaceNotification[]; dismissLabel: string; timeout?: number }>(), { timeout: 6000 });
const emit = defineEmits<{ dismiss: [notificationId: string] }>();
const { t } = useI18n();
const notification = computed(() => props.notifications[0] ?? null);
let timer: ReturnType<typeof setTimeout> | null = null;

function clearTimer(): void {
    if (timer !== null) clearTimeout(timer);
    timer = null;
}

function dismiss(): void {
    if (notification.value) emit('dismiss', notification.value.id);
}

watch(notification, (current) => {
    clearTimer();
    if (current && !current.persistent) timer = setTimeout(dismiss, props.timeout);
}, { immediate: true });
onBeforeUnmount(clearTimer);
</script>

<template>
    <aside v-if="notification" class="workspace-notification-host" aria-label="Notificações">
        <UiAlert :tone="notification.kind === 'information' ? 'info' : notification.kind">
            <span>{{ t(notification.messageKey) }}</span>
            <button class="workspace-notification-host__dismiss" type="button" :aria-label="dismissLabel" :title="dismissLabel" @click="dismiss">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" stroke-linecap="round" /></svg>
            </button>
        </UiAlert>
    </aside>
</template>
