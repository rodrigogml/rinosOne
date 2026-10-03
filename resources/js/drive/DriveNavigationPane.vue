<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import UiAlert from '../design-system/UiAlert.vue';
import IconButton from '../design-system/IconButton.vue';
import { rasterIconSource } from '../design-system/rasterIconAssets';
import { toast } from '../design-system/toast/toastService';
import DriveDetailsPanel from './DriveDetailsPanel.vue';
import DriveOperationDialog from './DriveOperationDialog.vue';
import ResourceSharingPanel from '../authorization/ResourceSharingPanel.vue';
import { createDriveFolder, driveDownloadUrl, driveExportDownloadUrl, loadDriveDetails, loadDriveExport, loadDriveLocation, loadDriveTree, moveDriveItem, releaseDriveItems, requestDriveExport, restoreDriveItems, trashDriveItems, uploadDriveFile, type DriveCatalogEntry, type DriveDetailsProjection, type DriveItem, type DriveLocation, type DriveLocationProjection, type DriveWorkspaceTarget } from './driveWorkspaceApi';

type PanelContext = { target: DriveWorkspaceTarget; projection: DriveLocationProjection; items: DriveItem[] };
type UploadEntry = { id: number; name: string; state: 'uploading' | 'complete' | 'failed'; progress: number; controller: AbortController | null };
type SessionExport = { exportId: string; target: DriveWorkspaceTarget; state: string; expiresAt: string | null; createdAt: string };

const props = defineProps<{ catalog: DriveCatalogEntry[]; offline: boolean; sessionExports: SessionExport[]; viewMode: ViewMode }>();
const emit = defineEmits<{ locationChange: [target: DriveWorkspaceTarget, projection: DriveLocationProjection]; transferDrop: []; transferStart: [context: PanelContext]; transferToPrimary: [context: PanelContext]; downloadExports: [entries: SessionExport[]]; exportUpdated: [entry: SessionExport]; mutated: []; viewModeChange: [mode: ViewMode]; close: [] }>();
const { t, locale } = useI18n();

type ViewMode = 'grid' | 'list' | 'details';
type TreeFolder = DriveItem & { depth: number; hasChildren: boolean };
const target = ref<DriveWorkspaceTarget | null>(null);
const tree = ref<DriveItem[]>([]);
const projection = ref<DriveLocationProjection | null>(null);
const selectedIds = ref<number[]>([]);
const selectedExportIds = ref<string[]>([]);
const exportSelectionAnchorId = ref<string | null>(null);
const exportsRootOpen = ref(false);
const selectionAnchorId = ref<number | null>(null);
const expandedFolderIds = ref(new Set<number>());
const expandedDriveKeys = ref(new Set<string>());
const loading = ref(false);
const error = ref('');
const viewMode = computed(() => props.viewMode);
const treeVisible = ref(true);
const bodyElement = ref<HTMLElement | null>(null);
const treeWidth = ref<number | null>(null);
const createFolderOpen = ref(false);
const createFolderName = ref('');
const createFolderError = ref('');
const creatingFolder = ref(false);
const trashConfirmationOpen = ref(false);
const trashError = ref('');
const trashingItems = ref(false);
const releaseConfirmationOpen = ref(false);
const releaseError = ref('');
const releasingItems = ref(false);
const restoringItems = ref(false);
const moveDialogOpen = ref(false);
const moveDestinationId = ref<number | null>(null);
const moveError = ref('');
const movingItem = ref(false);
const detailsOpen = ref(false);
const detailsLoading = ref(false);
const detailsError = ref('');
const details = ref<DriveDetailsProjection | null>(null);
const sharingFolder = ref<DriveItem | null>(null);
const uploadInput = ref<HTMLInputElement | null>(null);
const uploadQueue = ref<UploadEntry[]>([]);
const exporting = ref(false);
const exportMessage = ref('');
const activeExportId = ref<string | null>(null);
const activeExportState = ref<string | null>(null);
let nextUploadId = 1;
let exportPollTimer: ReturnType<typeof setTimeout> | null = null;

const selectedDrive = computed(() => props.catalog.find((entry) => sameTarget(entry.target, target.value)) ?? null);
const root = computed<DriveLocation>(() => ({ kind: 'root', id: null, displayName: selectedDrive.value?.displayName ?? t('access.drive.root'), parentFolderId: null }));
const items = computed(() => projection.value ? [...projection.value.folders, ...projection.value.files] : []);
const selectedCount = computed(() => selectedIds.value.length);
const selectedItem = computed(() => selectedCount.value === 1 ? items.value.find((item) => item.id === selectedIds.value[0]) ?? null : null);
const canCreateFolder = computed(() => !props.offline && target.value !== null && projection.value?.location.kind !== 'trash' && projection.value?.capabilities.edit === true);
const canUpload = computed(() => canCreateFolder.value);
const canDownloadSelection = computed(() => !props.offline && (exportsRootOpen.value
    ? selectedExports.value.length > 0 && selectedExports.value.every((entry) => entry.state === 'READY')
    : selectedItems.value.length > 0 && selectedItems.value.every((item) => item.capabilities.read)));
const canTrashSelection = computed(() => !props.offline && projection.value?.location.kind !== 'trash' && selectedItems.value.length > 0 && selectedItems.value.every((item) => item.capabilities.trash));
const canInspectSelection = computed(() => !props.offline && selectedItem.value?.capabilities.read === true);
const itemCounts = computed(() => ({ total: items.value.length, files: items.value.filter((item) => item.kind === 'file').length, folders: items.value.filter((item) => item.kind === 'folder').length }));
const selectedItems = computed(() => items.value.filter((item) => selectedIds.value.includes(item.id)));
const selectedExports = computed(() => props.sessionExports.filter((entry) => selectedExportIds.value.includes(entry.exportId)));
const isTrashLocation = computed(() => !exportsRootOpen.value && projection.value?.location.kind === 'trash');
const selectedItemCounts = computed(() => ({ total: selectedItems.value.length, files: selectedItems.value.filter((item) => item.kind === 'file').length, folders: selectedItems.value.filter((item) => item.kind === 'folder').length }));
const selectedLogicalSizeBytes = computed(() => selectedItems.value.reduce((total, item) => total + (item.logicalSizeBytes ?? 0), 0));
const statusSummary = computed(() => t('access.drive.statusSummary', itemCounts.value));
const selectionSummary = computed(() => selectedCount.value ? t('access.drive.statusSelectionSummary', { ...selectedItemCounts.value, size: formatBytes(selectedLogicalSizeBytes.value) }) : '');
const context = computed<PanelContext | null>(() => target.value && projection.value ? { target: target.value, projection: projection.value, items: selectedItems.value } : null);
const treeFolders = computed<TreeFolder[]>(() => {
    const byParent = new Map<number | null, DriveItem[]>();
    for (const folder of tree.value) {
        const siblings = byParent.get(folder.parentFolderId) ?? [];
        siblings.push(folder);
        byParent.set(folder.parentFolderId, siblings);
    }
    const ordered: TreeFolder[] = [];
    const visit = (parentId: number | null, depth: number): void => {
        for (const folder of byParent.get(parentId) ?? []) {
            const hasChildren = (byParent.get(folder.id)?.length ?? 0) > 0;
            ordered.push({ ...folder, depth, hasChildren });
            if (hasChildren && expandedFolderIds.value.has(folder.id)) visit(folder.id, depth + 1);
        }
    };
    visit(null, 0);
    return ordered;
});

