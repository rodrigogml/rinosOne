<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import UiAlert from '../design-system/UiAlert.vue';
import UiButton from '../design-system/UiButton.vue';
import { rasterIconSource } from '../design-system/rasterIconAssets';
import DriveDetailsPanel from './DriveDetailsPanel.vue';
import ResourceSharingPanel from '../authorization/ResourceSharingPanel.vue';
import DriveOperationDialog from './DriveOperationDialog.vue';
import DriveNavigationPane from './DriveNavigationPane.vue';
import DriveTransferDialog from './DriveTransferDialog.vue';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';
import {
    loadDriveDetails,
    createDriveFolder,
    loadDriveLocation,
    loadDriveTree,
    loadDriveCatalog,
    loadSharedWithMe,
    moveDriveItem,
    releaseDriveItems,
    restoreDriveItems,
    uploadDriveFile,
    driveDownloadUrl,
    requestDriveExport,
    cancelDriveExport,
    loadDriveExport,
    driveExportDownloadUrl,
    type DriveDetailsProjection,
    type DriveCatalogProjection,
    type DriveItem,
    type DriveLocation,
    type DriveLocationProjection,
    type DriveWorkspaceTarget,
    type DriveTransferMode,
    requestDriveTransfer,
    type SharedWithMeProjection,
    trashDriveItems,
} from './driveWorkspaceApi';

const props = defineProps<{ surface: WorkspaceSurface }>();
const { t, locale } = useI18n();

type ViewMode = 'grid' | 'list' | 'details' | 'table';
interface TreeFolder extends DriveItem { depth: number; }

const isGlobalSurface = computed(() => props.surface.destinationId === 'global.drive');
const target = ref<DriveWorkspaceTarget | null>(isGlobalSurface.value
    ? null
    : props.surface.destinationId === 'tenant.drive' && props.surface.tenantId !== null
        ? { kind: 'tenant', tenantId: props.surface.tenantId }
        : { kind: 'personal' });
const catalog = ref<DriveCatalogProjection | null>(null);
const sharedWithMe = ref<SharedWithMeProjection | null>(null);
const sharedRootOpen = ref(false);
const isWork = computed(() => target.value?.kind === 'tenant');
const workspaceName = computed(() => sharedRootOpen.value
    ? catalog.value?.sharedWithMe.displayName ?? 'Compartilhados comigo'
    : catalog.value?.drives.find((entry) => entry.target.kind === target.value?.kind && (entry.target.kind !== 'tenant' || target.value?.kind !== 'tenant' || entry.target.tenantId === target.value.tenantId))?.displayName
        ?? t(isWork.value ? 'access.drive.work' : 'access.drive.personal'));
const rootLocation = computed<DriveLocation>(() => ({ kind: 'root', id: null, displayName: t(isWork.value ? 'access.drive.organizationRoot' : 'access.drive.root'), parentFolderId: null }));
const tree = ref<DriveItem[]>([]);
const projection = ref<DriveLocationProjection | null>(null);
const selectedIds = ref<number[]>([]);
const selectionAnchorId = ref<number | null>(null);
const loading = ref(false);
const error = ref('');
const stale = ref(false);
const offline = ref(!navigator.onLine);
const treeDrawerOpen = ref(false);
const secondaryPaneOpen = ref(false);
const secondaryDestination = ref<{ target: DriveWorkspaceTarget; projection: DriveLocationProjection } | null>(null);
const transferDialogOpen = ref(false);
const transferring = ref(false);
const transferError = ref('');
const viewMode = ref<ViewMode>('grid');
const detailsOpen = ref(false);
const detailsLoading = ref(false);
const detailsError = ref('');
const details = ref<DriveDetailsProjection | null>(null);
const sharingFolder = ref<DriveItem | null>(null);
const locationHeading = ref<HTMLElement | null>(null);
const createFolderOpen = ref(false);
const createFolderName = ref('');
const createFolderError = ref('');
const creatingFolder = ref(false);
const trashConfirmationOpen = ref(false);
const trashError = ref('');
const trashingItems = ref(false);
const moveDialogOpen = ref(false);
const moveDestinationId = ref<number | null>(null);
const moveError = ref('');
const movingItem = ref(false);
const releaseConfirmationOpen = ref(false);
const releaseError = ref('');
const releasingItems = ref(false);
const restoringItems = ref(false);
const uploadInput = ref<HTMLInputElement | null>(null);
type UploadEntry = { id: number; name: string; file: File; state: 'queued' | 'uploading' | 'complete' | 'failed' | 'cancelled'; progress: number; controller: AbortController | null };
const uploadQueue = ref<UploadEntry[]>([]);
let nextUploadId = 1;
const uploading = computed(() => uploadQueue.value.some((entry) => entry.state === 'queued' || entry.state === 'uploading'));
const VIEW_MODE_STORAGE_KEY = 'rinos-one.drive.view-mode.v1';
const SECONDARY_PANE_STORAGE_KEY = 'rinos-one.drive.secondary-pane.v1';

const hasData = computed(() => projection.value !== null);
const items = computed(() => sharedRootOpen.value
    ? [...(sharedWithMe.value?.folders ?? []), ...(sharedWithMe.value?.files ?? [])].map((item) => ({ ...item, parentFolderId: null, modifiedAt: null, logicalSizeBytes: item.logicalSizeBytes ?? null, detectedMimeType: item.detectedMimeType ?? null }))
    : projection.value ? [...projection.value.folders, ...projection.value.files] : []);
