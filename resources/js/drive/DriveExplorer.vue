<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import UiAlert from '../design-system/UiAlert.vue';
import UIRinoButton from '../design-system/UIRinoButton.vue';
import { toast } from '../design-system/toast/toastService';
import DrivePanel from './DrivePanel.vue';
import DriveTransferDialog from './DriveTransferDialog.vue';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';
import { loadDriveCatalog, loadDriveExport, driveExportDownloadUrl, requestDriveTransfer, loadDriveTransfer, cancelDriveTransfer, type DriveCatalogProjection, type DriveWorkspaceTarget, type DriveTransferMode, type DriveTransfer } from './driveWorkspaceApi';
import { sameDrive as sameTarget, invalidDriveDrop, type DrivePanelContext, type DriveDropDestination, type SessionExport } from './drivePanelTypes';

const props = defineProps<{ surface: WorkspaceSurface }>();
const { t } = useI18n();
const userId = inject<Ref<number | null>>('authenticated-user-id', ref(null));
const preferenceOwner = computed(() => String(userId.value ?? 'local'));
const isGlobalSurface = computed(() => props.surface.destinationId === 'global.drive');
const windowLabel = computed(() => isGlobalSurface.value ? 'Rinos Drive' : t(props.surface.destinationId === 'tenant.drive' ? 'access.drive.work' : 'access.drive.personal'));
const catalog = ref<DriveCatalogProjection | null>(null);
const catalogError = ref('');
const primaryPane = ref<InstanceType<typeof DrivePanel> | null>(null);
const secondaryPane = ref<InstanceType<typeof DrivePanel> | null>(null);
const secondaryPaneOpen = ref(false);
const isMobileViewport = ref(false);
const panesElement = ref<HTMLElement | null>(null);
const primaryPaneWidth = ref<number | null>(null);
const sessionExports = ref<SessionExport[]>([]);
const exportTimers = new Map<string, ReturnType<typeof setTimeout>>();
const exportDownloadsStarted = new Set<string>();
const exportRetryDelays = new Map<string, number>();
let disposed = false;
const draggedSource = ref<DrivePanelContext | null>(null);
const transferDialogOpen = ref(false);
const transferSource = ref<DrivePanelContext | null>(null);
const transferDestination = ref<DriveDropDestination | null>(null);
const transferCanMove = computed(() => Boolean(transferSource.value?.items.every(item => item.capabilities.edit)));
const transferring = ref(false);
const transferError = ref('');
const activeTransfer = ref<DriveTransfer | null>(null);
const transferPolling = ref(false);
const transferNotice = ref('');
const transferNoticeTone = computed(() => activeTransfer.value?.state === 'COMPLETED' ? 'success' : 'warning');
const offline = ref(!navigator.onLine);
let transferPollTimer: number | null = null;
let transferPollDelay = 1000;
const SECONDARY_PANE_STORAGE_KEY = 'rinos-one.drive.secondary-pane.v1';
const TRANSFER_STORAGE_KEY = 'rinos-one.drive.active-transfer.v1';