function sameTarget(left: DriveWorkspaceTarget, right: DriveWorkspaceTarget | null): boolean {
    return right !== null && left.kind === right.kind && (left.kind !== 'tenant' || right.kind !== 'tenant' || left.tenantId === right.tenantId);
}
function targetKey(nextTarget: DriveWorkspaceTarget): string { return nextTarget.kind === 'tenant' ? `tenant:${nextTarget.tenantId}` : 'personal'; }
function isActiveDrive(nextTarget: DriveWorkspaceTarget): boolean { return sameTarget(nextTarget, target.value); }
function driveExpanded(nextTarget: DriveWorkspaceTarget): boolean { return expandedDriveKeys.value.has(targetKey(nextTarget)); }
function itemIcon(item: DriveItem): string { return rasterIconSource(item.kind === 'folder' ? 'folderClose' : 'file', 'md') ?? ''; }
function folderIcon(folder: TreeFolder): string { return rasterIconSource(folder.hasChildren && expandedFolderIds.value.has(folder.id) ? 'folderOpen' : 'folderClose', 'sm') ?? ''; }
function trashIcon(): string { return rasterIconSource((projection.value?.usage.trashBytes ?? 0) > 0 ? 'trashFull' : 'trashEmpty', 'sm') ?? ''; }
function itemTypeLabel(item: DriveItem): string { return item.kind === 'folder' ? t('access.drive.folder') : item.detectedMimeType?.split('/').at(-1)?.toUpperCase() ?? t('access.drive.file'); }
function formatBytes(bytes: number | null): string {
    if (bytes === null || bytes === 0) return bytes === 0 ? '0 B' : '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** index).toLocaleString(locale.value, { maximumFractionDigits: 1 })} ${units[index]}`;
}
function formatDate(value: string): string { return new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)); }
function setViewMode(mode: ViewMode): void { emit('viewModeChange', mode); }
async function load(location: DriveLocation = root.value): Promise<void> {
    if (!target.value) return;
    loading.value = true;
    error.value = '';
    try {
        const [nextTree, nextProjection] = await Promise.all([loadDriveTree(target.value), loadDriveLocation(target.value, location)]);
        tree.value = nextTree;
        projection.value = nextProjection;
        selectedIds.value = [];
        emit('locationChange', target.value, nextProjection);
    } catch { error.value = t('access.drive.loadFailed'); }
    finally { loading.value = false; }
}
async function toggleDrive(nextTarget: DriveWorkspaceTarget): Promise<void> {
    if (isActiveDrive(nextTarget)) {
        const next = new Set(expandedDriveKeys.value);
        if (next.has(targetKey(nextTarget))) next.delete(targetKey(nextTarget)); else next.add(targetKey(nextTarget));
        expandedDriveKeys.value = next;
        return;
    }
    exportsRootOpen.value = false;
    target.value = nextTarget;
    expandedFolderIds.value = new Set();
    expandedDriveKeys.value = new Set(expandedDriveKeys.value).add(targetKey(nextTarget));
    tree.value = [];
    projection.value = null;
    await load();
}
function toggleDriveTree(nextTarget: DriveWorkspaceTarget): void {
    const next = new Set(expandedDriveKeys.value);
    const key = targetKey(nextTarget);
    if (next.has(key)) next.delete(key); else next.add(key);
    expandedDriveKeys.value = next;
}
function openDriveRoot(nextTarget: DriveWorkspaceTarget): void {
    if (!isActiveDrive(nextTarget)) {
        void toggleDrive(nextTarget);
        return;
    }
    exportsRootOpen.value = false;
    expandedDriveKeys.value = new Set(expandedDriveKeys.value).add(targetKey(nextTarget));
    void load(root.value);
}
function exportStateLabel(state: string): string {
    const key = state === 'FAILED' ? 'exportFailedStatus' : `export${state.charAt(0)}${state.slice(1).toLowerCase()}`;
    return t(`access.drive.${key}`);
}
function openLocation(location: DriveLocation): void { exportsRootOpen.value = false; void load(location); }
function openItem(item: DriveItem): void { if (item.kind === 'folder') openLocation({ kind: 'folder', id: item.id, displayName: item.displayName, parentFolderId: item.parentFolderId }); }
function toggleFolder(folder: TreeFolder): void {
    if (!folder.hasChildren) return;
    const next = new Set(expandedFolderIds.value);
    if (next.has(folder.id)) next.delete(folder.id); else next.add(folder.id);
    expandedFolderIds.value = next;
}
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
function clearSelection(): void { selectedIds.value = []; selectedExportIds.value = []; selectionAnchorId.value = null; exportSelectionAnchorId.value = null; }
function clampTreeWidth(value: number): number {
    const width = bodyElement.value?.getBoundingClientRect().width ?? 0;
    return Math.min(Math.max(value, 112), Math.max(112, Math.floor(width * .25)));
}
function resizeTreeAt(clientX: number): void { const bounds = bodyElement.value?.getBoundingClientRect(); if (bounds) treeWidth.value = clampTreeWidth(clientX - bounds.left); }
function onTreeResize(event: PointerEvent): void { resizeTreeAt(event.clientX); }
function stopTreeResize(): void { window.removeEventListener('pointermove', onTreeResize); window.removeEventListener('pointerup', stopTreeResize); }
function startTreeResize(event: PointerEvent): void {
    if (window.matchMedia('(max-width: 900px)').matches) return;
    event.preventDefault(); resizeTreeAt(event.clientX);
    window.addEventListener('pointermove', onTreeResize); window.addEventListener('pointerup', stopTreeResize, { once: true });
}
function resizeTreeBy(offset: number): void {
    const width = bodyElement.value?.getBoundingClientRect().width ?? 0;
    treeWidth.value = clampTreeWidth((treeWidth.value ?? Math.min(160, Math.floor(width * .25))) + offset);
}
function openCreateFolder(): void { if (canCreateFolder.value) { createFolderName.value = ''; createFolderError.value = ''; createFolderOpen.value = true; } }
async function createFolder(): Promise<void> {
    const displayName = createFolderName.value.trim();
    if (!target.value || !projection.value || !displayName) { createFolderError.value = t('access.drive.folderNameRequired'); return; }
    creatingFolder.value = true;
    createFolderError.value = '';
    try {
        await createDriveFolder(target.value, displayName, projection.value.location.kind === 'folder' ? projection.value.location.id : null);
        createFolderOpen.value = false;
        await load();
        emit('mutated');
    } catch (reason) { createFolderError.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.createFolderDenied') : t('access.drive.createFolderFailed'); }
    finally { creatingFolder.value = false; }
}
function chooseUploads(): void { if (canUpload.value) uploadInput.value?.click(); }
async function uploadFiles(files: FileList | File[]): Promise<void> {
    if (!target.value || !projection.value || !canUpload.value) return;
    const filesToUpload = Array.from(files);
    const entries: UploadEntry[] = filesToUpload.map((file) => ({ id: nextUploadId++, name: file.name, state: 'uploading', progress: 0, controller: new AbortController() }));
    uploadQueue.value.push(...entries);
    for (const [index, entry] of entries.entries()) {
        const controller = entry.controller;
        if (!controller) continue;
        try {
            await uploadDriveFile(target.value, filesToUpload[index], projection.value.location.kind === 'folder' ? projection.value.location.id : null, controller.signal, (progress) => { entry.progress = progress; });
            entry.progress = 100;
            entry.state = 'complete';
        } catch { entry.state = 'failed'; }
        finally { entry.controller = null; }
    }
    await load();
    emit('mutated');
}
function receiveUploadSelection(event: Event): void { const input = event.target as HTMLInputElement; if (input.files) void uploadFiles(input.files); input.value = ''; }
function dismissUpload(id: number): void { uploadQueue.value = uploadQueue.value.filter((entry) => entry.id !== id); }
function cancelUpload(entry: UploadEntry): void { entry.controller?.abort(); }
async function downloadSelectedItems(): Promise<void> {
    if (exportsRootOpen.value) { if (selectedExports.value.length) emit('downloadExports', selectedExports.value); return; }
    if (!target.value || !canDownloadSelection.value) return;
    if (selectedItems.value.length === 1 && selectedItems.value[0].kind === 'file') { window.location.assign(driveDownloadUrl(target.value, selectedItems.value[0].id)); return; }
    exporting.value = true;
    exportMessage.value = '';
    try {
        const job = await requestDriveExport(target.value, selectedItems.value);
        activeExportId.value = job.exportId;
        activeExportState.value = job.state;
        emit('exportUpdated', { exportId: job.exportId, target: target.value, state: job.state, expiresAt: job.expiresAt, createdAt: new Date().toISOString() });
        exportMessage.value = t('access.drive.exportState', { state: job.state.toLowerCase() });
        toast.info('O conteúdo está sendo compactado para download. Você será avisado quando o download começar.');
        scheduleExportPoll();
    }
    catch { exportMessage.value = t('access.drive.exportFailed'); }
    finally { exporting.value = false; }
}
function openTrashConfirmation(): void { if (canTrashSelection.value) { trashError.value = ''; trashConfirmationOpen.value = true; } }
async function trashSelectedItems(): Promise<void> {
    if (!target.value || !canTrashSelection.value) return;
    trashingItems.value = true;
    trashError.value = '';
    try { await trashDriveItems(target.value, selectedItems.value); trashConfirmationOpen.value = false; clearSelection(); await load(); emit('mutated'); }
    catch (reason) { trashError.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.trashDenied') : t('access.drive.trashFailed'); }
    finally { trashingItems.value = false; }
}
async function restoreSelectedItems(): Promise<void> {
    if (!target.value || !selectedItems.value.length || props.offline) return;
    restoringItems.value = true;
    try { await restoreDriveItems(target.value, selectedItems.value); clearSelection(); await load(); emit('mutated'); }
    catch (reason) { error.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.restoreDenied') : t('access.drive.restoreFailed'); }
    finally { restoringItems.value = false; }
}
function openReleaseConfirmation(): void { if (selectedItems.value.length && !props.offline) { releaseError.value = ''; releaseConfirmationOpen.value = true; } }
async function releaseSelectedItems(): Promise<void> {
    if (!target.value || !selectedItems.value.length || props.offline) return;
    releasingItems.value = true;
    try { await releaseDriveItems(target.value, selectedItems.value); releaseConfirmationOpen.value = false; clearSelection(); await load(); emit('mutated'); }
    catch (reason) { releaseError.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.releaseDenied') : t('access.drive.releaseFailed'); }
    finally { releasingItems.value = false; }
}
function canMoveTo(folder: DriveItem | null): boolean { return selectedItem.value?.kind !== 'folder' || folder?.id !== selectedItem.value.id; }
function openMoveDialog(): void { if (selectedItem.value && !props.offline) { moveDestinationId.value = null; moveError.value = ''; moveDialogOpen.value = true; } }
async function moveSelectedItem(): Promise<void> {
    if (!target.value || !selectedItem.value || props.offline) return;
    movingItem.value = true;
    moveError.value = '';
    try { await moveDriveItem(target.value, selectedItem.value, moveDestinationId.value); moveDialogOpen.value = false; clearSelection(); await load(); emit('mutated'); }
    catch (reason) { moveError.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.moveDenied') : t('access.drive.moveFailed'); }
    finally { movingItem.value = false; }
}
async function openDetails(): Promise<void> {
    if (!target.value || !selectedItem.value || !canInspectSelection.value) return;
    detailsOpen.value = true;
    detailsLoading.value = true;
    detailsError.value = '';
    details.value = null;
    try { details.value = await loadDriveDetails(target.value, selectedItem.value); }
    catch (reason) { detailsError.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.detailsDenied') : t('access.drive.detailsFailed'); }
    finally { detailsLoading.value = false; }
}
function openSharing(): void { if (details.value?.item.kind === 'folder') sharingFolder.value = details.value.item; }
function beginTransfer(): void { if (context.value && context.value.items.length && !props.offline) emit('transferToPrimary', context.value); }
function rememberTransferSource(): void { if (context.value && context.value.items.length && !props.offline) emit('transferStart', context.value); }
function openExports(): void { expandedDriveKeys.value = new Set(); exportsRootOpen.value = true; clearSelection(); }
function selectExport(entry: SessionExport, event?: MouseEvent): void {
    if (event?.shiftKey && exportSelectionAnchorId.value !== null) {
        const from = props.sessionExports.findIndex((candidate) => candidate.exportId === exportSelectionAnchorId.value);
        const to = props.sessionExports.findIndex((candidate) => candidate.exportId === entry.exportId);
        if (from >= 0 && to >= 0) selectedExportIds.value = props.sessionExports.slice(Math.min(from, to), Math.max(from, to) + 1).map((candidate) => candidate.exportId);
        return;
    }
    if (event?.ctrlKey || event?.metaKey) selectedExportIds.value = selectedExportIds.value.includes(entry.exportId) ? selectedExportIds.value.filter((id) => id !== entry.exportId) : [...selectedExportIds.value, entry.exportId];
    else selectedExportIds.value = [entry.exportId];
    exportSelectionAnchorId.value = entry.exportId;
}
function clearExportPoll(): void { if (exportPollTimer !== null) clearTimeout(exportPollTimer); exportPollTimer = null; }
function scheduleExportPoll(): void {
    clearExportPoll();
    if (!activeExportId.value || ['READY', 'FAILED', 'CANCELLED', 'EXPIRED'].includes(activeExportState.value ?? '')) return;
    exportPollTimer = setTimeout(() => { void pollExport(); }, 1000);
}
async function pollExport(): Promise<void> {
    if (!activeExportId.value || !target.value) return;
    try {
        const job = await loadDriveExport(target.value, activeExportId.value);
        activeExportState.value = job.state;
        emit('exportUpdated', { exportId: job.exportId, target: target.value, state: job.state, expiresAt: job.expiresAt, createdAt: new Date().toISOString() });
        exportMessage.value = t('access.drive.exportState', { state: job.state.toLowerCase() });
        if (job.state === 'READY') { toast.success('A compactação foi concluída. O download será iniciado agora.'); downloadExport(); }
    } catch { exportMessage.value = t('access.drive.exportFailed'); activeExportState.value = 'FAILED'; }
    finally { scheduleExportPoll(); }
}
function downloadExport(): void { if (target.value && activeExportId.value && activeExportState.value === 'READY') window.location.assign(driveExportDownloadUrl(target.value, activeExportId.value)); }

defineExpose({ refresh: (): Promise<void> => load(projection.value?.location ?? root.value), toggleTree: (): void => { treeVisible.value = !treeVisible.value; } });
watch(() => props.catalog, (entries) => { if (!target.value && entries.length) void toggleDrive(entries[0].target); }, { immediate: true });
onMounted(() => { if (!target.value && props.catalog.length) void toggleDrive(props.catalog[0].target); });
onBeforeUnmount(() => { stopTreeResize(); clearExportPoll(); });
</script>

<template>
    <section class="drive-navigation-pane" :aria-busy="loading || undefined" aria-label="Painel secundário do Rinos Drive" @dragover.prevent @drop.prevent="emit('transferDrop')">
        <div ref="bodyElement" class="drive-navigation-pane__layout" :class="{ 'drive-navigation-pane__layout--tree-hidden': !treeVisible }" :style="treeWidth === null ? undefined : { '--drive-secondary-tree-width': `${treeWidth}px` }">
            <aside v-if="treeVisible" class="drive-navigation-pane__tree" :aria-label="t('access.drive.tree')">
                <header class="drive-navigation-pane__tree-heading"><span>{{ t('access.drive.tree') }}</span><button type="button" class="drive-navigation-pane__mobile-close" :aria-label="t('access.drive.closeSecondPanel')" :title="t('access.drive.closeSecondPanel')" @click="emit('close')">×</button></header>
                <nav class="drive-navigation-pane__tree-list" role="tree" :aria-label="t('access.drive.tree')">
                    <section v-for="drive in catalog" :key="targetKey(drive.target)" class="drive-navigation-pane__drive-section" :class="{ 'drive-navigation-pane__drive-section--active': isActiveDrive(drive.target) }">
                        <div class="drive-navigation-pane__drive-control"><button type="button" class="drive-navigation-pane__drive-toggle" :aria-label="driveExpanded(drive.target) ? 'Recolher drive' : 'Expandir drive'" :aria-expanded="driveExpanded(drive.target)" @click="toggleDriveTree(drive.target)"><img :src="rasterIconSource('drive', 'sm') ?? ''" alt="" aria-hidden="true"></button><button type="button" role="treeitem" class="drive-navigation-pane__drive-header" :aria-current="isActiveDrive(drive.target) ? 'page' : undefined" @click="openDriveRoot(drive.target)"><span>{{ drive.displayName }}</span></button></div>
                        <div v-if="isActiveDrive(drive.target) && driveExpanded(drive.target)" class="drive-navigation-pane__drive-content">
                            <div v-for="folder in treeFolders" :key="folder.id" class="drive-navigation-pane__tree-row" :style="{ '--drive-depth': folder.depth }"><button v-if="folder.hasChildren" type="button" class="drive-navigation-pane__tree-toggle" :aria-expanded="expandedFolderIds.has(folder.id)" @click="toggleFolder(folder)"><img :src="folderIcon(folder)" alt="" aria-hidden="true"></button><span v-else class="drive-navigation-pane__tree-toggle drive-navigation-pane__tree-toggle--empty"><img :src="folderIcon(folder)" alt="" aria-hidden="true"></span><button type="button" class="drive-navigation-pane__tree-item" :class="{ 'drive-navigation-pane__tree-item--active': projection?.location.kind === 'folder' && projection.location.id === folder.id }" @click="openLocation({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId })"><span>{{ folder.displayName }}</span></button></div>
                            <button type="button" class="drive-navigation-pane__tree-item drive-navigation-pane__tree-item--trash" :class="{ 'drive-navigation-pane__tree-item--active': isTrashLocation }" @click="openLocation({ kind: 'trash', id: null, displayName: t('access.drive.trash'), parentFolderId: null })"><img :src="trashIcon()" alt="" aria-hidden="true"><span>{{ t('access.drive.trash') }}</span></button>
                        </div>
                    </section>
                    <button v-if="sessionExports.length" type="button" role="treeitem" class="drive-navigation-pane__tree-item" :class="{ 'drive-navigation-pane__tree-item--active': exportsRootOpen }" :aria-current="exportsRootOpen ? 'page' : undefined" @click="openExports"><img :src="rasterIconSource('fileZipExport', 'sm') ?? ''" alt=""><span>Exportações</span></button>
                </nav>
                <p v-if="projection" class="drive-navigation-pane__usage" :title="t('access.drive.used', { size: formatBytes(projection.usage.totalBytes) })">{{ t('access.drive.used', { size: formatBytes(projection.usage.totalBytes) }) }}</p>
            </aside>
            <div v-if="treeVisible" class="drive-navigation-pane__tree-divider" :aria-label="t('access.drive.resizeTree')" tabindex="0" @pointerdown="startTreeResize" @keydown.arrow-left.prevent="resizeTreeBy(-20)" @keydown.arrow-right.prevent="resizeTreeBy(20)" @keydown.home.prevent="resizeTreeBy(-Number.MAX_SAFE_INTEGER)" @keydown.end.prevent="resizeTreeBy(Number.MAX_SAFE_INTEGER)" />
            <section class="drive-navigation-pane__collection">
                <header class="drive-navigation-pane__collection-header"><nav class="drive-navigation-pane__breadcrumbs" :aria-label="t('access.drive.path')"><button type="button" @click="exportsRootOpen ? (exportsRootOpen = false) : openLocation(root)">{{ exportsRootOpen ? 'Exportações' : root.displayName }}</button><template v-if="!exportsRootOpen"><template v-for="crumb in projection?.breadcrumbs ?? []" :key="`${crumb.kind}-${crumb.id}`"><span aria-hidden="true">/</span><button type="button" @click="openLocation(crumb)">{{ crumb.displayName }}</button></template></template></nav></header>
                <div class="drive-navigation-pane__tools">
                    <div class="drive-navigation-pane__actions" role="toolbar" :aria-label="t('access.drive.actions')">
                        <IconButton v-if="selectedCount || selectedExportIds.length" :label="t('access.drive.clearSelection')" @click="clearSelection"><img :src="rasterIconSource('fileCleanSelection', 'md') ?? ''" alt=""></IconButton>
                        <IconButton :label="t('access.drive.refresh')" :disabled="loading || offline" @click="load()"><img :src="rasterIconSource('fileRefresh', 'md') ?? ''" alt=""></IconButton>
                        <IconButton :label="t('access.drive.newFolder')" :disabled="exportsRootOpen || !canCreateFolder" @click="openCreateFolder"><img :src="rasterIconSource('fileNewFolder', 'md') ?? ''" alt=""></IconButton>
                        <IconButton class="drive-navigation-pane__action--overflowable" :label="t('access.drive.upload')" :disabled="exportsRootOpen || !canUpload" @click="chooseUploads"><img :src="rasterIconSource('driveUpload', 'md') ?? ''" alt=""></IconButton>
                        <IconButton class="drive-navigation-pane__action--overflowable" :label="t('access.drive.download')" :disabled="!canDownloadSelection || exporting" @click="downloadSelectedItems"><img :src="rasterIconSource('driveDownload', 'md') ?? ''" alt=""></IconButton>
                        <template v-if="isTrashLocation"><IconButton class="drive-navigation-pane__action--overflowable" :label="t('access.drive.restore')" :disabled="!selectedCount || offline || restoringItems" @click="restoreSelectedItems"><img :src="rasterIconSource('fileMove', 'md') ?? ''" alt=""></IconButton><IconButton class="drive-navigation-pane__action--overflowable" :label="t('access.drive.release')" :disabled="!selectedCount || offline || releasingItems" @click="openReleaseConfirmation"><img :src="rasterIconSource('trashBurn', 'md') ?? ''" alt=""></IconButton></template>
                        <template v-else><IconButton class="drive-navigation-pane__action--overflowable" :label="t('access.drive.move')" :disabled="!selectedItem || offline" @click="openMoveDialog"><img :src="rasterIconSource('fileMove', 'md') ?? ''" alt=""></IconButton><IconButton class="drive-navigation-pane__action--overflowable" :label="t('access.drive.moveToTrash')" :disabled="!canTrashSelection" @click="openTrashConfirmation"><img :src="rasterIconSource('fileDelete', 'md') ?? ''" alt=""></IconButton></template>
                        <IconButton class="drive-navigation-pane__action--overflowable" :label="t('access.drive.details')" :disabled="!canInspectSelection" @click="openDetails"><img :src="rasterIconSource('fileDetailPanel', 'md') ?? ''" alt=""></IconButton>
                        <details class="drive-navigation-pane__action-overflow"><summary :aria-label="t('access.drive.moreActions')" :title="t('access.drive.moreActions')"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg></summary><div class="drive-navigation-pane__action-overflow-menu"><IconButton :label="t('access.drive.upload')" :disabled="exportsRootOpen || !canUpload" @click="chooseUploads"><img :src="rasterIconSource('driveUpload', 'md') ?? ''" alt=""></IconButton><IconButton :label="t('access.drive.download')" :disabled="!canDownloadSelection || exporting" @click="downloadSelectedItems"><img :src="rasterIconSource('driveDownload', 'md') ?? ''" alt=""></IconButton><template v-if="isTrashLocation"><IconButton :label="t('access.drive.restore')" :disabled="!selectedCount || offline || restoringItems" @click="restoreSelectedItems"><img :src="rasterIconSource('fileMove', 'md') ?? ''" alt=""></IconButton><IconButton :label="t('access.drive.release')" :disabled="!selectedCount || offline || releasingItems" @click="openReleaseConfirmation"><img :src="rasterIconSource('trashBurn', 'md') ?? ''" alt=""></IconButton></template><template v-else><IconButton :label="t('access.drive.move')" :disabled="!selectedItem || offline" @click="openMoveDialog"><img :src="rasterIconSource('fileMove', 'md') ?? ''" alt=""></IconButton><IconButton :label="t('access.drive.moveToTrash')" :disabled="!canTrashSelection" @click="openTrashConfirmation"><img :src="rasterIconSource('fileDelete', 'md') ?? ''" alt=""></IconButton></template><IconButton :label="t('access.drive.details')" :disabled="!canInspectSelection" @click="openDetails"><img :src="rasterIconSource('fileDetailPanel', 'md') ?? ''" alt=""></IconButton></div></details>
                    </div>
                    <div class="drive-navigation-pane__view-modes" role="group" :aria-label="t('access.drive.viewMode')"><button v-for="mode in [{ id: 'grid', label: t('access.drive.grid'), icon: 'fileGrade' }, { id: 'list', label: t('access.drive.list'), icon: 'fileList' }, { id: 'details', label: t('access.drive.detailsView'), icon: 'fileDetails' }]" :key="mode.id" type="button" class="drive-navigation-pane__view-mode" :class="{ 'drive-navigation-pane__view-mode--active': viewMode === mode.id }" :aria-label="mode.label" :aria-pressed="viewMode === mode.id" :title="mode.label" @click="setViewMode(mode.id as ViewMode)"><img :src="rasterIconSource(mode.icon, 'md') ?? ''" alt=""></button></div>
                </div>
                <UiAlert v-if="error" tone="error" class="drive-navigation-pane__feedback">{{ error }}</UiAlert>
                <div v-if="exportsRootOpen" class="drive-navigation-pane__items" :class="`drive-navigation-pane__items--${viewMode}`" role="list"><button v-for="entry in sessionExports" :key="entry.exportId" type="button" class="drive-navigation-pane__item" :class="{ 'drive-navigation-pane__item--selected': selectedExportIds.includes(entry.exportId) }" :aria-pressed="selectedExportIds.includes(entry.exportId)" @click="selectExport(entry, $event)"><img :src="rasterIconSource('fileZipExport', 'md') ?? ''" alt=""><span>Exportação ZIP</span><small>{{ exportStateLabel(entry.state) }}</small><small>{{ entry.expiresAt ? formatDate(entry.expiresAt) : 'Prazo não informado' }}</small></button></div>
                <p v-else-if="loading" class="drive-navigation-pane__empty">{{ t('access.drive.loading') }}</p><p v-else-if="!items.length" class="drive-navigation-pane__empty">{{ t(projection?.location.kind === 'trash' ? 'access.drive.emptyTrash' : 'access.drive.emptyFolder') }}</p><div v-else class="drive-navigation-pane__items" :class="`drive-navigation-pane__items--${viewMode}`" role="list"><button v-for="item in items" :key="`${item.kind}-${item.id}`" type="button" class="drive-navigation-pane__item" :class="{ 'drive-navigation-pane__item--selected': selectedIds.includes(item.id) }" :draggable="selectedIds.includes(item.id)" role="listitem" :aria-pressed="selectedIds.includes(item.id)" @click="selectItem(item, $event)" @dblclick="openItem(item)" @dragstart="selectItem(item, $event); rememberTransferSource()" @keydown.enter.prevent="openItem(item)" @keydown.space.prevent="selectItem(item, $event)"><img :src="itemIcon(item)" alt=""><span>{{ item.displayName }}</span><small>{{ itemTypeLabel(item) }}</small><small>{{ formatBytes(item.logicalSizeBytes) }}</small></button></div>
                <DriveDetailsPanel :open="detailsOpen" :details="details" :loading="detailsLoading" :error="detailsError" @close="detailsOpen = false" @retry="openDetails" @share="openSharing" />
                <ResourceSharingPanel v-if="sharingFolder && target" :target="target" :folder="sharingFolder" @close="sharingFolder = null" />
                <footer class="drive-navigation-pane__status" aria-live="polite"><span>{{ statusSummary }}{{ selectionSummary }}</span><span v-for="entry in uploadQueue" :key="entry.id" class="drive-navigation-pane__status-upload"><span>{{ entry.name }} · {{ entry.progress }}%</span><button type="button" :aria-label="entry.state === 'uploading' ? t('access.drive.cancelUpload') : t('access.drive.dismissUploadMessage')" @click="entry.state === 'uploading' ? cancelUpload(entry) : dismissUpload(entry.id)">×</button></span></footer>
            </section>
        </div>
        <input ref="uploadInput" class="drive-navigation-pane__file-input" type="file" multiple @change="receiveUploadSelection">
        <DriveOperationDialog v-model="createFolderOpen" :title="t('access.drive.newFolder')" :confirm-label="t('access.drive.newFolder')" :cancel-label="t('access.drive.cancel')" :loading="creatingFolder" :error="createFolderError" @submit="createFolder"><label>{{ t('access.drive.folderName') }}<input v-model="createFolderName" maxlength="160" autocomplete="off"></label></DriveOperationDialog>
        <DriveOperationDialog v-model="trashConfirmationOpen" :title="t('access.drive.trashConfirmationTitle')" :confirm-label="t('access.drive.moveToTrash')" :cancel-label="t('access.drive.cancel')" :loading="trashingItems" :error="trashError" @submit="trashSelectedItems"><p>{{ t('access.drive.trashConfirmationDescription', { count: selectedCount }, selectedCount) }}</p></DriveOperationDialog>
        <DriveOperationDialog v-model="moveDialogOpen" :title="t('access.drive.move')" :confirm-label="t('access.drive.move')" :cancel-label="t('access.drive.cancel')" :loading="movingItem" :error="moveError" @submit="moveSelectedItem"><label>{{ t('access.drive.moveDestination') }}<select v-model="moveDestinationId"><option :value="null">{{ root.displayName }}</option><option v-for="folder in treeFolders.filter((folder) => canMoveTo(folder))" :key="folder.id" :value="folder.id">{{ '— '.repeat(folder.depth) }}{{ folder.displayName }}</option></select></label></DriveOperationDialog>
        <DriveOperationDialog v-model="releaseConfirmationOpen" :title="t('access.drive.releaseTitle')" :confirm-label="t('access.drive.release')" :cancel-label="t('access.drive.cancel')" :loading="releasingItems" :error="releaseError" @submit="releaseSelectedItems"><p>{{ t('access.drive.releaseDescription', { count: selectedCount }, selectedCount) }}</p></DriveOperationDialog>
    </section>
</template>

<style scoped>
.drive-navigation-pane { min-width: 0; min-height: 0; height: 100%; color: var(--color-text-secondary); }.drive-navigation-pane__layout { display: grid; min-width: 0; min-height: 0; height: 100%; grid-template-columns: minmax(7.5rem, var(--drive-secondary-tree-width, 18%)) var(--space-2) minmax(0, 1fr); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); overflow: hidden; }.drive-navigation-pane__layout--tree-hidden { grid-template-columns: minmax(0, 1fr); }.drive-navigation-pane__tree { display: grid; min-width: 0; min-height: 0; grid-template-rows: auto minmax(0, 1fr) auto; background: var(--color-surface-muted); }.drive-navigation-pane__tree-heading { display: flex; align-items: center; justify-content: space-between; padding: var(--space-3); color: var(--color-text-primary); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); letter-spacing: var(--letter-spacing-wide); text-transform: uppercase; }.drive-navigation-pane__tree-list { display: grid; align-content: start; gap: var(--space-1); overflow: auto; padding: 0 var(--space-2) var(--space-3); }.drive-navigation-pane__drive-section, .drive-navigation-pane__drive-content { display: grid; gap: var(--space-1); }.drive-navigation-pane__drive-header, .drive-navigation-pane__tree-item { display: flex; min-width: 0; align-items: center; gap: var(--space-2); padding: var(--space-2); border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--color-text-secondary); font: inherit; text-align: left; cursor: pointer; }.drive-navigation-pane__drive-header { background: color-mix(in srgb, var(--color-surface-raised) 72%, transparent); color: var(--color-text-primary); font-weight: var(--font-weight-semibold); }.drive-navigation-pane__drive-header:hover, .drive-navigation-pane__drive-section--active .drive-navigation-pane__drive-header { background: var(--color-surface-raised); box-shadow: inset .15rem 0 0 var(--color-action-primary); }.drive-navigation-pane__drive-header img, .drive-navigation-pane__tree-item > img { width: var(--icon-size-sm); height: var(--icon-size-sm); flex: 0 0 auto; object-fit: contain; }.drive-navigation-pane__drive-header span, .drive-navigation-pane__tree-item span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.drive-navigation-pane__tree-row { display: flex; min-width: 0; align-items: center; margin-left: calc(var(--space-2) + var(--drive-depth) * var(--space-3)); border-left: var(--component-border-width) solid color-mix(in srgb, var(--color-border-subtle) 70%, transparent); padding-left: var(--space-1); }.drive-navigation-pane__tree-row .drive-navigation-pane__tree-item { flex: 1 1 auto; }.drive-navigation-pane__tree-item:hover, .drive-navigation-pane__tree-item--active { background: var(--color-surface-raised); color: var(--color-text-primary); }.drive-navigation-pane__tree-item--active { box-shadow: inset .15rem 0 0 var(--color-action-primary); }.drive-navigation-pane__tree-item--trash { margin-top: var(--space-2); }.drive-navigation-pane__tree-toggle { display: inline-grid; width: var(--control-height-sm); height: var(--control-height-sm); flex: 0 0 var(--control-height-sm); place-items: center; padding: 0; border: 0; border-radius: var(--radius-sm); background: transparent; cursor: pointer; }.drive-navigation-pane__tree-toggle img { width: var(--icon-size-sm); height: var(--icon-size-sm); object-fit: contain; }.drive-navigation-pane__tree-toggle--empty { cursor: default; }.drive-navigation-pane__usage { margin: 0; padding: var(--space-3); border-top: var(--component-border-width) solid var(--color-border-subtle); font-size: var(--font-size-sm); }.drive-navigation-pane__tree-divider { display: grid; min-height: 100%; place-items: center; cursor: col-resize; touch-action: none; }.drive-navigation-pane__tree-divider::before { width: var(--component-border-width); height: calc(100% - var(--space-4)); border-radius: var(--radius-pill); background: var(--color-border-subtle); content: ''; }.drive-navigation-pane__collection { display: grid; min-width: 0; min-height: 0; height: 100%; grid-template-rows: auto auto minmax(0, 1fr) auto; }.drive-navigation-pane__collection-header { min-width: 0; padding: var(--space-3) var(--space-4); border-bottom: var(--component-border-width) solid var(--color-border-subtle); }.drive-navigation-pane__breadcrumbs { display: flex; min-width: 0; gap: var(--space-2); overflow: auto; font-weight: var(--font-weight-semibold); }.drive-navigation-pane__breadcrumbs button { flex: none; padding: 0; border: 0; background: transparent; color: var(--color-text-primary); font: inherit; cursor: pointer; }.drive-navigation-pane__breadcrumbs span { color: var(--color-text-secondary); }.drive-navigation-pane__tools { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-3) var(--space-4); }.drive-navigation-pane__actions { display: flex; min-width: 0; flex-wrap: wrap; align-items: center; gap: var(--space-2); margin-left: auto; }.drive-navigation-pane__tools :deep(.ui-icon-button) { border-color: transparent; background: transparent; }.drive-navigation-pane__tools :deep(.ui-icon-button:not(:disabled):hover) { background: var(--color-surface-muted); color: var(--color-action-primary); }.drive-navigation-pane__view-modes { display: flex; gap: var(--space-1); }.drive-navigation-pane__view-mode { position: relative; display: inline-grid; width: var(--control-height-md); height: var(--control-height-md); place-items: center; padding: 0; border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--color-text-secondary); cursor: pointer; }.drive-navigation-pane__view-mode:hover { background: var(--color-surface-muted); color: var(--color-action-primary); }.drive-navigation-pane__view-mode--active { color: var(--color-action-primary); }.drive-navigation-pane__view-mode--active::after { position: absolute; bottom: var(--space-1); left: 50%; width: calc(var(--icon-size-md) * .42); height: calc(var(--component-border-width) * 2); border-radius: var(--radius-pill); background: currentcolor; content: ''; transform: translateX(-50%); }.drive-navigation-pane__view-mode img { width: var(--icon-size-md); height: var(--icon-size-md); object-fit: contain; }.drive-navigation-pane__items { min-height: 0; overflow: auto; padding: var(--space-4); }.drive-navigation-pane__items--grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr)); align-content: start; gap: var(--space-3); }.drive-navigation-pane__item { display: grid; min-width: 0; gap: var(--space-2); padding: var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface-raised); color: var(--color-text-secondary); font: inherit; text-align: left; cursor: pointer; }.drive-navigation-pane__item:hover, .drive-navigation-pane__item--selected { border-color: var(--color-action-primary); background: var(--color-surface-muted); }.drive-navigation-pane__item img { width: calc(2.25rem * var(--component-scale)); height: calc(2.25rem * var(--component-scale)); object-fit: contain; }.drive-navigation-pane__item span { overflow: hidden; color: var(--color-text-primary); font-weight: var(--font-weight-semibold); text-overflow: ellipsis; white-space: nowrap; }.drive-navigation-pane__item small { color: var(--color-text-secondary); }.drive-navigation-pane__items--list, .drive-navigation-pane__items--details { display: grid; align-content: start; gap: var(--space-1); }.drive-navigation-pane__items--list .drive-navigation-pane__item, .drive-navigation-pane__items--details .drive-navigation-pane__item { grid-template-columns: auto minmax(0, 1fr) auto auto; align-items: center; padding: var(--space-2) var(--space-3); }.drive-navigation-pane__items--list .drive-navigation-pane__item small:first-of-type { display: none; }.drive-navigation-pane__empty { display: grid; min-height: 0; place-items: center; margin: 0; padding: var(--space-4); text-align: center; }.drive-navigation-pane__status { display: flex; min-height: var(--control-height-sm); align-items: center; padding: var(--space-2) var(--space-4); border-top: var(--component-border-width) solid var(--color-border-subtle); color: var(--color-text-secondary); font-size: var(--font-size-sm); }.drive-navigation-pane__status span { color: var(--color-text-primary); }.drive-navigation-pane__mobile-close { display: none; }
@media (max-width: 900px) { .drive-navigation-pane__layout { grid-template-columns: minmax(7.5rem, 18%) minmax(0, 1fr); }.drive-navigation-pane__tree-divider { display: none; } }
@media (max-width: 700px) { .drive-navigation-pane { position: fixed; z-index: 70; inset: calc(env(safe-area-inset-top) + 2vh) 2.5vw calc(env(safe-area-inset-bottom) + 2vh); }.drive-navigation-pane__mobile-close { display: inline-grid; width: var(--control-height-sm); height: var(--control-height-sm); place-items: center; padding: 0; border: 0; background: transparent; color: var(--color-text-secondary); font-size: var(--font-size-xl); }.drive-navigation-pane__layout { grid-template-columns: minmax(6rem, 7rem) minmax(0, 1fr); }.drive-navigation-pane__tools { overflow: auto; } }
@media (max-width: 900px) { .drive-navigation-pane__action--overflowable { display: none; }.drive-navigation-pane__action-overflow { display: block; } }
@container (max-width: 48rem) { .drive-navigation-pane__action--overflowable { display: none; }.drive-navigation-pane__action-overflow { display: block; } }
.drive-navigation-pane__drive-content > .drive-navigation-pane__tree-item--trash { margin-left: var(--space-4); }
.drive-navigation-pane__usage { overflow: hidden; padding: var(--space-2) var(--space-3); font-size: var(--font-size-xs); text-overflow: ellipsis; white-space: nowrap; }
.drive-navigation-pane__feedback { margin: var(--space-2) var(--space-4) 0; }
.drive-navigation-pane__drive-control { display: flex; min-width: 0; align-items: stretch; gap: var(--space-1); }.drive-navigation-pane__drive-toggle { display: grid; width: var(--control-height-sm); flex: 0 0 var(--control-height-sm); place-items: center; padding: 0; border: 0; border-radius: var(--radius-sm); background: color-mix(in srgb, var(--color-surface-raised) 72%, transparent); cursor: pointer; }.drive-navigation-pane__drive-toggle img { width: var(--icon-size-sm); height: var(--icon-size-sm); object-fit: contain; }.drive-navigation-pane__drive-header { flex: 1 1 auto; }.drive-navigation-pane__tree-item--active, .drive-navigation-pane__drive-section--active .drive-navigation-pane__drive-header { box-shadow: none; background: color-mix(in srgb, var(--color-action-primary) 14%, var(--color-surface-raised)); color: var(--color-text-primary); }
.drive-navigation-pane__status-upload { display: inline-flex; min-width: 0; align-items: center; gap: var(--space-2); margin-left: var(--space-3); }.drive-navigation-pane__status-upload span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.drive-navigation-pane__status-upload button { border: 0; background: transparent; color: inherit; cursor: pointer; }
.drive-navigation-pane__file-input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.drive-navigation-pane__action-overflow { position: relative; display: none; }.drive-navigation-pane__action-overflow summary { display: grid; width: var(--control-height-md); height: var(--control-height-md); place-items: center; cursor: pointer; list-style: none; }.drive-navigation-pane__action-overflow summary::-webkit-details-marker { display: none; }.drive-navigation-pane__action-overflow svg { width: var(--icon-size-md); height: var(--icon-size-md); fill: currentcolor; }.drive-navigation-pane__action-overflow-menu { position: absolute; z-index: 5; top: calc(100% + var(--space-1)); right: 0; display: grid; gap: var(--space-1); min-width: max-content; padding: var(--space-2); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface-raised); box-shadow: var(--shadow-float); }
.drive-navigation-pane__collection { container-type: inline-size; }
.drive-navigation-pane__tools :deep(.ui-icon-button[aria-pressed='true']) { position: relative; border-color: transparent; background: transparent; color: var(--color-action-primary); }
.drive-navigation-pane__tools :deep(.ui-icon-button[aria-pressed='true']::after) { position: absolute; bottom: var(--space-1); left: 50%; width: calc(var(--icon-size-md) * .42); height: calc(var(--component-border-width) * 2); border-radius: var(--radius-pill); background: currentcolor; content: ''; transform: translateX(-50%); }
</style>
