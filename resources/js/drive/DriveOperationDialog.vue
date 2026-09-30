<script setup lang="ts">
import UiButton from '../design-system/UiButton.vue';
import UiDialog from '../design-system/UiDialog.vue';

defineProps<{
    title: string;
    confirmLabel: string;
    cancelLabel: string;
    loading?: boolean;
    error?: string;
}>();
const open = defineModel<boolean>({ required: true });
const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <UiDialog v-model="open" contained :title="title" :backdrop-dismissible="false">
        <form class="drive-operation-dialog" @submit.prevent="emit('submit')">
            <slot />
            <p v-if="error" class="drive-operation-dialog__error" role="alert">{{ error }}</p>
            <div>
                <UiButton variant="secondary" :disabled="loading" @click="open = false">{{ cancelLabel }}</UiButton>
                <UiButton type="submit" :loading="loading">{{ confirmLabel }}</UiButton>
            </div>
        </form>
    </UiDialog>
</template>

<style scoped>
.drive-operation-dialog { display: grid; gap: var(--space-4); margin-top: var(--space-4); }
.drive-operation-dialog :deep(label) { display: grid; gap: var(--space-2); color: var(--color-text-primary); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); }
.drive-operation-dialog :deep(input), .drive-operation-dialog :deep(select) { min-height: var(--control-height-md); padding: 0 var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text-primary); font: inherit; }
.drive-operation-dialog p { margin: 0; }
.drive-operation-dialog__error { color: var(--color-danger); }
.drive-operation-dialog > div { display: flex; justify-content: end; gap: var(--space-3); }
</style>