function online(): void { offline.value = false; sessionExports.value.filter(entry => ['PENDING', 'PROCESSING'].includes(entry.state)).forEach(scheduleExport); scheduleTransferPoll(); }
function offlineNow(): void { offline.value = true; }
function updateMobileViewport(): void {
    isMobileViewport.value = typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 700px)').matches;
    if (isMobileViewport.value) secondaryPaneOpen.value = false;
}
function toggleSecondaryPane(): void {
    if (isMobileViewport.value) return;
    secondaryPaneOpen.value = !secondaryPaneOpen.value;
    if (!secondaryPaneOpen.value) primaryPaneWidth.value = null;
}
async function loadCatalog(): Promise<void> {
    if (!isGlobalSurface.value) return;
    catalogError.value = '';
    try { catalog.value = await loadDriveCatalog(); }
    catch { catalogError.value = t('access.drive.loadFailed'); }
}
function revokeDrive(nextTarget: DriveWorkspaceTarget): void {
    if (catalog.value) catalog.value = { ...catalog.value, drives: catalog.value.drives.filter(entry => !sameTarget(entry.target, nextTarget)) };
}
async function refreshAll(): Promise<void> {
    await Promise.all([primaryPane.value?.refresh(), secondaryPane.value?.refresh()]);
}
async function synchronizeMutation(nextTarget: DriveWorkspaceTarget, sourcePanel: 'primary' | 'secondary'): Promise<void> {
    // The originating panel has already refreshed itself; only notify its peer.
    await (sourcePanel === 'primary' ? secondaryPane.value : primaryPane.value)?.refreshAffected(nextTarget);
}
async function openTransfer(candidate: DriveDropDestination | Promise<DriveDropDestination>): Promise<void> {
    const source = draggedSource.value;
    draggedSource.value = null;
    let destination: DriveDropDestination;
    try { destination = await candidate; }
    catch { if (!disposed) toast.info(t('access.drive.transferInvalid')); return; }
    if (disposed) return;
    if (!source || offline.value || invalidDriveDrop(source, destination)) { toast.info(t('access.drive.transferInvalid')); return; }
    transferSource.value = source;
    transferDestination.value = destination;
    transferError.value = '';
    transferDialogOpen.value = true;
}
function clampPrimaryPaneWidth(value: number): number {
    const containerWidth = panesElement.value?.getBoundingClientRect().width ?? 0;
    const dividerWidth = 8;
    const minimumPaneWidth = 288;
    const availableWidth = containerWidth - dividerWidth;

    if (availableWidth <= minimumPaneWidth * 2) return Math.max(0, availableWidth / 2);

    return Math.min(Math.max(value, minimumPaneWidth), availableWidth - minimumPaneWidth);
}

function resizePanelsAt(clientX: number): void {
    const bounds = panesElement.value?.getBoundingClientRect();
    if (!bounds) return;

    primaryPaneWidth.value = clampPrimaryPaneWidth(clientX - bounds.left);
}

function onPanelResize(event: PointerEvent): void {
    resizePanelsAt(event.clientX);
}

function stopPanelResize(): void {
    window.removeEventListener('pointermove', onPanelResize);
    window.removeEventListener('pointerup', stopPanelResize);
}

function startPanelResize(event: PointerEvent): void {
    if (window.matchMedia('(max-width: 900px)').matches) return;

    event.preventDefault();
    resizePanelsAt(event.clientX);
    window.addEventListener('pointermove', onPanelResize);
    window.addEventListener('pointerup', stopPanelResize, { once: true });
}

function resizePanelsBy(offset: number): void {
    const bounds = panesElement.value?.getBoundingClientRect();
    if (!bounds) return;

    primaryPaneWidth.value = clampPrimaryPaneWidth((primaryPaneWidth.value ?? ((bounds.width - 8) / 2)) + offset);
}