const treeFolders = computed<TreeFolder[]>(() => {
    const byParent = new Map<number | null, DriveItem[]>();
    const knownIds = new Set(tree.value.map((folder) => folder.id));
    for (const folder of tree.value) {
        const group = byParent.get(folder.parentFolderId) ?? [];
        group.push(folder);
        byParent.set(folder.parentFolderId, group);
    }
    const ordered: TreeFolder[] = [];
    const visit = (parentId: number | null, depth: number) => {
        for (const folder of byParent.get(parentId) ?? []) {
            ordered.push({ ...folder, depth });
            visit(folder.id, depth + 1);
        }
    };
    visit(null, 0);
    // Uma árvore parcial pode começar em uma pasta concedida cujo ancestral não
    // é visível. Ela ainda precisa continuar alcançável para o membro.
    for (const folder of tree.value.filter((item) => item.parentFolderId !== null && !knownIds.has(item.parentFolderId))) {
        ordered.push({ ...folder, depth: 0 });
        visit(folder.id, 1);
    }
    return ordered;
});
const collectionLabel = computed(() => projection.value?.location.displayName ?? workspaceName.value);
const selectedCount = computed(() => selectedIds.value.length);
const selectedItem = computed(() => selectedIds.value.length === 1 ? items.value.find((item) => item.id === selectedIds.value[0]) ?? null : null);
const selectedItems = computed(() => items.value.filter((item) => selectedIds.value.includes(item.id)));
const canCreateFolder = computed(() => !sharedRootOpen.value && projection.value?.location.kind !== 'trash' && projection.value?.capabilities.edit === true);
const canTrashSelection = computed(() => !sharedRootOpen.value && projection.value?.location.kind !== 'trash' && selectedItems.value.length > 0 && selectedItems.value.every((item) => item.capabilities.trash));
const canMoveSelection = computed(() => !sharedRootOpen.value && selectedItem.value !== null && selectedItem.value.capabilities.edit && projection.value?.location.kind !== 'trash');
const canUpload = computed(() => canCreateFolder.value && !offline.value);
const canDownloadSelection = computed(() => target.value !== null && selectedItem.value?.kind === 'file' && selectedItem.value.capabilities.read);
const canTransferSelection = computed(() => !sharedRootOpen.value && target.value !== null && secondaryDestination.value?.projection.capabilities.edit === true && selectedItems.value.length > 0 && projection.value?.location.kind !== 'trash');
const exporting = ref(false);
const exportDialogOpen = ref(false);
const exportMessage = ref('');
const activeExportId = ref<string | null>(null);
const activeExportState = ref<string | null>(null);
let exportPollTimer: ReturnType<typeof setTimeout> | null = null;

