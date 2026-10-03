<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import UiButton from '../UiButton.vue';
import UiDialog from '../UiDialog.vue';
import { subscribeDialogs, type DialogRequest } from './dialogService';

const active = ref<DialogRequest | null>(null);
const queue = ref<DialogRequest[]>([]);
let unsubscribe: (() => void) | null = null;

const presentation = computed(() => active.value?.presentation ?? null);
const initialFocusSelector = computed(() => active.value ? `[data-dialog-action=${JSON.stringify(active.value.options.initialFocusActionId)}]` : '');

function enqueue(request: DialogRequest): void {
    if (active.value === null) {
        active.value = request;
        return;
    }

    queue.value = [...queue.value, request];
}

function resolve(actionId: string): void {
    if (!active.value) return;

    active.value.resolve({ actionId });
    active.value = queue.value.shift() ?? null;
}

function resolveEscape(): void {
    const escapeActionId = active.value?.options.escapeActionId;
    if (escapeActionId !== undefined) resolve(escapeActionId);
}

onMounted(() => { unsubscribe = subscribeDialogs(enqueue); });
onBeforeUnmount(() => { unsubscribe?.(); });
</script>

<template>
    <UiDialog
        :key="active?.id"
        :model-value="active !== null"
        :title="active?.options.title ?? ''"
        :window-like="true"
        :icon-src="presentation?.iconSrc ?? ''"
        :tone="presentation?.tone ?? ''"
        :message-dialog="true"
        :destructive="presentation?.urgent === true"
        :escapable="active?.options.escapeActionId !== undefined"
        :backdrop-dismissible="false"
        :initial-focus-selector="initialFocusSelector"
        @update:model-value="resolveEscape"
    >
        <div v-if="active" class="dialog-host__message-region">
            <p class="dialog-host__message">{{ active.options.message }}</p>
        </div>
        <footer v-if="active" class="dialog-host__actions">
            <UiButton v-for="button in active.options.buttons" :key="button.id" :variant="button.variant ?? 'primary'" :data-dialog-action="button.id" @click="resolve(button.id)">{{ button.label }}</UiButton>
        </footer>
    </UiDialog>
</template>