async function confirmTransfer(mode: DriveTransferMode): Promise<void> {
    if (!transferSource.value || !transferDestination.value || transferring.value) return;
    if (mode === 'MOVE' && !transferCanMove.value) { transferError.value = t('access.drive.transferInvalid'); return; }
    transferring.value = true;
    transferError.value = '';
    try {
        activeTransfer.value = await requestDriveTransfer({ sourceTarget: { ...transferSource.value.target, folderId: transferSource.value.projection.location.kind === 'folder' ? transferSource.value.projection.location.id : null }, destinationTarget: { ...transferDestination.value.target, folderId: transferDestination.value.folderId }, items: transferSource.value.items.map((item) => ({ id: item.id, kind: item.kind })), mode });
        persistActiveTransfer();
        scheduleTransferPoll();
        transferDialogOpen.value = false;
        (transferSource.value.panelId === 'primary' ? primaryPane.value : secondaryPane.value)?.clearSelection();
    } catch { transferError.value = t('access.drive.transferFailed'); }
    finally { transferring.value = false; }
}
function isTerminalTransfer(transfer: DriveTransfer): boolean { return ['COMPLETED', 'FAILED', 'CANCELLED'].includes(transfer.state); }
function persistActiveTransfer(): void {
    try { activeTransfer.value === null || isTerminalTransfer(activeTransfer.value) ? window.localStorage.removeItem(TRANSFER_STORAGE_KEY) : window.localStorage.setItem(TRANSFER_STORAGE_KEY, activeTransfer.value.transferId); } catch { /* Persistência local é apenas uma conveniência. */ }
}
function clearTransferPoll(): void { if (transferPollTimer !== null) clearTimeout(transferPollTimer); transferPollTimer = null; }
function scheduleTransferPoll(): void {
    clearTransferPoll();
    if (disposed || offline.value) return;
    if (activeTransfer.value === null || isTerminalTransfer(activeTransfer.value)) { persistActiveTransfer(); return; }
    transferPollTimer = window.setTimeout(() => { void pollTransfer(); }, transferPollDelay);
    transferPollDelay = Math.min(10_000, transferPollDelay * 2);
}
async function pollTransfer(): Promise<void> {
    if (disposed || offline.value || activeTransfer.value === null || transferPolling.value) return;
    transferPolling.value = true;
    const transferId = activeTransfer.value.transferId;
    try {
        const next = await loadDriveTransfer(transferId);
        if (disposed || activeTransfer.value?.transferId !== transferId) return;
        activeTransfer.value = next;
        transferPollDelay = 1000;
        persistActiveTransfer();
        if (activeTransfer.value !== null && isTerminalTransfer(activeTransfer.value)) {
            transferNotice.value = activeTransfer.value.state === 'COMPLETED' ? t('access.drive.transferCompleted') : t('access.drive.transferTerminal', { state: activeTransfer.value.state });
            void refreshAll();
        }
    }
    catch (error) {
        if (disposed || activeTransfer.value?.transferId !== transferId) return;
        if (axios.isAxiosError(error) && [403, 404].includes(error.response?.status ?? 0)) {
            activeTransfer.value = null;
            persistActiveTransfer();
        }
        transferNotice.value = t('access.drive.transferUnavailable');
    }
    finally { transferPolling.value = false; scheduleTransferPoll(); }
}
async function cancelActiveTransfer(): Promise<void> {
    if (activeTransfer.value?.state !== 'PENDING') return;
    transferPolling.value = true;
    try {
        activeTransfer.value = await cancelDriveTransfer(activeTransfer.value.transferId);
        persistActiveTransfer();
        if (activeTransfer.value !== null && isTerminalTransfer(activeTransfer.value)) transferNotice.value = t('access.drive.transferTerminal', { state: activeTransfer.value.state });
    }
    catch { transferError.value = t('access.drive.transferFailed'); }
    finally { transferPolling.value = false; scheduleTransferPoll(); }
}
function dismissTransferStatus(): void { activeTransfer.value = null; transferNotice.value = ''; }