function online(): void { offline.value = false; }
function offlineNow(): void { offline.value = true; }
function uploadStateLabel(state: UploadEntry['state']): string { return t(`access.drive.upload${state[0].toUpperCase()}${state.slice(1)}`); }
function formatBytes(bytes: number | null): string {
    if (bytes === null || bytes === 0) return bytes === 0 ? '0 B' : '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** index).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} ${units[index]}`;
}
function formatDate(value: string | null): string { return value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—'; }
function itemTypeLabel(item: DriveItem): string { return item.kind === 'folder' ? t('access.drive.folder') : item.detectedMimeType?.split('/').at(-1)?.toUpperCase() ?? t('access.drive.file'); }
function itemIconSource(item: DriveItem): string { return rasterIconSource(item.kind === 'folder' ? 'folderClose' : 'file', 'md') ?? ''; }
function isCurrent(location: DriveLocation): boolean { return projection.value?.location.kind === location.kind && projection.value.location.id === location.id; }
function selectItem(item: DriveItem, event?: MouseEvent | KeyboardEvent): void {
    if (!item.capabilities.read) return;
    if (event?.shiftKey && selectionAnchorId.value !== null) {
        const from = items.value.findIndex((candidate) => candidate.id === selectionAnchorId.value);
        const to = items.value.findIndex((candidate) => candidate.id === item.id);
        if (from >= 0 && to >= 0) selectedIds.value = items.value.slice(Math.min(from, to), Math.max(from, to) + 1).filter((candidate) => candidate.capabilities.read).map((candidate) => candidate.id);
        return;
    }
    if (event?.ctrlKey || event?.metaKey) selectedIds.value = selectedIds.value.includes(item.id) ? selectedIds.value.filter((id) => id !== item.id) : [...selectedIds.value, item.id];
    else selectedIds.value = [item.id];
    selectionAnchorId.value = item.id;
}
function clearSelection(): void { selectedIds.value = []; selectionAnchorId.value = null; }
function clearDetails(): void { detailsOpen.value = false; detailsLoading.value = false; detailsError.value = ''; details.value = null; }
function openSharing(): void { if (details.value?.item.kind === 'folder') sharingFolder.value = details.value.item; }
async function openDetails(): Promise<void> {
    if (!target.value || !selectedItem.value || offline.value) return;
    detailsOpen.value = true;
    detailsLoading.value = true;
    detailsError.value = '';
    details.value = null;
    try { details.value = await loadDriveDetails(target.value, selectedItem.value); }
    catch (reason) {
        detailsError.value = axios.isAxiosError(reason) && reason.response?.status === 403
            ? t('access.drive.detailsDenied')
            : t('access.drive.detailsFailed');
    } finally { detailsLoading.value = false; }
}
function openCreateFolder(): void { createFolderName.value = ''; createFolderError.value = ''; createFolderOpen.value = true; }
async function createFolder(): Promise<void> {
    const displayName = createFolderName.value.trim();
    if (!target.value || !projection.value || !displayName) { createFolderError.value = t('access.drive.folderNameRequired'); return; }
    creatingFolder.value = true;
    createFolderError.value = '';
    try {
        await createDriveFolder(target.value, displayName, projection.value.location.kind === 'folder' ? projection.value.location.id : null);
        createFolderOpen.value = false;
        await refresh();
    } catch (reason) {
        createFolderError.value = axios.isAxiosError(reason) && reason.response?.status === 403
            ? t('access.drive.createFolderDenied')
            : t('access.drive.createFolderFailed');
    } finally { creatingFolder.value = false; }
}
function openTrashConfirmation(): void { trashError.value = ''; trashConfirmationOpen.value = true; }
async function trashSelectedItems(): Promise<void> {
    if (!target.value || !canTrashSelection.value) return;
    trashingItems.value = true;
    trashError.value = '';
    try {
        await trashDriveItems(target.value, selectedItems.value);
        trashConfirmationOpen.value = false;
        selectedIds.value = [];
        await refresh();
    } catch (reason) {
        trashError.value = axios.isAxiosError(reason) && reason.response?.status === 403
            ? t('access.drive.trashDenied')
            : t('access.drive.trashFailed');
    } finally { trashingItems.value = false; }
}
function openMoveDialog(): void { moveDestinationId.value = null; moveError.value = ''; moveDialogOpen.value = true; }
function canMoveTo(folder: DriveItem | null): boolean { return selectedItem.value?.kind !== 'folder' || folder?.id !== selectedItem.value.id; }
async function moveSelectedItem(): Promise<void> {
    if (!target.value || !selectedItem.value || !canMoveSelection.value) return;
    movingItem.value = true;
    moveError.value = '';
    try {
        await moveDriveItem(target.value, selectedItem.value, moveDestinationId.value);
        moveDialogOpen.value = false;
        selectedIds.value = [];
        await refresh();
    } catch (reason) {
        moveError.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.moveDenied') : t('access.drive.moveFailed');
    } finally { movingItem.value = false; }
}
async function restoreSelectedItems(): Promise<void> {
    if (!target.value || !selectedItems.value.length) return;
    restoringItems.value = true;
    try {
        await restoreDriveItems(target.value, selectedItems.value);
        selectedIds.value = [];
        await refresh();
    } catch (reason) { error.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.restoreDenied') : t('access.drive.restoreFailed'); }
    finally { restoringItems.value = false; }
}
function openReleaseConfirmation(): void { releaseError.value = ''; releaseConfirmationOpen.value = true; }
async function releaseSelectedItems(): Promise<void> {
    if (!target.value || !selectedItems.value.length) return;
    releasingItems.value = true;
    try {
        await releaseDriveItems(target.value, selectedItems.value);
        releaseConfirmationOpen.value = false;
        selectedIds.value = [];
        await refresh();
    } catch (reason) { releaseError.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.releaseDenied') : t('access.drive.releaseFailed'); }
    finally { releasingItems.value = false; }
}
function chooseUploads(): void { uploadInput.value?.click(); }
async function uploadFiles(files: FileList | File[]): Promise<void> {
    if (!target.value || !projection.value || !canUpload.value) return;
    uploadQueue.value.push(...Array.from(files).map((file) => ({ id: nextUploadId++, name: file.name, file, state: 'queued' as const, progress: 0, controller: null })));
    for (const entry of uploadQueue.value.filter((candidate) => candidate.state === 'queued')) {
        entry.state = 'uploading'; entry.controller = new AbortController();
        try { await uploadDriveFile(target.value, entry.file, projection.value.location.kind === 'folder' ? projection.value.location.id : null, entry.controller.signal, (progress) => { entry.progress = progress; }); entry.progress = 100; entry.state = 'complete'; }
        catch { entry.state = entry.controller.signal.aborted ? 'cancelled' : 'failed'; }
        finally { entry.controller = null; }
    }
    await refresh();
}
function receiveUploadSelection(event: Event): void { const input = event.target as HTMLInputElement; if (input.files) void uploadFiles(input.files); input.value = ''; }
function cancelUpload(entry: UploadEntry): void { entry.controller?.abort(); if (entry.state === 'queued') entry.state = 'cancelled'; }
function downloadSelectedFile(): void {
    if (!target.value || !selectedItem.value || selectedItem.value.kind !== 'file') return;
    window.location.assign(driveDownloadUrl(target.value, selectedItem.value.id));
}
function openExportDialog(): void { exportMessage.value = ''; exportDialogOpen.value = true; }
async function requestExport(): Promise<void> {
    if (!target.value || selectedItems.value.length < 2 || offline.value) return;
    exporting.value = true;
    exportMessage.value = '';
    try {
        const exportJob = await requestDriveExport(target.value, selectedItems.value);
        activeExportId.value = exportJob.exportId;
        activeExportState.value = exportJob.state;
        exportDialogOpen.value = false;
        exportMessage.value = t('access.drive.exportState', { state: exportJob.state.toLowerCase() });
        scheduleExportPoll();
    } catch { exportMessage.value = t('access.drive.exportFailed'); }
    finally { exporting.value = false; }
}
function scheduleExportPoll(): void {
    if (exportPollTimer !== null) clearTimeout(exportPollTimer);
    exportPollTimer = setTimeout(async () => {
        if (!target.value || activeExportId.value === null) return;
        try {
            const exportJob = await loadDriveExport(target.value, activeExportId.value);
            activeExportState.value = exportJob.state;
            exportMessage.value = t('access.drive.exportState', { state: exportJob.state.toLowerCase() });
            if (exportJob.state === 'PENDING' || exportJob.state === 'PROCESSING') scheduleExportPoll();
        } catch { activeExportId.value = null; }
    }, 3000);
}
async function cancelExport(): Promise<void> {
    if (!target.value || activeExportId.value === null) return;
    exporting.value = true;
    try { const exportJob = await cancelDriveExport(target.value, activeExportId.value); activeExportState.value = exportJob.state; exportMessage.value = t('access.drive.exportState', { state: exportJob.state.toLowerCase() }); }
    finally { exporting.value = false; }
}
function downloadExport(): void {
    if (!target.value || activeExportId.value === null || activeExportState.value !== 'READY') return;
    window.location.assign(driveExportDownloadUrl(target.value, activeExportId.value));
}
async function openLocation(location: DriveLocation): Promise<void> {
    if (!target.value || offline.value) return;
    loading.value = true;
    error.value = '';
    try {
        projection.value = await loadDriveLocation(target.value, location);
        selectedIds.value = [];
        clearDetails();
        stale.value = false;
        treeDrawerOpen.value = false;
        await nextTick();
        locationHeading.value?.focus();
    } catch (reason) {
        stale.value = hasData.value;
        error.value = axios.isAxiosError(reason) && reason.response?.status === 403
            ? t('access.drive.locationDenied')
            : t('access.drive.locationFailed');
    } finally { loading.value = false; }
}
async function openCatalogDrive(nextTarget: DriveWorkspaceTarget): Promise<void> {
    if (offline.value) return;
    sharedRootOpen.value = false;
    target.value = nextTarget;
    await refresh();
}
async function openSharedWithMe(): Promise<void> {
    if (offline.value) return;
    loading.value = true;
    error.value = '';
    try {
        sharedWithMe.value = await loadSharedWithMe();
        sharedRootOpen.value = true;
        target.value = null;
        tree.value = [];
        projection.value = { location: { kind: 'root', id: null, displayName: catalog.value?.sharedWithMe.displayName ?? 'Compartilhados comigo', parentFolderId: null }, breadcrumbs: [], folders: [], files: [], capabilities: { read: true, edit: false, trash: false }, usage: { workspaceBytes: 0, systemManagedBytes: 0, trashBytes: 0, totalBytes: 0 } };
        clearSelection();
        clearDetails();
    } catch { error.value = t('access.drive.loadFailed'); }
    finally { loading.value = false; }
}
async function loadCatalog(): Promise<void> {
    if (offline.value) return;
    loading.value = true;
    try {
        catalog.value = await loadDriveCatalog();
        const personal = catalog.value.drives.find((entry) => entry.target.kind === 'personal');
        if (personal) await openCatalogDrive(personal.target);
    } catch { error.value = t('access.drive.loadFailed'); }
    finally { loading.value = false; }
}
async function refresh(): Promise<void> {
    if (!target.value || offline.value) return;
    loading.value = true;
    error.value = '';
    try {
        const [nextTree, nextProjection] = await Promise.all([
            loadDriveTree(target.value),
            loadDriveLocation(target.value, projection.value?.location ?? rootLocation.value),
        ]);
        tree.value = nextTree;
        projection.value = nextProjection;
        selectedIds.value = selectedIds.value.filter((id) => [...nextProjection.folders, ...nextProjection.files].some((item) => item.id === id));
        if (detailsOpen.value) clearDetails();
        stale.value = false;
    } catch (reason) {
        stale.value = hasData.value;
        error.value = axios.isAxiosError(reason) && reason.response?.status === 403
            ? t('access.drive.workspaceDenied')
            : t('access.drive.loadFailed');
    } finally { loading.value = false; }
}
function openItem(item: DriveItem): void {
    if (item.kind === 'folder') void openLocation({ kind: 'folder', id: item.id, displayName: item.displayName, parentFolderId: item.parentFolderId });
    else selectItem(item);
}
function restoreViewMode(): void {
    const value = window.localStorage.getItem(VIEW_MODE_STORAGE_KEY);
    if (value === 'grid' || value === 'list' || value === 'details' || value === 'table') viewMode.value = value;
}
function toggleSecondaryPane(): void {
    secondaryPaneOpen.value = !secondaryPaneOpen.value;
    if (!secondaryPaneOpen.value) secondaryDestination.value = null;
}
function openTransferDialog(): void { if (canTransferSelection.value) { transferError.value = ''; transferDialogOpen.value = true; } }
function setSecondaryDestination(nextTarget: DriveWorkspaceTarget, nextProjection: DriveLocationProjection): void { secondaryDestination.value = { target: nextTarget, projection: nextProjection }; }
function sameTarget(left: DriveWorkspaceTarget, right: DriveWorkspaceTarget): boolean { return left.kind === right.kind && (left.kind !== 'tenant' || right.kind !== 'tenant' || left.tenantId === right.tenantId); }
async function confirmTransfer(mode: DriveTransferMode): Promise<void> {
    if (!target.value || !secondaryDestination.value || !projection.value) return;
    transferring.value = true;
    transferError.value = '';
    try {
        await requestDriveTransfer({ sourceTarget: { ...target.value, folderId: projection.value.location.kind === 'folder' ? projection.value.location.id : null }, destinationTarget: { ...secondaryDestination.value.target, folderId: secondaryDestination.value.projection.location.kind === 'folder' ? secondaryDestination.value.projection.location.id : null }, items: selectedItems.value.map((item) => ({ id: item.id, kind: item.kind })), mode });
        transferDialogOpen.value = false;
        clearSelection();
    } catch { transferError.value = t('access.drive.transferFailed'); }
    finally { transferring.value = false; }
}

onMounted(() => {
    restoreViewMode();
    if (isGlobalSurface.value) secondaryPaneOpen.value = window.localStorage.getItem(SECONDARY_PANE_STORAGE_KEY) === 'open';
    window.addEventListener('online', online);
    window.addEventListener('offline', offlineNow);
    if (isGlobalSurface.value) void loadCatalog();
    else void refresh();
});
watch(viewMode, (value) => {
    try { window.localStorage.setItem(VIEW_MODE_STORAGE_KEY, value); } catch { /* Preferência local é opcional. */ }
});
watch(secondaryPaneOpen, (open) => {
    if (!isGlobalSurface.value) return;
    try { window.localStorage.setItem(SECONDARY_PANE_STORAGE_KEY, open ? 'open' : 'closed'); } catch { /* Preferência local é opcional. */ }
});
watch(target, () => {
    if (exportPollTimer !== null) clearTimeout(exportPollTimer);
    exportPollTimer = null;
    activeExportId.value = null;
    activeExportState.value = null;
    exportMessage.value = '';
    clearSelection();
});
onBeforeUnmount(() => {
    if (exportPollTimer !== null) clearTimeout(exportPollTimer);
    window.removeEventListener('online', online);
    window.removeEventListener('offline', offlineNow);
});
</script>

<template>
    <main class="drive-explorer" :aria-label="workspaceName">
        <header class="drive-explorer__toolbar">
            <div class="drive-explorer__identity"><span class="drive-explorer__mark" aria-hidden="true">◈</span><div><p>{{ t(isWork ? 'access.drive.workWorkspace' : 'access.drive.personalWorkspace') }}</p><h3>{{ workspaceName }}</h3></div></div>
            <div class="drive-explorer__actions"><UiButton variant="secondary" :disabled="offline" :loading="loading" @click="refresh">{{ t('access.drive.refresh') }}</UiButton><UiButton v-if="isGlobalSurface" variant="secondary" :aria-pressed="secondaryPaneOpen" @click="toggleSecondaryPane">{{ t(secondaryPaneOpen ? 'access.drive.closeSecondPanel' : 'access.drive.openSecondPanel') }}</UiButton><UiButton :disabled="!canCreateFolder || offline" @click="openCreateFolder">{{ t('access.drive.newFolder') }}</UiButton><UiButton :disabled="!canUpload" @click="chooseUploads">{{ t('access.drive.upload') }}</UiButton><input ref="uploadInput" type="file" multiple hidden @change="receiveUploadSelection"></div>
        </header>
        <div class="drive-explorer__feedback" aria-live="polite"><UiAlert v-if="offline" tone="warning">{{ t('access.drive.offline') }}</UiAlert><UiAlert v-if="stale" tone="warning">{{ t('access.drive.stale') }}</UiAlert><UiAlert v-if="error" tone="error">{{ error }}</UiAlert></div>
        <div class="drive-explorer__panes" :class="{ 'drive-explorer__panes--split': secondaryPaneOpen }">
        <div class="drive-explorer__layout" :class="{ 'drive-explorer__layout--drawer-open': treeDrawerOpen }">
            <aside class="drive-explorer__tree" :class="{ 'drive-explorer__tree--open': treeDrawerOpen }" aria-label="Árvore de pastas">
                <div class="drive-explorer__tree-heading"><strong>{{ t('access.drive.tree') }}</strong><button type="button" class="drive-explorer__drawer-close" :aria-label="t('access.drive.closeTree')" @click="treeDrawerOpen = false">×</button></div>
                <nav class="drive-explorer__tree-list" aria-label="Pastas do workspace">
                    <button v-for="drive in catalog?.drives ?? []" :key="drive.target.kind === 'tenant' ? `tenant-${drive.target.tenantId}` : 'personal'" type="button" :class="{ 'drive-explorer__tree-item--active': !sharedRootOpen && drive.target.kind === target?.kind && (drive.target.kind !== 'tenant' || target?.kind !== 'tenant' || drive.target.tenantId === target.tenantId) }" class="drive-explorer__tree-item" @click="openCatalogDrive(drive.target)"><span aria-hidden="true">▰</span><span>{{ drive.displayName }}</span></button>
                    <button v-if="catalog" type="button" :class="{ 'drive-explorer__tree-item--active': sharedRootOpen }" class="drive-explorer__tree-item" @click="openSharedWithMe"><span aria-hidden="true">◇</span><span>{{ catalog.sharedWithMe.displayName }}</span></button>
                    <template v-if="!sharedRootOpen">
                        <button v-for="folder in treeFolders" :key="folder.id" type="button" :style="{ '--drive-depth': folder.depth }" :class="{ 'drive-explorer__tree-item--active': isCurrent({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId }) }" class="drive-explorer__tree-item drive-explorer__tree-item--nested" @click="openLocation({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId })"><span aria-hidden="true">▰</span><span>{{ folder.displayName }}</span></button>
                    </template>
                    <button type="button" :class="{ 'drive-explorer__tree-item--active': projection?.location.kind === 'trash' }" class="drive-explorer__tree-item drive-explorer__tree-item--trash" @click="openLocation({ kind: 'trash', id: null, displayName: t('access.drive.trash'), parentFolderId: null })"><span aria-hidden="true">♲</span><span>{{ t('access.drive.trash') }}</span></button>
                </nav>
                <p v-if="projection" class="drive-explorer__usage">{{ t('access.drive.used', { size: formatBytes(projection.usage.totalBytes) }) }}</p>
            </aside>
            <section class="drive-explorer__collection" :aria-busy="loading || undefined" @dragover.prevent @drop.stop.prevent="uploadFiles($event.dataTransfer?.files ?? [])">
                <div class="drive-explorer__collection-header">
                    <button type="button" class="drive-explorer__tree-trigger" :aria-label="t('access.drive.openTree')" @click="treeDrawerOpen = true">☰</button>
                    <nav class="drive-explorer__breadcrumbs" :aria-label="t('access.drive.path')"><button type="button" @click="openLocation(rootLocation)">{{ rootLocation.displayName }}</button><template v-for="crumb in projection?.breadcrumbs ?? []" :key="`${crumb.kind}-${crumb.id}`"><span aria-hidden="true">/</span><button type="button" :aria-current="isCurrent(crumb) ? 'page' : undefined" @click="openLocation(crumb)">{{ crumb.displayName }}</button></template></nav>
                    <span ref="locationHeading" class="drive-explorer__collection-name" tabindex="-1">{{ collectionLabel }}</span>
                </div>
                <div class="drive-explorer__collection-tools"><div class="drive-explorer__view-modes" role="group" :aria-label="t('access.drive.viewMode')"><button v-for="mode in [{ id: 'grid', label: t('access.drive.grid'), glyph: '▦' }, { id: 'list', label: t('access.drive.list'), glyph: '☷' }, { id: 'details', label: t('access.drive.detailsView'), glyph: '☷' }, { id: 'table', label: t('access.drive.table'), glyph: '▤' }]" :key="mode.id" type="button" :class="{ 'drive-explorer__view-mode--active': viewMode === mode.id }" class="drive-explorer__view-mode" :aria-pressed="viewMode === mode.id" :title="mode.label" @click="viewMode = mode.id as ViewMode">{{ mode.glyph }}<span>{{ mode.label }}</span></button></div><div class="drive-explorer__selection-actions"><p v-if="selectedCount" class="drive-explorer__selection" aria-live="polite">{{ t('access.drive.selected', { count: selectedCount }, selectedCount) }}</p><button v-if="selectedCount" type="button" class="drive-explorer__details-trigger" @click="clearSelection">{{ t('access.drive.clearSelection') }}</button><button type="button" class="drive-explorer__details-trigger" :disabled="!canDownloadSelection || offline" @click="downloadSelectedFile">{{ t('access.drive.download') }}</button><template v-if="projection?.location.kind === 'trash'"><button type="button" class="drive-explorer__details-trigger" :disabled="!selectedCount || offline || restoringItems" @click="restoreSelectedItems">{{ t('access.drive.restore') }}</button><button type="button" class="drive-explorer__details-trigger" :disabled="!selectedCount || offline" @click="openReleaseConfirmation">{{ t('access.drive.release') }}</button></template><template v-else><button type="button" class="drive-explorer__details-trigger" :disabled="!canMoveSelection || offline" @click="openMoveDialog">{{ t('access.drive.move') }}</button><button type="button" class="drive-explorer__details-trigger" :disabled="!canTrashSelection || offline" @click="openTrashConfirmation">{{ t('access.drive.moveToTrash') }}</button></template><button type="button" class="drive-explorer__details-trigger" :disabled="selectedItem === null || offline" @click="openDetails">{{ t('access.drive.details') }}</button></div></div>
                <div v-if="selectedCount >= 2 || activeExportId" class="drive-explorer__export-action"><UiButton v-if="selectedCount >= 2" :disabled="offline || exporting" :loading="exporting" @click="openExportDialog">{{ t('access.drive.exportSelection') }}</UiButton><UiButton v-if="activeExportState === 'PENDING'" variant="secondary" :disabled="exporting" @click="cancelExport">{{ t('access.drive.cancelExport') }}</UiButton><UiButton v-if="activeExportState === 'READY'" @click="downloadExport">{{ t('access.drive.download') }}</UiButton><span v-if="exportMessage" aria-live="polite">{{ exportMessage }}</span></div>
                <p v-if="loading && !hasData" class="drive-explorer__loading" aria-live="polite">{{ t('access.drive.loading') }}</p>
                <p v-else-if="!loading && !items.length" class="drive-explorer__empty">{{ t(projection?.location.kind === 'trash' ? 'access.drive.emptyTrash' : 'access.drive.emptyFolder') }}</p>
                <div v-else class="drive-explorer__items" :class="`drive-explorer__items--${viewMode}`" role="list" :aria-label="t('access.drive.itemCollection', { name: collectionLabel })">
                    <button v-for="item in items" :key="`${item.kind}-${item.id}`" type="button" class="drive-explorer__item" :class="{ 'drive-explorer__item--selected': selectedIds.includes(item.id) }" role="listitem" :aria-pressed="selectedIds.includes(item.id)" @click="selectItem(item, $event)" @dblclick="openItem(item)" @keydown.enter.prevent="openItem(item)" @keydown.space.prevent="selectItem(item, $event)"><img class="drive-explorer__item-icon" :class="{ 'drive-explorer__item-icon--folder': item.kind === 'folder' }" :src="itemIconSource(item)" alt="" aria-hidden="true"><span class="drive-explorer__item-name">{{ item.displayName }}</span><span class="drive-explorer__item-type">{{ itemTypeLabel(item) }}</span><span class="drive-explorer__item-size">{{ formatBytes(item.logicalSizeBytes) }}</span><span class="drive-explorer__item-date">{{ formatDate(item.modifiedAt) }}</span></button>
                </div>
                <DriveDetailsPanel :open="detailsOpen" :details="details" :loading="detailsLoading" :error="detailsError" @close="clearDetails" @retry="openDetails" @share="openSharing" />
                <ResourceSharingPanel v-if="sharingFolder && target" :target="target" :folder="sharingFolder" @close="sharingFolder = null" />
                <aside v-if="uploadQueue.length" class="drive-explorer__uploads" aria-live="polite"><div v-for="entry in uploadQueue" :key="entry.id"><span>{{ entry.name }}</span><span>{{ t('access.drive.uploadState', { progress: entry.progress, state: uploadStateLabel(entry.state) }) }}</span><button v-if="entry.state === 'queued' || entry.state === 'uploading'" type="button" @click="cancelUpload(entry)">{{ t('access.drive.cancelUpload') }}</button></div></aside>
            </section>
        </div>
        <DriveNavigationPane v-if="secondaryPaneOpen && catalog" class="drive-explorer__secondary-pane" :catalog="catalog.drives" />
        </div>
        <DriveOperationDialog v-model="createFolderOpen" :title="t('access.drive.newFolder')" :confirm-label="t('access.drive.newFolder')" :cancel-label="t('access.drive.cancel')" :loading="creatingFolder" :error="createFolderError" @submit="createFolder"><label>{{ t('access.drive.folderName') }}<input v-model="createFolderName" maxlength="160" autocomplete="off" /></label></DriveOperationDialog>
        <DriveOperationDialog v-model="trashConfirmationOpen" :title="t('access.drive.trashConfirmationTitle')" :confirm-label="t('access.drive.moveToTrash')" :cancel-label="t('access.drive.cancel')" :loading="trashingItems" :error="trashError" @submit="trashSelectedItems"><p>{{ t('access.drive.trashConfirmationDescription', { count: selectedCount }, selectedCount) }}</p></DriveOperationDialog>
        <DriveOperationDialog v-model="moveDialogOpen" :title="t('access.drive.move')" :confirm-label="t('access.drive.move')" :cancel-label="t('access.drive.cancel')" :loading="movingItem" :error="moveError" @submit="moveSelectedItem"><label>{{ t('access.drive.moveDestination') }}<select v-model="moveDestinationId"><option :value="null">{{ rootLocation.displayName }}</option><option v-for="folder in treeFolders.filter((folder) => canMoveTo(folder))" :key="folder.id" :value="folder.id">{{ '— '.repeat(folder.depth) }}{{ folder.displayName }}</option></select></label></DriveOperationDialog>
        <DriveOperationDialog v-model="releaseConfirmationOpen" :title="t('access.drive.releaseTitle')" :confirm-label="t('access.drive.release')" :cancel-label="t('access.drive.cancel')" :loading="releasingItems" :error="releaseError" @submit="releaseSelectedItems"><p>{{ t('access.drive.releaseDescription', { count: selectedCount }, selectedCount) }}</p></DriveOperationDialog>
        <DriveOperationDialog v-model="exportDialogOpen" :title="t('access.drive.exportTitle')" :confirm-label="t('access.drive.exportSelection')" :cancel-label="t('access.drive.cancel')" :loading="exporting" :error="exportMessage" @submit="requestExport"><p>{{ t('access.drive.exportDescription', { count: selectedCount }, selectedCount) }}</p></DriveOperationDialog>
    </main>
</template>

<style scoped>
.drive-explorer { position: relative; display: grid; min-height: 100%; gap: var(--space-4); color: var(--color-text-secondary); }
.drive-explorer__toolbar, .drive-explorer__identity, .drive-explorer__actions, .drive-explorer__collection-header, .drive-explorer__collection-tools, .drive-explorer__view-modes { display: flex; align-items: center; gap: var(--space-3); }
.drive-explorer__toolbar { justify-content: space-between; flex-wrap: wrap; }
.drive-explorer__panes { display: grid; min-width: 0; min-height: 0; }.drive-explorer__panes--split { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-4); }.drive-explorer__secondary-pane { min-height: 24rem; }
.drive-explorer__identity { min-width: 0; }.drive-explorer__identity p, .drive-explorer__identity h3, .drive-explorer__usage, .drive-explorer__selection, .drive-explorer__empty, .drive-explorer__loading { margin: 0; }
.drive-explorer__identity p { color: var(--color-text-secondary); font-size: var(--font-size-sm); }.drive-explorer__identity h3 { color: var(--color-text-primary); font-family: var(--font-family-display); font-size: var(--font-size-xl); }.drive-explorer__mark { display: grid; width: calc(2.5rem * var(--component-scale)); height: calc(2.5rem * var(--component-scale)); flex: none; place-items: center; border-radius: var(--radius-md); background: var(--color-action-primary); color: var(--color-action-on-primary); font-size: var(--font-size-xl); }
.drive-explorer__actions { flex-wrap: wrap; }.drive-explorer__feedback { display: grid; gap: var(--space-2); }.drive-explorer__layout { display: grid; min-height: min(34rem, 100%); grid-template-columns: minmax(12rem, 15rem) minmax(0, 1fr); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); overflow: hidden; }.drive-explorer__tree { display: grid; min-height: 0; grid-template-rows: auto minmax(0, 1fr) auto; border-right: var(--component-border-width) solid var(--color-border-subtle); background: var(--color-surface-muted); }.drive-explorer__tree-heading { display: flex; align-items: center; justify-content: space-between; padding: var(--space-3); color: var(--color-text-primary); text-transform: uppercase; font-size: var(--font-size-sm); letter-spacing: var(--letter-spacing-wide); }.drive-explorer__tree-list { display: grid; align-content: start; gap: var(--space-1); overflow: auto; padding: 0 var(--space-2) var(--space-3); }.drive-explorer__tree-item { display: flex; min-width: 0; align-items: center; gap: var(--space-2); padding: var(--space-2); border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--color-text-secondary); font: inherit; text-align: left; cursor: pointer; }.drive-explorer__tree-item span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.drive-explorer__tree-item--nested { padding-left: calc(var(--space-2) + var(--drive-depth) * var(--space-3)); }.drive-explorer__tree-item:hover, .drive-explorer__tree-item--active { background: var(--color-surface-raised); color: var(--color-text-primary); }.drive-explorer__tree-item--active { box-shadow: inset .15rem 0 0 var(--color-action-primary); }.drive-explorer__tree-item--trash { margin-top: var(--space-2); }.drive-explorer__usage { padding: var(--space-3); border-top: var(--component-border-width) solid var(--color-border-subtle); font-size: var(--font-size-sm); }.drive-explorer__collection { display: grid; min-width: 0; min-height: 0; grid-template-rows: auto auto minmax(0, 1fr); }.drive-explorer__collection-header { min-width: 0; padding: var(--space-3) var(--space-4); border-bottom: var(--component-border-width) solid var(--color-border-subtle); }.drive-explorer__breadcrumbs { display: flex; min-width: 0; gap: var(--space-2); overflow: auto; }.drive-explorer__breadcrumbs button { flex: none; padding: 0; border: 0; background: transparent; color: var(--color-action-primary); font: inherit; cursor: pointer; }.drive-explorer__breadcrumbs span { color: var(--color-text-secondary); }.drive-explorer__collection-name { margin-left: auto; overflow: hidden; color: var(--color-text-primary); font-weight: var(--font-weight-semibold); text-overflow: ellipsis; white-space: nowrap; }.drive-explorer__collection-tools { justify-content: space-between; padding: var(--space-3) var(--space-4); }.drive-explorer__view-mode, .drive-explorer__tree-trigger, .drive-explorer__drawer-close { display: inline-flex; align-items: center; justify-content: center; gap: var(--space-1); min-height: var(--control-height-sm); padding: 0 var(--space-2); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-sm); background: var(--color-surface-raised); color: var(--color-text-secondary); font: inherit; cursor: pointer; }.drive-explorer__view-mode--active { border-color: var(--color-action-primary); color: var(--color-action-primary); }.drive-explorer__tree-trigger, .drive-explorer__drawer-close { display: none; }.drive-explorer__items { min-height: 0; overflow: auto; padding: var(--space-4); }.drive-explorer__items--grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr)); align-content: start; gap: var(--space-3); }.drive-explorer__item { display: grid; min-width: 0; gap: var(--space-2); padding: var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface-raised); color: var(--color-text-secondary); font: inherit; text-align: left; cursor: pointer; }.drive-explorer__item:hover, .drive-explorer__item--selected { border-color: var(--color-action-primary); background: var(--color-surface-muted); }.drive-explorer__item-icon { display: grid; width: calc(2.25rem * var(--component-scale)); height: calc(2.25rem * var(--component-scale)); place-items: center; border-radius: var(--radius-sm); background: var(--color-surface-muted); color: var(--color-text-primary); font-size: var(--font-size-xs); font-weight: var(--font-weight-bold); }.drive-explorer__item-icon--folder { color: var(--color-action-primary); font-size: var(--font-size-xl); }.drive-explorer__item-name { overflow: hidden; color: var(--color-text-primary); font-weight: var(--font-weight-semibold); text-overflow: ellipsis; white-space: nowrap; }.drive-explorer__item-type, .drive-explorer__item-size, .drive-explorer__item-date { color: var(--color-text-secondary); font-size: var(--font-size-xs); }.drive-explorer__items--list, .drive-explorer__items--table { display: grid; align-content: start; gap: var(--space-1); }.drive-explorer__items--list .drive-explorer__item, .drive-explorer__items--table .drive-explorer__item { grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; padding: var(--space-2) var(--space-3); }.drive-explorer__items--list .drive-explorer__item-type, .drive-explorer__items--list .drive-explorer__item-date { display: none; }.drive-explorer__items--table .drive-explorer__item { grid-template-columns: auto minmax(10rem, 1fr) minmax(5rem, .5fr) minmax(4rem, .3fr) minmax(8rem, .6fr); }.drive-explorer__empty, .drive-explorer__loading { display: grid; place-items: center; min-height: 12rem; padding: var(--space-4); text-align: center; }.drive-explorer__selection { color: var(--color-action-primary); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); }
@media (max-width: 900px) { .drive-explorer__panes--split { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 700px) { .drive-explorer__layout { display: block; min-height: 26rem; overflow: visible; border: 0; }.drive-explorer__tree { position: fixed; z-index: 30; inset: 2.5vh 2.5vw; display: none; border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); box-shadow: var(--component-workspace-window-shadow); }.drive-explorer__tree--open { display: grid; }.drive-explorer__tree-heading { padding: var(--space-4); }.drive-explorer__tree-trigger, .drive-explorer__drawer-close { display: inline-flex; }.drive-explorer__collection { min-height: 26rem; border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); overflow: hidden; }.drive-explorer__collection-name { display: none; }.drive-explorer__view-mode span { display: none; }.drive-explorer__items { padding: var(--space-3); }.drive-explorer__items--grid { grid-template-columns: repeat(auto-fill, minmax(7rem, 1fr)); gap: var(--space-2); }.drive-explorer__items--table .drive-explorer__item { grid-template-columns: auto minmax(0, 1fr) auto; }.drive-explorer__items--table .drive-explorer__item-type, .drive-explorer__items--table .drive-explorer__item-date { display: none; }.drive-explorer__actions { width: 100%; }.drive-explorer__actions .ui-button { flex: 1 1 auto; } }
.drive-explorer__items--details { display: grid; align-content: start; gap: var(--space-1); }
.drive-explorer__items--details .drive-explorer__item { grid-template-columns: auto minmax(10rem, 1fr) minmax(5rem, .5fr) minmax(4rem, .3fr) minmax(8rem, .6fr); align-items: center; padding: var(--space-2) var(--space-3); }
@media (max-width: 700px) { .drive-explorer__items--details .drive-explorer__item { grid-template-columns: auto minmax(0, 1fr) auto; }.drive-explorer__items--details .drive-explorer__item-type, .drive-explorer__items--details .drive-explorer__item-date { display: none; } }
.drive-explorer__collection { position: relative; }
.drive-explorer__selection-actions { display: flex; align-items: center; gap: var(--space-3); }
.drive-explorer__export-action { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3); padding: 0 var(--space-4) var(--space-3); color: var(--color-text-secondary); font-size: var(--font-size-sm); }
.drive-explorer__details-trigger { display: inline-flex; align-items: center; justify-content: center; min-height: var(--control-height-sm); padding: 0 var(--space-2); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-sm); background: var(--color-surface-raised); color: var(--color-text-secondary); font: inherit; cursor: pointer; }
.drive-explorer__details-trigger:not(:disabled):hover { border-color: var(--color-action-primary); color: var(--color-action-primary); }
.drive-explorer__details-trigger:disabled { cursor: not-allowed; opacity: .55; }
</style>
