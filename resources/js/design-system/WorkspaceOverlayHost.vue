<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkspaceDialog } from '../workspace/workspaceTypes';
import UiButton from './UiButton.vue';
import UiDialog from './UiDialog.vue';

const props = defineProps<{ dialogs: readonly WorkspaceDialog[] }>();
const emit = defineEmits<{ resolve: [dialogId: string, confirmed: boolean] }>();
const { t } = useI18n();
const activeDialog = computed(() => props.dialogs.at(-1) ?? null);
const isDiscardConfirmation = computed(() => activeDialog.value?.action?.type === 'discard-surface');
const title = computed(() => isDiscardConfirmation.value
    ? t('access.workspace.dialog.discardTitle')
    : t(`access.workspace.dialog.${activeDialog.value?.kind ?? 'information'}Title`));
const description = computed(() => isDiscardConfirmation.value
    ? t('access.workspace.dialog.discardDescription')
    : t(`access.workspace.dialog.${activeDialog.value?.kind ?? 'information'}Description`));

function resolve(confirmed: boolean): void {
    if (activeDialog.value) emit('resolve', activeDialog.value.id, confirmed);
}
</script>

<template>
    <UiDialog
        :model-value="activeDialog !== null"
        :title="title"
        :destructive="isDiscardConfirmation || activeDialog?.kind === 'error'"
        :escapable="activeDialog?.closePolicy === 'dismissible'"
        :backdrop-dismissible="activeDialog?.closePolicy === 'dismissible'"
        contained
        @update:model-value="resolve(false)"
    >
        <p class="dialog-description">{{ description }}</p>
        <div class="dialog-actions">
            <UiButton v-if="activeDialog?.closePolicy === 'dismissible'" variant="secondary" @click="resolve(false)">{{ t('access.workspace.dialog.close') }}</UiButton>
            <template v-else>
                <UiButton variant="secondary" @click="resolve(false)">{{ t('access.workspace.dialog.cancel') }}</UiButton>
                <UiButton variant="destructive" @click="resolve(true)">{{ t('access.workspace.dialog.discard') }}</UiButton>
            </template>
        </div>
    </UiDialog>
</template>