function updateSessionExport(next: SessionExport): void {
    const existing = sessionExports.value.some(entry => entry.exportId === next.exportId);
    sessionExports.value = existing ? sessionExports.value.map(entry => entry.exportId === next.exportId ? { ...entry, ...next } : entry) : [next, ...sessionExports.value];
}
async function acceptExport(pending: Promise<SessionExport>): Promise<void> {
    let entry: SessionExport;
    try { entry = await pending; }
    catch { if (!disposed) toast.info(t('access.drive.exportFailed')); return; }
    if (disposed) return;
    updateSessionExport(entry);
    toast.info(t('access.drive.exportPreparing'));
    if (entry.state === 'READY') downloadReadyExport(entry);
    else scheduleExport(entry);
}
function downloadReadyExport(entry: SessionExport): void {
    if (exportDownloadsStarted.has(entry.exportId)) return;
    exportDownloadsStarted.add(entry.exportId);
    toast.success(t('access.drive.exportDownloadStarted'));
    void downloadSessionExports([entry]);
}
function scheduleExport(entry: SessionExport): void {
    const previous = exportTimers.get(entry.exportId);
    if (previous) clearTimeout(previous);
    if (disposed || offline.value || !['PENDING', 'PROCESSING'].includes(entry.state)) return;
    exportTimers.set(entry.exportId, setTimeout(() => { void pollExport(entry); }, exportRetryDelays.get(entry.exportId) ?? 3000));
}
async function pollExport(entry: SessionExport): Promise<void> {
    exportTimers.delete(entry.exportId);
    try {
        const job = await loadDriveExport(entry.target, entry.exportId);
        if (disposed) return;
        const next = { ...entry, state: job.state, expiresAt: job.expiresAt };
        updateSessionExport(next);
        exportRetryDelays.delete(entry.exportId);
        if (next.state === 'READY') downloadReadyExport(next);
        else scheduleExport(next);
    } catch (error) {
        if (disposed) return;
        if (axios.isAxiosError(error) && [403, 404].includes(error.response?.status ?? 0)) {
            updateSessionExport({ ...entry, state: error.response?.status === 404 ? 'EXPIRED' : 'FAILED' });
            exportRetryDelays.delete(entry.exportId);
            return;
        }
        exportRetryDelays.set(entry.exportId, Math.min(10000, (exportRetryDelays.get(entry.exportId) ?? 3000) * 2));
        scheduleExport(entry);
    }
}
async function downloadSessionExports(entries: SessionExport[]): Promise<void> {
    for (const entry of entries.filter((candidate) => candidate.state === 'READY')) {
        try {
            const response = await fetch(driveExportDownloadUrl(entry.target, entry.exportId), { credentials: 'same-origin' });
            if (!response.ok) throw new Error('Export download failed');
            const blob = await response.blob();
            const anchor = document.createElement('a');
            anchor.href = URL.createObjectURL(blob);
            anchor.download = 'exportacao.zip';
            document.body.append(anchor);
            anchor.click();
            anchor.remove();
            window.setTimeout(() => URL.revokeObjectURL(anchor.href), 1_000);
            await new Promise((resolve) => window.setTimeout(resolve, 250));
        } catch {
            toast.info(t('access.drive.exportDownloadFailed'));
        }
    }
}

onMounted(() => {
    updateMobileViewport();
    try { if (!isMobileViewport.value && isGlobalSurface.value) secondaryPaneOpen.value = window.localStorage.getItem(SECONDARY_PANE_STORAGE_KEY) === 'open'; } catch { /* Optional storage. */ }
    window.addEventListener('resize', updateMobileViewport);
    window.addEventListener('online', online); window.addEventListener('offline', offlineNow);
    void loadCatalog();
    try {
        const id = window.localStorage.getItem(TRANSFER_STORAGE_KEY);
        if (id) { activeTransfer.value = { transferId: id, state: 'PENDING', mode: 'COPY', totalItems: 0, processedItems: 0, destinationTarget: { kind: 'personal' }, failureCode: null }; void pollTransfer(); }
    } catch { /* Optional storage. */ }
});
watch(secondaryPaneOpen, value => { try { window.localStorage.setItem(SECONDARY_PANE_STORAGE_KEY, value ? 'open' : 'closed'); } catch { /* Optional preference. */ } });
onBeforeUnmount(() => {
    disposed = true; clearTransferPoll(); stopPanelResize();
    exportTimers.forEach(timer => clearTimeout(timer));
    window.removeEventListener('resize', updateMobileViewport);
    window.removeEventListener('online', online); window.removeEventListener('offline', offlineNow);
});
</script>

