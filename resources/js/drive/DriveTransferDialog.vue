<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import UIRinoButton from '../design-system/UIRinoButton.vue';
import UiDialog from '../design-system/UiDialog.vue';
import type { DriveTransferMode } from './driveWorkspaceApi';

const props = withDefaults(defineProps<{ sourceLabel: string; destinationLabel: string; itemCount: number; sameDrive: boolean; canMove?: boolean; loading?: boolean; error?: string; }>(), { canMove: true });
const open = defineModel<boolean>({ required: true });
const emit = defineEmits<{ confirm: [mode: DriveTransferMode] }>();
const { t } = useI18n();
const mode = ref<DriveTransferMode>(props.sameDrive && props.canMove ? 'MOVE' : 'COPY');
const defaultMode = computed<DriveTransferMode>(() => props.sameDrive && props.canMove ? 'MOVE' : 'COPY');
watch(open, (visible) => { if (visible) mode.value = defaultMode.value; });
watch(defaultMode, (value) => { if (open.value) mode.value = value; });
</script>

<template>
    <UiDialog v-model="open" contained :title="t('access.drive.transferTitle')" :backdrop-dismissible="false">
        <form class="drive-transfer-dialog" @submit.prevent="emit('confirm', mode)">
            <p>{{ t('access.drive.transferDescription', { count: itemCount }) }}</p>
            <dl><div><dt>{{ t('access.drive.transferSource') }}</dt><dd>{{ sourceLabel }}</dd></div><div><dt>{{ t('access.drive.transferDestination') }}</dt><dd>{{ destinationLabel }}</dd></div></dl>
            <fieldset><legend>{{ t('access.drive.transferMode') }}</legend><label><input v-model="mode" type="radio" value="COPY"> {{ t('access.drive.copy') }}</label><label><input v-model="mode" type="radio" value="MOVE" :disabled="!canMove"> {{ t('access.drive.move') }}</label></fieldset>
            <p v-if="error" role="alert" class="drive-transfer-dialog__error">{{ error }}</p>
            <div><UIRinoButton :disabled="loading" @click="open = false" command="cancel"  /><UIRinoButton type="submit" :loading="loading" variant="primary" :label="mode === 'COPY' ? 'access.drive.copy' : 'access.drive.move'" /></div>
        </form>
    </UiDialog>
</template>

<style scoped>
.drive-transfer-dialog { display: grid; gap: var(--space-4); margin-top: var(--space-4); }.drive-transfer-dialog p { margin: 0; }.drive-transfer-dialog dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-3); margin: 0; }.drive-transfer-dialog dl div, .drive-transfer-dialog fieldset { padding: var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface-muted); }.drive-transfer-dialog dt { color: var(--color-text-secondary); font-size: var(--font-size-sm); }.drive-transfer-dialog dd { margin: var(--space-1) 0 0; overflow: hidden; color: var(--color-text-primary); font-weight: var(--font-weight-semibold); text-overflow: ellipsis; white-space: nowrap; }.drive-transfer-dialog fieldset { display: flex; gap: var(--space-4); color: var(--color-text-primary); }.drive-transfer-dialog legend { padding: 0 var(--space-1); }.drive-transfer-dialog label { display: inline-flex; gap: var(--space-2); align-items: center; }.drive-transfer-dialog__error { color: var(--color-danger); }.drive-transfer-dialog > div { display: flex; justify-content: end; gap: var(--space-3); } @media (max-width: 600px) { .drive-transfer-dialog dl { grid-template-columns: 1fr; }.drive-transfer-dialog fieldset { display: grid; } }
</style>
