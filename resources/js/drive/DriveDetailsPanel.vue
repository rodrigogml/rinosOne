<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import UiAlert from '../design-system/UiAlert.vue';
import UIRinoButton from '../design-system/UIRinoButton.vue';
import { rasterIconSource } from '../design-system/rasterIconAssets';
import type { DriveDetailsProjection } from './driveWorkspaceApi';

const props = defineProps<{ open: boolean; details: DriveDetailsProjection | null; loading: boolean; error: string }>();
const emit = defineEmits<{ close: []; retry: []; share: [] }>();
const { t, locale } = useI18n();
const closeButton = ref<HTMLButtonElement | null>(null);

const item = computed(() => props.details?.item ?? null);
const itemIcon = computed(() => item.value ? rasterIconSource(item.value.kind === 'folder' ? 'folderClose' : 'file', 'md') : null);
function formatBytes(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined || bytes === 0) return bytes === 0 ? '0 B' : '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** index).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} ${units[index]}`;
}
function formatDate(value: string | null | undefined): string { return value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—'; }
function handleKeydown(event: KeyboardEvent): void { if (props.open && event.key === 'Escape') { event.preventDefault(); emit('close'); } }
watch(() => props.open, async (open) => { if (open) { await nextTick(); closeButton.value?.focus(); } });
onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <aside v-if="open" class="drive-details-panel" :aria-label="t('access.drive.detailsPanel')" :aria-busy="loading || undefined">
        <header class="drive-details-panel__header"><h4>{{ t('access.drive.details') }}</h4><button ref="closeButton" type="button" :aria-label="t('access.drive.closeDetails')" @click="emit('close')">×</button></header>
        <p v-if="loading" class="drive-details-panel__loading" aria-live="polite">{{ t('access.drive.detailsLoading') }}</p>
        <div v-else-if="error" class="drive-details-panel__error"><UiAlert tone="error">{{ error }}</UiAlert><UIRinoButton variant="secondary" @click="emit('retry')" label="access.drive.retry" /></div>
        <template v-else-if="item">
            <div class="drive-details-panel__identity"><img v-if="itemIcon" :src="itemIcon" alt="" aria-hidden="true"><div><strong>{{ item.displayName }}</strong><span>{{ item.kind === 'folder' ? t('access.drive.folder') : item.detectedMimeType ?? t('access.drive.file') }}</span></div></div>
            <dl class="drive-details-panel__facts"><div><dt>{{ t('access.drive.location') }}</dt><dd>{{ details?.location.displayName }}</dd></div><div><dt>{{ t('access.drive.size') }}</dt><dd>{{ formatBytes(item.logicalSizeBytes) }}</dd></div><div><dt>{{ t('access.drive.modified') }}</dt><dd>{{ formatDate(item.modifiedAt) }}</dd></div><div><dt>{{ t('access.drive.access') }}</dt><dd>{{ details?.capabilities.edit ? t('access.drive.editable') : t('access.drive.readOnly') }}</dd></div></dl>
            <UIRinoButton v-if="item.kind === 'folder'" variant="secondary" @click="emit('share')" label="rinoButtons.context.share" />
            <p v-if="details?.metadata.length" class="drive-details-panel__metadata-note">{{ t('access.drive.metadataAvailable', { count: details.metadata.length }) }}</p>
        </template>
    </aside>
</template>

<style scoped>
.drive-details-panel { position: absolute; z-index: 2; top: 0; right: 0; bottom: 0; display: grid; width: min(22rem, 44%); align-content: start; gap: var(--space-4); padding: var(--space-4); border-left: var(--component-border-width) solid var(--color-border-subtle); background: var(--color-surface-raised); box-shadow: var(--shadow-float); color: var(--color-text-secondary); overflow: auto; }
.drive-details-panel__header, .drive-details-panel__identity { display: flex; align-items: center; gap: var(--space-3); }.drive-details-panel__header { justify-content: space-between; }.drive-details-panel__header h4 { margin: 0; color: var(--color-text-primary); font-family: var(--font-family-display); font-size: var(--font-size-lg); }.drive-details-panel__header button { width: var(--control-height-sm); height: var(--control-height-sm); border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--color-text-secondary); font: inherit; font-size: var(--font-size-xl); cursor: pointer; }.drive-details-panel__header button:hover { background: var(--color-surface-muted); color: var(--color-text-primary); }.drive-details-panel__identity img { width: calc(2.5rem * var(--component-scale)); height: calc(2.5rem * var(--component-scale)); object-fit: contain; }.drive-details-panel__identity div { display: grid; min-width: 0; gap: var(--space-1); }.drive-details-panel__identity strong { overflow: hidden; color: var(--color-text-primary); text-overflow: ellipsis; white-space: nowrap; }.drive-details-panel__identity span, .drive-details-panel__metadata-note { font-size: var(--font-size-sm); }.drive-details-panel__facts { display: grid; gap: var(--space-3); margin: 0; }.drive-details-panel__facts div { display: grid; gap: var(--space-1); padding-bottom: var(--space-3); border-bottom: var(--component-border-width) solid var(--color-border-subtle); }.drive-details-panel__facts dt { color: var(--color-text-secondary); font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold); text-transform: uppercase; letter-spacing: var(--letter-spacing-wide); }.drive-details-panel__facts dd { margin: 0; color: var(--color-text-primary); overflow-wrap: anywhere; }.drive-details-panel__loading, .drive-details-panel__metadata-note { margin: 0; }
.drive-details-panel__error { display: grid; gap: var(--space-3); }
@media (max-width: 700px) { .drive-details-panel { position: fixed; z-index: 40; inset: 2.5vh 2.5vw; width: auto; border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); } }
</style>