<template>
    <main class="drive-explorer" :aria-label="windowLabel">
        <UiAlert v-if="catalogError" tone="error">{{ catalogError }} <UIRinoButton accessible-label="access.drive.retry" icon="fileRefresh" @click="loadCatalog" /></UiAlert>
        <UiAlert v-if="transferNotice" :tone="transferNoticeTone" role="status">{{ transferNotice }} <UIRinoButton accessible-label="access.drive.dismissUploadMessage" icon="btCancel" @click="dismissTransferStatus" /></UiAlert>
        <section v-if="activeTransfer" class="drive-explorer__transfer-progress" role="status">
            <span>{{ t('access.drive.transferProgress', { processed: activeTransfer.processedItems, total: activeTransfer.totalItems, state: activeTransfer.state }) }}</span>
            <progress :value="activeTransfer.processedItems" :max="Math.max(activeTransfer.totalItems, 1)">{{ activeTransfer.processedItems }}/{{ activeTransfer.totalItems }}</progress>
            <UIRinoButton v-if="activeTransfer.state === 'PENDING'" label="access.drive.cancelTransfer" :disabled="transferPolling" @click="cancelActiveTransfer" />
        </section>
        <div ref="panesElement" class="drive-explorer__panes" :class="{ 'drive-explorer__panes--split': secondaryPaneOpen && !isMobileViewport }" :style="primaryPaneWidth === null ? undefined : { '--drive-primary-pane-width': `${primaryPaneWidth}px` }">
            <DrivePanel ref="primaryPane" panel-id="primary" :surface="surface" :catalog="catalog" :session-exports="sessionExports" :preference-owner="preferenceOwner" :show-shared="isGlobalSurface" show-exports :show-split="isGlobalSurface && !isMobileViewport" :secondary-open="secondaryPaneOpen" @toggle-split="toggleSecondaryPane" @mutated="synchronizeMutation($event, 'primary')" @revoked="revokeDrive" @export-created="acceptExport" @download-exports="downloadSessionExports" @drag-start="draggedSource = $event" @drag-end="draggedSource = null" @drop-items="openTransfer" />
            <div v-if="secondaryPaneOpen && !isMobileViewport" class="drive-explorer__pane-divider" role="separator" aria-orientation="vertical" :aria-label="t('access.drive.resizePanels')" tabindex="0" @pointerdown="startPanelResize" @keydown.arrow-left.prevent="resizePanelsBy(-32)" @keydown.arrow-right.prevent="resizePanelsBy(32)" @keydown.home.prevent="resizePanelsBy(-Number.MAX_SAFE_INTEGER)" @keydown.end.prevent="resizePanelsBy(Number.MAX_SAFE_INTEGER)" />
            <DrivePanel v-if="secondaryPaneOpen && !isMobileViewport" ref="secondaryPane" panel-id="secondary" :surface="surface" :catalog="catalog" :session-exports="sessionExports" :preference-owner="preferenceOwner" @mutated="synchronizeMutation($event, 'secondary')" @revoked="revokeDrive" @export-created="acceptExport" @download-exports="downloadSessionExports" @drag-start="draggedSource = $event" @drag-end="draggedSource = null" @drop-items="openTransfer" />
        </div>
        <DriveTransferDialog v-if="transferSource && transferDestination" v-model="transferDialogOpen" :source-label="transferSource.projection.location.displayName" :destination-label="transferDestination.displayName" :item-count="transferSource.items.length" :same-drive="sameTarget(transferSource.target, transferDestination.target)" :can-move="transferCanMove" :loading="transferring" :error="transferError" @confirm="confirmTransfer" />
    </main>
</template>

<style scoped>
.drive-explorer { position: relative; display: flex; min-width: 0; min-height: 0; height: 100%; flex-direction: column; gap: var(--space-2); }
.drive-explorer__panes { display: grid; min-width: 0; min-height: 0; height: 0; flex: 1 1 0; }
.drive-explorer__panes--split { grid-template-columns: minmax(0, var(--drive-primary-pane-width, 1fr)) var(--space-2) minmax(0, 1fr); }
.drive-explorer__pane-divider { display: grid; min-height: 0; place-items: center; cursor: col-resize; touch-action: none; }
.drive-explorer__pane-divider::before { width: var(--component-border-width); height: calc(100% - var(--space-4)); background: var(--color-border-subtle); content: ''; }
.drive-explorer__transfer-progress { display: flex; flex: 0 0 auto; align-items: center; gap: var(--space-3); font-size: var(--font-size-sm); }
.drive-explorer__transfer-progress progress { min-width: 0; max-width: 30%; accent-color: var(--color-action-primary); }
</style>
