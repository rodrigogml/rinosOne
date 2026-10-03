<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import UiAlert from '../design-system/UiAlert.vue';
import UIRinoButton from '../design-system/UIRinoButton.vue';
import { rasterIconSource } from '../design-system/rasterIconAssets';
import { toast } from '../design-system/toast/toastService';
import DriveDetailsPanel from './DriveDetailsPanel.vue';
import ResourceSharingPanel from '../authorization/ResourceSharingPanel.vue';
import DriveOperationDialog from './DriveOperationDialog.vue';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';
import {
    loadDriveDetails,
    createDriveFolder,
    loadDriveLocation,
    loadDriveTree,
    loadSharedWithMe,
    moveDriveItem,
    releaseDriveItems,
    restoreDriveItems,
    uploadDriveFile,
    driveDownloadUrl,
    requestDriveExport,

    type DriveDetailsProjection,
    type DriveCatalogProjection,
    type DriveItem,
    type DriveLocation,
    type DriveLocationProjection,
    type DriveWorkspaceTarget,

    type SharedWithMeProjection,
    type SharedDriveItem,
    trashDriveItems,
} from './driveWorkspaceApi';

import UiControlGroup from '../design-system/UiControlGroup.vue';
import UiActionPopover from '../design-system/UiActionPopover.vue';
import { createRinoToggleGroup } from '../design-system/rinoToggleGroup';
import { sameDrive, type DrivePanelId, type DrivePanelContext, type DriveDropDestination, type SessionExport } from './drivePanelTypes';

const props = withDefaults(defineProps<{
    surface: WorkspaceSurface;
    panelId: DrivePanelId;
    catalog: DriveCatalogProjection | null;
    sessionExports: SessionExport[];
    showShared?: boolean;
    showExports?: boolean;
    showSplit?: boolean;
    secondaryOpen?: boolean;
    preferenceOwner: string;
}>(), { showShared: false, showExports: false, showSplit: false, secondaryOpen: false });
const emit = defineEmits<{
    toggleSplit: [];
    mutated: [target: DriveWorkspaceTarget];
    revoked: [target: DriveWorkspaceTarget];
    exportCreated: [entry: Promise<SessionExport>];
    downloadExports: [entries: SessionExport[]];
    dragStart: [context: DrivePanelContext];
    dragEnd: [];
    dropItems: [destination: DriveDropDestination | Promise<DriveDropDestination>];
}>();
const { t, locale } = useI18n();

type ViewMode = 'grid' | 'list' | 'details';
interface TreeFolder extends DriveItem { depth: number; hasChildren: boolean; }
type VisibleDriveItem = DriveItem & { originTarget?: DriveWorkspaceTarget };

const isGlobalSurface = computed(() => props.surface.destinationId === 'global.drive');
const target = ref<DriveWorkspaceTarget | null>(isGlobalSurface.value
    ? null
    : props.surface.destinationId === 'tenant.drive' && props.surface.tenantId !== null
        ? { kind: 'tenant', tenantId: props.surface.tenantId }
        : { kind: 'personal' });
const catalog = computed(() => props.catalog);
const sharedWithMe = ref<SharedWithMeProjection | null>(null);
const sharedRootOpen = ref(false);
const exportsRootOpen = ref(false);
const sessionExports = computed(() => props.sessionExports);
const selectedExportIds = ref<string[]>([]);
const exportSelectionAnchorId = ref<string | null>(null);
const isWork = computed(() => target.value?.kind === 'tenant');
const workspaceName = computed(() => sharedRootOpen.value
    ? catalog.value?.sharedWithMe.displayName ?? 'Compartilhados comigo'
    : catalog.value?.drives.find((entry) => entry.target.kind === target.value?.kind && (entry.target.kind !== 'tenant' || target.value?.kind !== 'tenant' || entry.target.tenantId === target.value.tenantId))?.displayName
        ?? t(isWork.value ? 'access.drive.work' : 'access.drive.personal'));
const rootLocation = computed<DriveLocation>(() => ({ kind: 'root', id: null, displayName: isGlobalSurface.value ? workspaceName.value : t(isWork.value ? 'access.drive.organizationRoot' : 'access.drive.root'), parentFolderId: null }));
const tree = ref<DriveItem[]>([]);
const projection = ref<DriveLocationProjection | null>(null);
const selectedKeys = ref<string[]>([]);
const selectionAnchorKey = ref<string | null>(null);
const loading = ref(false);
const error = ref('');
const stale = ref(false);
const offline = ref(!navigator.onLine);
const treeDrawerOpen = ref(false);
const mobileViewport = ref(false);
const primaryTreeVisible = ref(true);
const expandedDriveKeys = ref(new Set<string>());
const expandedTreeFolderIds = ref(new Set<number>());
const primaryLayoutElement = ref<HTMLElement | null>(null);
const primaryTreeWidth = ref<number | null>(null);
const viewMode = ref<ViewMode>('details');
const detailsOpen = ref(false);
const detailsLoading = ref(false);
const detailsError = ref('');
const details = ref<DriveDetailsProjection | null>(null);
const sharingFolder = ref<DriveItem | null>(null);
const locationHeading = ref<HTMLElement | null>(null);
const treeTrigger = ref<{ focus: () => void } | null>(null);
const createFolderOpen = ref(false);
const createFolderName = ref('');
const createFolderError = ref('');
const creatingFolder = ref(false);
const createFolderTarget = ref<DriveWorkspaceTarget | null>(null);
const createFolderParentId = ref<number | null>(null);
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
const VIEW_MODE_STORAGE_KEY = `rinos-one.drive.view-mode.v2.${props.preferenceOwner}.${props.panelId}`;
const viewModeGroup = createRinoToggleGroup();
let locationRequest = 0;
let disposed = false;
let catalogInitialized = false;

const hasData = computed(() => projection.value !== null);
const items = computed<VisibleDriveItem[]>(() => sharedRootOpen.value
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
            const hasChildren = (byParent.get(folder.id)?.length ?? 0) > 0;
            ordered.push({ ...folder, depth, hasChildren });
            if (hasChildren && expandedTreeFolderIds.value.has(folder.id)) visit(folder.id, depth + 1);
        }
    };
    visit(null, 0);
    // Uma árvore parcial pode começar em uma pasta concedida cujo ancestral não
    // é visível. Ela ainda precisa continuar alcançável para o membro.
    for (const folder of tree.value.filter((item) => item.parentFolderId !== null && !knownIds.has(item.parentFolderId))) {
        const hasChildren = (byParent.get(folder.id)?.length ?? 0) > 0;
        ordered.push({ ...folder, depth: 0, hasChildren });
        if (hasChildren && expandedTreeFolderIds.value.has(folder.id)) visit(folder.id, 1);
    }
    return ordered;
});
const collectionLabel = computed(() => projection.value?.location.displayName ?? workspaceName.value);
const exportRootLabel = computed(() => t('access.drive.exportsRoot'));
const selectedCount = computed(() => selectedKeys.value.length);
const selectedItem = computed(() => selectedKeys.value.length === 1 ? items.value.find((item) => itemKey(item) === selectedKeys.value[0]) ?? null : null);
const selectedItems = computed(() => items.value.filter((item) => selectedKeys.value.includes(itemKey(item))));
const itemCounts = computed(() => ({
    total: exportsRootOpen.value ? sessionExports.value.length : items.value.length,
    files: exportsRootOpen.value ? sessionExports.value.length : items.value.filter((item) => item.kind === 'file').length,
    folders: exportsRootOpen.value ? 0 : items.value.filter((item) => item.kind === 'folder').length,
}));
const selectedItemCounts = computed(() => ({
    total: selectedItems.value.length,
    files: selectedItems.value.filter((item) => item.kind === 'file').length,
    folders: selectedItems.value.filter((item) => item.kind === 'folder').length,
}));
const selectedLogicalSizeBytes = computed(() => selectedItems.value.reduce((total, item) => total + (item.logicalSizeBytes ?? 0), 0));
const statusSummary = computed(() => t('access.drive.statusSummary', itemCounts.value));
const selectionSummary = computed(() => exportsRootOpen.value && selectedExportIds.value.length ? t('access.drive.statusSelectionSummary', { total: selectedExportIds.value.length, folders: 0, files: selectedExportIds.value.length, size: '—' }) : selectedCount.value
    ? t('access.drive.statusSelectionSummary', { ...selectedItemCounts.value, size: formatBytes(selectedLogicalSizeBytes.value) })
    : '');
const canCreateFolder = computed(() => !exportsRootOpen.value && !sharedRootOpen.value && projection.value?.location.kind !== 'trash' && projection.value?.capabilities.edit === true);
const canTrashSelection = computed(() => !offline.value && !exportsRootOpen.value && !sharedRootOpen.value && projection.value?.location.kind !== 'trash' && selectedItems.value.length > 0 && selectedItems.value.every((item) => item.capabilities.trash));
const canMoveSelection = computed(() => !offline.value && !exportsRootOpen.value && !sharedRootOpen.value && selectedItem.value !== null && selectedItem.value.capabilities.edit && projection.value?.location.kind !== 'trash');
const canUpload = computed(() => canCreateFolder.value && !offline.value);
const selectedExports = computed(() => sessionExports.value.filter((entry) => selectedExportIds.value.includes(entry.exportId)));
const isTrashLocation = computed(() => !exportsRootOpen.value && projection.value?.location.kind === 'trash');
const canDownloadSelection = computed(() => exportsRootOpen.value
    ? selectedExports.value.length > 0 && selectedExports.value.every((entry) => entry.state === 'READY')
    : target.value !== null && selectedItems.value.length > 0 && selectedItems.value.every((item) => item.capabilities.read));
const exporting = ref(false);
function online(): void { offline.value = false; }
function offlineNow(): void { offline.value = true; }
function uploadStateLabel(state: UploadEntry['state']): string { return t(`access.drive.upload${state[0].toUpperCase()}${state.slice(1)}`); }
function formatBytes(bytes: number | null): string {
    if (bytes === null || bytes === 0) return bytes === 0 ? '0 B' : '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** index).toLocaleString(locale.value, { maximumFractionDigits: 1 })} ${units[index]}`;
}
function formatDate(value: string | null): string { return value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—'; }
function itemTypeLabel(item: DriveItem): string { return item.kind === 'folder' ? t('access.drive.folder') : item.detectedMimeType?.split('/').at(-1)?.toUpperCase() ?? t('access.drive.file'); }
function itemIconSource(item: DriveItem): string { return rasterIconSource(item.kind === 'folder' ? 'folderClose' : 'file', 'md') ?? ''; }
function folderTreeIcon(folder: TreeFolder): string { return rasterIconSource(folder.hasChildren && expandedTreeFolderIds.value.has(folder.id) ? 'folderOpen' : 'folderClose', 'sm') ?? ''; }
function trashIconSource(): string { return rasterIconSource((projection.value?.usage.trashBytes ?? 0) > 0 ? 'trashFull' : 'trashEmpty', 'sm') ?? ''; }
function driveKey(nextTarget: DriveWorkspaceTarget): string { return nextTarget.kind === 'tenant' ? `tenant:${nextTarget.tenantId}` : 'personal'; }
function isActiveDrive(nextTarget: DriveWorkspaceTarget): boolean { return !sharedRootOpen.value && target.value !== null && sameTarget(nextTarget, target.value); }
function isDriveExpanded(nextTarget: DriveWorkspaceTarget): boolean { return expandedDriveKeys.value.has(driveKey(nextTarget)); }
function toggleDriveTree(nextTarget: DriveWorkspaceTarget): void {
    const key = driveKey(nextTarget);
    const next = new Set(expandedDriveKeys.value);
    if (next.has(key)) next.delete(key); else next.add(key);
    expandedDriveKeys.value = next;
}
function exportStateLabel(state: string): string {
    const key = state === 'FAILED' ? 'exportFailedStatus' : `export${state.charAt(0)}${state.slice(1).toLowerCase()}`;
    return t(`access.drive.${key}`);
}
function toggleTreeFolder(folder: TreeFolder): void {
    if (!folder.hasChildren) return;
    const next = new Set(expandedTreeFolderIds.value);
    if (next.has(folder.id)) next.delete(folder.id); else next.add(folder.id);
    expandedTreeFolderIds.value = next;
}
function isCurrent(location: DriveLocation): boolean { return projection.value?.location.kind === location.kind && projection.value.location.id === location.id; }
function itemKey(item: Pick<DriveItem, 'kind' | 'id'> & { originTarget?: DriveWorkspaceTarget }): string { return `${item.originTarget ? driveKey(item.originTarget) : ''}:${item.kind}:${item.id}`; }
function selectItem(item: DriveItem, event?: MouseEvent | KeyboardEvent): void {
    if (!item.capabilities.read) return;
    if (event?.shiftKey && selectionAnchorKey.value !== null) {
        const from = items.value.findIndex((candidate) => itemKey(candidate) === selectionAnchorKey.value);
        const to = items.value.findIndex((candidate) => itemKey(candidate) === itemKey(item));
        if (from >= 0 && to >= 0) selectedKeys.value = items.value.slice(Math.min(from, to), Math.max(from, to) + 1).filter((candidate) => candidate.capabilities.read).map((candidate) => itemKey(candidate));
        return;
    }
    if (event?.ctrlKey || event?.metaKey) selectedKeys.value = selectedKeys.value.includes(itemKey(item)) ? selectedKeys.value.filter((id) => id !== itemKey(item)) : [...selectedKeys.value, itemKey(item)];
    else selectedKeys.value = [itemKey(item)];
    selectionAnchorKey.value = itemKey(item);
}
function clearSelection(): void { selectedKeys.value = []; selectedExportIds.value = []; selectionAnchorKey.value = null; exportSelectionAnchorId.value = null; }
function clearDetails(): void { detailsOpen.value = false; detailsLoading.value = false; detailsError.value = ''; details.value = null; }
function openSharing(): void { if (details.value?.item.kind === 'folder') sharingFolder.value = details.value.item; }
async function openDetails(): Promise<void> {
    if (!target.value || !selectedItem.value || offline.value) return;
    const request = locationRequest;
    const item = selectedItem.value;
    const detailTarget = { ...target.value };
    detailsOpen.value = true; detailsLoading.value = true; detailsError.value = ''; details.value = null;
    try {
        const result = await loadDriveDetails(detailTarget, item);
        if (!disposed && request === locationRequest && detailsOpen.value && selectedItem.value && itemKey(selectedItem.value) === itemKey(item)) details.value = result;
    } catch (reason) {
        if (!disposed && request === locationRequest) detailsError.value = t(axios.isAxiosError(reason) && reason.response?.status === 403 ? 'access.drive.detailsDenied' : 'access.drive.detailsFailed');
    } finally { if (request === locationRequest) detailsLoading.value = false; }
}
function openCreateFolder(): void {
    if (!target.value || !projection.value) return;
    openCreateFolderAt(target.value, projection.value.location);
}
function openCreateFolderAt(nextTarget: DriveWorkspaceTarget, location: DriveLocation): void {
    if (location.kind === 'trash') return;
    createFolderTarget.value = nextTarget;
    createFolderParentId.value = location.kind === 'folder' ? location.id : null;
    createFolderName.value = '';
    createFolderError.value = '';
    createFolderOpen.value = true;
}
async function createFolder(): Promise<void> {
    const displayName = createFolderName.value.trim();
    if (!createFolderTarget.value || !displayName) { createFolderError.value = t('access.drive.folderNameRequired'); return; }
    creatingFolder.value = true;
    createFolderError.value = '';
    try {
        await createDriveFolder(createFolderTarget.value, displayName, createFolderParentId.value);
        createFolderOpen.value = false;
        await refreshAffected(createFolderTarget.value);
        emit('mutated', createFolderTarget.value);
    } catch (reason) {
        createFolderError.value = axios.isAxiosError(reason) && reason.response?.status === 403
            ? t('access.drive.createFolderDenied')
            : t('access.drive.createFolderFailed');
    } finally { creatingFolder.value = false; }
}
function openTrashConfirmation(): void { trashError.value = ''; trashConfirmationOpen.value = true; }
async function trashSelectedItems(): Promise<void> {
    if (!target.value || !canTrashSelection.value) return;
    const mutationTarget = { ...target.value };
    trashingItems.value = true;
    trashError.value = '';
    try {
        await trashDriveItems(mutationTarget, selectedItems.value);
        trashConfirmationOpen.value = false;
        selectedKeys.value = [];
        await refreshAffected(mutationTarget);
        emit('mutated', mutationTarget);
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
    const mutationTarget = { ...target.value };
    movingItem.value = true;
    moveError.value = '';
    try {
        await moveDriveItem(mutationTarget, selectedItem.value, moveDestinationId.value);
        moveDialogOpen.value = false;
        selectedKeys.value = [];
        await refreshAffected(mutationTarget);
        emit('mutated', mutationTarget);
    } catch (reason) {
        moveError.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.moveDenied') : t('access.drive.moveFailed');
    } finally { movingItem.value = false; }
}
async function restoreSelectedItems(): Promise<void> {
    if (!target.value || !selectedItems.value.length) return;
    const mutationTarget = { ...target.value };
    restoringItems.value = true;
    try {
        await restoreDriveItems(mutationTarget, selectedItems.value);
        selectedKeys.value = [];
        await refreshAffected(mutationTarget);
        emit('mutated', mutationTarget);
    } catch (reason) { error.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.restoreDenied') : t('access.drive.restoreFailed'); }
    finally { restoringItems.value = false; }
}
function openReleaseConfirmation(): void { releaseError.value = ''; releaseConfirmationOpen.value = true; }
async function releaseSelectedItems(): Promise<void> {
    if (!target.value || !selectedItems.value.length) return;
    const mutationTarget = { ...target.value };
    releasingItems.value = true;
    try {
        await releaseDriveItems(mutationTarget, selectedItems.value);
        releaseConfirmationOpen.value = false;
        selectedKeys.value = [];
        await refreshAffected(mutationTarget);
        emit('mutated', mutationTarget);
    } catch (reason) { releaseError.value = axios.isAxiosError(reason) && reason.response?.status === 403 ? t('access.drive.releaseDenied') : t('access.drive.releaseFailed'); }
    finally { releasingItems.value = false; }
}
function chooseUploads(): void { if (canUpload.value) uploadInput.value?.click(); }
async function uploadFiles(files: FileList | File[], destination?: DriveDropDestination): Promise<void> {
    if (!destination && (!target.value || !projection.value || !canUpload.value)) return;
    const uploadTarget = destination?.target ?? { ...target.value! };
    const parentId = destination ? destination.folderId : (projection.value?.location.kind === 'folder' ? projection.value.location.id : null);
    const entries: UploadEntry[] = Array.from(files).map(file => ({ id: nextUploadId++, name: file.name, file, state: 'queued', progress: 0, controller: null }));
    uploadQueue.value.push(...entries);
    for (const pending of entries) {
        const entry = uploadQueue.value.find(candidate => candidate.id === pending.id)!;
        if (disposed || entry.state !== 'queued') continue;
        entry.state = 'uploading'; entry.controller = new AbortController();
        try {
            await uploadDriveFile(uploadTarget, entry.file, parentId, entry.controller.signal, progress => { entry.progress = progress; });
            entry.progress = 100; entry.state = 'complete';
            window.setTimeout(() => dismissUpload(entry.id), 4_000);
        } catch { entry.state = entry.controller.signal.aborted ? 'cancelled' : 'failed'; }
        finally { entry.controller = null; }
    }
    if (!disposed) { await refreshAffected(uploadTarget); emit('mutated', uploadTarget); }
}
function receiveUploadSelection(event: Event): void { const input = event.target as HTMLInputElement; if (input.files) void uploadFiles(input.files); input.value = ''; }
function cancelUpload(entry: UploadEntry): void { entry.controller?.abort(); if (entry.state === 'queued') entry.state = 'cancelled'; }
function dismissUpload(entryId: number): void { uploadQueue.value = uploadQueue.value.filter((entry) => entry.id !== entryId); }
async function downloadSelectedItem(): Promise<void> {
    if (exportsRootOpen.value) { emit('downloadExports', selectedExports.value); return; }
    if (!target.value || !canDownloadSelection.value || offline.value) return;
    const sourceTarget = { ...target.value };
    const sourceItems = [...selectedItems.value];
    if (sourceItems.length === 1 && sourceItems[0].kind === 'file') {
        window.location.assign(driveDownloadUrl(sourceTarget, sourceItems[0].id)); return;
    }
    exporting.value = true;
    const pending = requestDriveExport(sourceTarget, sourceItems).then(job => ({ exportId: job.exportId,
        target: sourceTarget, state: job.state, expiresAt: job.expiresAt, createdAt: new Date().toISOString() }));
    // The window owns the request from the start, even if this panel is unmounted before the response.
    emit('exportCreated', pending);
    try { await pending; }
    catch { /* The window reports this request failure once. */ }
    finally { exporting.value = false; }
}
function openExports(): void {
    if (!props.showExports || !sessionExports.value.length) return;
    locationRequest++; loading.value = false;
    expandedDriveKeys.value = new Set(); exportsRootOpen.value = true; sharedRootOpen.value = false;
    clearSelection(); clearDetails();
}
function selectExport(entry: SessionExport, event?: MouseEvent): void {
    if (event?.shiftKey && exportSelectionAnchorId.value !== null) {
        const from = sessionExports.value.findIndex((candidate) => candidate.exportId === exportSelectionAnchorId.value);
        const to = sessionExports.value.findIndex((candidate) => candidate.exportId === entry.exportId);
        if (from >= 0 && to >= 0) selectedExportIds.value = sessionExports.value.slice(Math.min(from, to), Math.max(from, to) + 1).map((candidate) => candidate.exportId);
        return;
    }
    if (event?.ctrlKey || event?.metaKey) selectedExportIds.value = selectedExportIds.value.includes(entry.exportId) ? selectedExportIds.value.filter((id) => id !== entry.exportId) : [...selectedExportIds.value, entry.exportId];
    else selectedExportIds.value = [entry.exportId];
    exportSelectionAnchorId.value = entry.exportId;
}
async function openLocation(location: DriveLocation): Promise<void> {
    exportsRootOpen.value = false;
    await loadPanel(location);
}
async function openCatalogDrive(nextTarget: DriveWorkspaceTarget): Promise<void> {
    if (offline.value) return;
    locationRequest++;
    exportsRootOpen.value = false; sharedRootOpen.value = false;
    expandedDriveKeys.value = new Set(expandedDriveKeys.value).add(driveKey(nextTarget));
    if (!target.value || !sameDrive(target.value, nextTarget)) {
        expandedTreeFolderIds.value = new Set(); tree.value = [];
    }
    target.value = { ...nextTarget };
    projection.value = null; stale.value = false;
    clearSelection(); clearDetails();
    await loadPanel(rootLocation.value, false, true);
}
async function openSharedWithMe(): Promise<void> {
    if (offline.value || !props.showShared) return;
    const request = ++locationRequest;
    exportsRootOpen.value = false; sharedRootOpen.value = true; target.value = null;
    tree.value = []; projection.value = null; clearSelection(); clearDetails();
    loading.value = true; error.value = '';
    try {
        const shared = await loadSharedWithMe();
        if (disposed || request !== locationRequest) return;
        sharedWithMe.value = shared;
        projection.value = { location: { kind: 'root', id: null, displayName: catalog.value?.sharedWithMe.displayName ?? t('access.drive.sharedRoot'), parentFolderId: null }, breadcrumbs: [], folders: [], files: [], capabilities: { read: true, edit: false, trash: false }, usage: { workspaceBytes: 0, systemManagedBytes: 0, trashBytes: 0, totalBytes: 0 } };
    } catch { if (request === locationRequest) error.value = t('access.drive.loadFailed'); }
    finally { if (request === locationRequest) loading.value = false; }
}
async function refresh(): Promise<void> {
    if (exportsRootOpen.value) return;
    if (sharedRootOpen.value) { await openSharedWithMe(); return; }
    if (!target.value || offline.value) return;
    await loadPanel(projection.value?.location ?? rootLocation.value, true, true);
}
async function loadPanel(location: DriveLocation, preserveSelection = false, includeTree = false): Promise<void> {
    if (!target.value || offline.value) return;
    const request = ++locationRequest;
    const requestTarget = { ...target.value };
    loading.value = true; error.value = '';
    try {
        const [nextTree, nextProjection] = await Promise.all([
            includeTree ? loadDriveTree(requestTarget) : Promise.resolve(tree.value),
            loadDriveLocation(requestTarget, location),
        ]);
        if (disposed || request !== locationRequest) return;
        tree.value = nextTree; projection.value = nextProjection;
        selectedKeys.value = preserveSelection ? selectedKeys.value.filter(id => [...nextProjection.folders, ...nextProjection.files].some(item => itemKey(item) === id)) : [];
        clearDetails(); stale.value = false; treeDrawerOpen.value = false;
        if (!preserveSelection) { await nextTick(); locationHeading.value?.focus(); }
    } catch (reason) {
        if (disposed || request !== locationRequest) return;
        const denied = axios.isAxiosError(reason) && [403, 404].includes(reason.response?.status ?? 0);
        stale.value = !denied && hasData.value;
        if (denied) {
            tree.value = []; projection.value = null; clearSelection(); clearDetails();
            if (location.kind === 'root') { target.value = null; emit('revoked', requestTarget); }
        }
        error.value = t(denied ? 'access.drive.locationDenied' : 'access.drive.loadFailed');
    } finally { if (request === locationRequest) loading.value = false; }
}
function removeRevokedSharedItem(item: SharedDriveItem): void {
    if (sharedWithMe.value === null) return;
    const same = (candidate: SharedDriveItem): boolean => itemKey(candidate) === itemKey(item) && candidate.kind === item.kind && sameTarget(candidate.originTarget, item.originTarget);
    sharedWithMe.value = { folders: sharedWithMe.value.folders.filter((candidate) => !same(candidate)), files: sharedWithMe.value.files.filter((candidate) => !same(candidate)) };
}
async function openSharedItem(item: VisibleDriveItem & { originTarget: DriveWorkspaceTarget }): Promise<void> {
    target.value = { ...item.originTarget }; sharedRootOpen.value = false; exportsRootOpen.value = false;
    clearSelection(); clearDetails(); tree.value = []; projection.value = null;
    if (item.kind === 'folder') {
        await loadPanel({ kind: 'folder', id: item.id, displayName: item.displayName, parentFolderId: null }, false, true);
        return;
    }
    const request = ++locationRequest;
    detailsOpen.value = true; detailsLoading.value = true;
    try {
        const result = await loadDriveDetails(item.originTarget, item);
        if (disposed || request !== locationRequest) return;
        details.value = result;
        projection.value = { location: result.location, breadcrumbs: [], folders: [], files: [result.item], capabilities: { read: true, edit: false, trash: false }, usage: { workspaceBytes: 0, systemManagedBytes: 0, trashBytes: 0, totalBytes: 0 } };
        selectedKeys.value = [itemKey(result.item)];
    } catch {
        if (request !== locationRequest) return;
        removeRevokedSharedItem(item); target.value = null; sharedRootOpen.value = true;
        error.value = t('access.drive.locationDenied');
    } finally { if (request === locationRequest) detailsLoading.value = false; }
}
function openItem(item: VisibleDriveItem): void {
    if (sharedRootOpen.value && item.originTarget) { void openSharedItem(item as VisibleDriveItem & { originTarget: DriveWorkspaceTarget }); return; }
    if (item.kind === 'folder') void openLocation({ kind: 'folder', id: item.id, displayName: item.displayName, parentFolderId: item.parentFolderId });
    else selectItem(item);
}
function moveTreeFocus(event: KeyboardEvent): void {
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    const tree = (event.currentTarget as HTMLElement).closest<HTMLElement>('[role="tree"]');
    if (tree === null) return;
    const entries = Array.from(tree.querySelectorAll<HTMLButtonElement>('[role="treeitem"]:not([disabled])'));
    const current = entries.indexOf(event.currentTarget as HTMLButtonElement);
    if (current < 0) return;
    event.preventDefault();
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? entries.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + entries.length) % entries.length;
    entries[next]?.focus();
}
function closeTreeDrawer(): void {
    treeDrawerOpen.value = false;
    void nextTick(() => treeTrigger.value?.focus());
}
function updateViewport(): void {
    mobileViewport.value = typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 700px)').matches;
    if (!mobileViewport.value) treeDrawerOpen.value = false;
}
function toggleTree(): void {
    if (mobileViewport.value) treeDrawerOpen.value = !treeDrawerOpen.value;
    else primaryTreeVisible.value = !primaryTreeVisible.value;
}
function restoreViewMode(): void {
    let value: string | null = null;
    try { value = window.localStorage.getItem(VIEW_MODE_STORAGE_KEY); } catch { /* Storage may be unavailable. */ }
    if (value === 'grid' || value === 'list' || value === 'details') viewMode.value = value;
}
function clampPrimaryTreeWidth(value: number): number {
    const layoutWidth = primaryLayoutElement.value?.getBoundingClientRect().width ?? 0;
    const minimumWidth = 120;
    const maximumWidth = Math.max(minimumWidth, Math.floor(layoutWidth * .25));
    return Math.min(Math.max(value, minimumWidth), maximumWidth);
}
function resizePrimaryTreeAt(clientX: number): void {
    const bounds = primaryLayoutElement.value?.getBoundingClientRect();
    if (bounds) primaryTreeWidth.value = clampPrimaryTreeWidth(clientX - bounds.left);
}
function onPrimaryTreeResize(event: PointerEvent): void { resizePrimaryTreeAt(event.clientX); }
function stopPrimaryTreeResize(): void {
    window.removeEventListener('pointermove', onPrimaryTreeResize);
    window.removeEventListener('pointerup', stopPrimaryTreeResize);
}
function startPrimaryTreeResize(event: PointerEvent): void {
    if (window.matchMedia('(max-width: 900px)').matches) return;
    event.preventDefault();
    resizePrimaryTreeAt(event.clientX);
    window.addEventListener('pointermove', onPrimaryTreeResize);
    window.addEventListener('pointerup', stopPrimaryTreeResize, { once: true });
}
function resizePrimaryTreeBy(offset: number): void {
    const layoutWidth = primaryLayoutElement.value?.getBoundingClientRect().width ?? 0;
    primaryTreeWidth.value = clampPrimaryTreeWidth((primaryTreeWidth.value ?? Math.min(240, Math.floor(layoutWidth * .25))) + offset);
}

function sameTarget(left: DriveWorkspaceTarget, right: DriveWorkspaceTarget): boolean { return sameDrive(left, right); }
function panelContext(): DrivePanelContext | null {
    return target.value && projection.value && !sharedRootOpen.value && !exportsRootOpen.value
        ? { panelId: props.panelId, target: { ...target.value }, projection: projection.value, items: [...selectedItems.value], tree: [...tree.value] }
        : null;
}
function startItemDrag(item: DriveItem, event: DragEvent): void {
    if (!selectedKeys.value.includes(itemKey(item))) selectItem(item);
    const context = panelContext();
    if (!context?.items.length || offline.value || isTrashLocation.value) { event.preventDefault(); return; }
    event.dataTransfer?.setData('application/x-rinos-drive-items', props.panelId);
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copyMove';
    emit('dragStart', context);
}
function startTreeDrag(folder: DriveItem, event: DragEvent): void {
    const context = panelContext();
    if (!context || offline.value || !folder.capabilities.read) { event.preventDefault(); return; }
    event.dataTransfer?.setData('application/x-rinos-drive-items', props.panelId);
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copyMove';
    // A tree node is an explicit single source, independent of the collection selection.
    emit('dragStart', { ...context, items: [folder], projection: { ...context.projection,
        location: { kind: folder.parentFolderId === null ? 'root' : 'folder', id: folder.parentFolderId,
            parentFolderId: null, displayName: folder.displayName } } });
}
function dropItems(event: DragEvent, destination?: DriveDropDestination): void {
    event.preventDefault(); event.stopPropagation();
    const context = panelContext();
    const next = destination ?? (context && {
        target: context.target,
        folderId: context.projection.location.kind === 'folder' ? context.projection.location.id : null,
        displayName: context.projection.location.displayName,
        editable: context.projection.capabilities.edit && context.projection.location.kind !== 'trash',
    });
    if (!next || !next.editable || offline.value || exportsRootOpen.value) return;
    if (event.dataTransfer?.files.length) void uploadFiles(event.dataTransfer.files, next);
    else emit('dropItems', next);
}
function folderDestination(folder: DriveItem): DriveDropDestination | undefined {
    return target.value ? { target: { ...target.value }, folderId: folder.id, displayName: folder.displayName, editable: folder.capabilities.edit } : undefined;
}
async function dropOnDrive(event: DragEvent, nextTarget: DriveWorkspaceTarget): Promise<void> {
    event.preventDefault(); event.stopPropagation();
    if (offline.value) return;
    const files = Array.from(event.dataTransfer?.files ?? []);
    const destination = loadDriveLocation(nextTarget, { kind: 'root', id: null, displayName: '', parentFolderId: null })
        .then(root => ({ target: nextTarget, folderId: null, displayName: root.location.displayName, editable: root.capabilities.edit }));
    if (!files.length) {
        // Dispatch before dragend clears the source; root authorization may finish later.
        emit('dropItems', destination);
        return;
    }
    try {
        const next = await destination;
        if (!disposed && next.editable) await uploadFiles(files, next);
    } catch { error.value = t('access.drive.transferInvalid'); }
}
function setViewMode(mode: ViewMode, selected: boolean): void {
    if (selected) viewMode.value = mode;
}
async function refreshAffected(nextTarget: DriveWorkspaceTarget): Promise<void> {
    if (sharedRootOpen.value) { await openSharedWithMe(); return; }
    if (!exportsRootOpen.value && target.value && sameDrive(target.value, nextTarget)) await refresh();
}
watch(() => props.catalog, next => {
    if (isGlobalSurface.value && !catalogInitialized && next?.drives.length) {
        catalogInitialized = true;
        void openCatalogDrive(next.drives.find(entry => entry.target.kind === 'personal')?.target ?? next.drives[0].target);
    }
}, { immediate: true });
onMounted(() => {
    restoreViewMode();
    updateViewport();
    window.addEventListener('resize', updateViewport);
    window.addEventListener('online', online);
    window.addEventListener('offline', offlineNow);
    if (!isGlobalSurface.value) void refresh();
});
watch(viewMode, value => { try { window.localStorage.setItem(VIEW_MODE_STORAGE_KEY, value); } catch { /* Optional local preference. */ } });
onBeforeUnmount(() => {
    disposed = true; locationRequest++;
    stopPrimaryTreeResize();
    uploadQueue.value.forEach(entry => entry.controller?.abort());
    window.removeEventListener('resize', updateViewport);
    window.removeEventListener('online', online); window.removeEventListener('offline', offlineNow);
});
defineExpose({ refresh, refreshAffected, context: panelContext, clearSelection });
</script>

<template>
    <section class="drive-panel" :data-panel="panelId" :aria-label="workspaceName">
        <input ref="uploadInput" type="file" multiple hidden @change="receiveUploadSelection">
        <div v-if="offline || stale || error" class="drive-panel__feedback" aria-live="polite"><UiAlert v-if="offline" tone="warning">{{ t('access.drive.offline') }}</UiAlert><UiAlert v-if="stale" tone="warning">{{ t('access.drive.stale') }}</UiAlert><UiAlert v-if="error" tone="error">{{ error }}</UiAlert></div>
        <div ref="primaryLayoutElement" class="drive-panel__layout" :class="{ 'drive-panel__layout--tree-hidden': !primaryTreeVisible }" :style="primaryTreeWidth === null ? undefined : { '--drive-primary-tree-width': `${primaryTreeWidth}px` }">
            <aside class="drive-panel__tree" :class="{ 'drive-panel__tree--open': treeDrawerOpen }" :aria-label="t('access.drive.tree')">
                <div class="drive-panel__tree-heading"><strong>{{ t('access.drive.tree') }}</strong><UIRinoButton class="drive-panel__drawer-close" accessible-label="access.drive.closeTree" icon="btCancel" @click="closeTreeDrawer" /></div>
                <nav class="drive-panel__tree-list" role="tree" :aria-label="t('access.drive.tree')">
                    <section v-for="drive in catalog?.drives ?? []" :key="driveKey(drive.target)" class="drive-panel__drive-section" :class="{ 'drive-panel__drive-section--active': isActiveDrive(drive.target) }">
                        <div class="drive-panel__drive-control"><button type="button" class="drive-panel__drive-toggle" :aria-label="isDriveExpanded(drive.target) ? 'Recolher drive' : 'Expandir drive'" :aria-expanded="isDriveExpanded(drive.target)" @click="toggleDriveTree(drive.target)"><img :src="rasterIconSource('drive', 'sm') ?? ''" alt="" aria-hidden="true"></button><button type="button" role="treeitem" :aria-current="isActiveDrive(drive.target) ? 'page' : undefined" class="drive-panel__drive-header" @dragover.prevent @drop.stop.prevent="dropOnDrive($event, drive.target)" @keydown="moveTreeFocus" @click="openCatalogDrive(drive.target)"><span>{{ drive.displayName }}</span></button></div>
                        <div v-if="isActiveDrive(drive.target) && isDriveExpanded(drive.target)" class="drive-panel__drive-content">
                            <div v-for="folder in treeFolders" :key="folder.id" class="drive-panel__tree-row drive-panel__tree-row--nested" :style="{ '--drive-depth': folder.depth }">
                                <button v-if="folder.hasChildren" type="button" class="drive-panel__tree-toggle" :aria-label="expandedTreeFolderIds.has(folder.id) ? 'Recolher' : 'Expandir'" :aria-expanded="expandedTreeFolderIds.has(folder.id)" @click="toggleTreeFolder(folder)"><img :src="folderTreeIcon(folder)" alt="" aria-hidden="true"></button>
                                <span v-else class="drive-panel__tree-toggle drive-panel__tree-toggle--empty"><img :src="folderTreeIcon(folder)" alt="" aria-hidden="true"></span>
                                <button type="button" role="treeitem" :aria-current="isCurrent({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId }) ? 'page' : undefined" :class="{ 'drive-panel__tree-item--active': isCurrent({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId }) }" class="drive-panel__tree-item" :draggable="folder.capabilities.read && !offline" @dragstart="startTreeDrag(folder, $event)" @dragend="emit('dragEnd')" @dragover.prevent @drop.stop.prevent="dropItems($event, folderDestination(folder))" @keydown="moveTreeFocus" @click="openLocation({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId })"><span>{{ folder.displayName }}</span></button>
                            </div>
                            <button type="button" role="treeitem" :aria-current="isTrashLocation ? 'page' : undefined" :class="{ 'drive-panel__tree-item--active': isTrashLocation }" class="drive-panel__tree-item drive-panel__tree-item--trash" @keydown="moveTreeFocus" @click="openLocation({ kind: 'trash', id: null, displayName: t('access.drive.trash'), parentFolderId: null })"><img :src="trashIconSource()" alt="" aria-hidden="true"><span>{{ t('access.drive.trash') }}</span></button>
                        </div>
                    </section>
                    <button v-if="showShared && catalog" type="button" role="treeitem" :aria-current="sharedRootOpen ? 'page' : undefined" :class="{ 'drive-panel__tree-item--active': sharedRootOpen }" class="drive-panel__tree-item" @keydown="moveTreeFocus" @click="openSharedWithMe"><img :src="rasterIconSource('fileSharedWithMe', 'sm') ?? ''" alt="" aria-hidden="true"><span>{{ catalog.sharedWithMe.displayName }}</span></button>
                    <button v-if="showExports && sessionExports.length" type="button" role="treeitem" :aria-current="exportsRootOpen ? 'page' : undefined" :class="{ 'drive-panel__tree-item--active': exportsRootOpen }" class="drive-panel__tree-item" @keydown="moveTreeFocus" @click="openExports"><img :src="rasterIconSource('fileZipExport', 'sm') ?? ''" alt="" aria-hidden="true"><span>{{ exportRootLabel }}</span></button>
                    <template v-if="!isGlobalSurface && !sharedRootOpen">
                        <div v-for="folder in treeFolders" :key="folder.id" class="drive-panel__tree-row drive-panel__tree-row--nested" :style="{ '--drive-depth': folder.depth }">
                            <button v-if="folder.hasChildren" type="button" class="drive-panel__tree-toggle" :aria-label="expandedTreeFolderIds.has(folder.id) ? 'Recolher' : 'Expandir'" :aria-expanded="expandedTreeFolderIds.has(folder.id)" @click="toggleTreeFolder(folder)"><img :src="folderTreeIcon(folder)" alt="" aria-hidden="true"></button>
                            <span v-else class="drive-panel__tree-toggle drive-panel__tree-toggle--empty"><img :src="folderTreeIcon(folder)" alt="" aria-hidden="true"></span>
                            <button type="button" role="treeitem" :aria-current="isCurrent({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId }) ? 'page' : undefined" :class="{ 'drive-panel__tree-item--active': isCurrent({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId }) }" class="drive-panel__tree-item" :draggable="folder.capabilities.read && !offline" @dragstart="startTreeDrag(folder, $event)" @dragend="emit('dragEnd')" @dragover.prevent @drop.stop.prevent="dropItems($event, folderDestination(folder))" @keydown="moveTreeFocus" @click="openLocation({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId })"><span>{{ folder.displayName }}</span></button>
                        </div>
                    </template>
                    <button v-if="!isGlobalSurface" type="button" role="treeitem" :aria-current="isTrashLocation ? 'page' : undefined" :class="{ 'drive-panel__tree-item--active': isTrashLocation }" class="drive-panel__tree-item drive-panel__tree-item--trash" @keydown="moveTreeFocus" @click="openLocation({ kind: 'trash', id: null, displayName: t('access.drive.trash'), parentFolderId: null })"><img :src="trashIconSource()" alt="" aria-hidden="true"><span>{{ t('access.drive.trash') }}</span></button>
                </nav>
                <p v-if="projection" class="drive-panel__usage" :title="t('access.drive.used', { size: formatBytes(projection.usage.totalBytes) })">{{ t('access.drive.used', { size: formatBytes(projection.usage.totalBytes) }) }}</p>
            </aside>
            <div v-if="primaryTreeVisible" class="drive-panel__tree-divider" role="separator" aria-orientation="vertical" :aria-label="t('access.drive.resizeTree')" tabindex="0" @pointerdown="startPrimaryTreeResize" @keydown.arrow-left.prevent="resizePrimaryTreeBy(-24)" @keydown.arrow-right.prevent="resizePrimaryTreeBy(24)" @keydown.home.prevent="resizePrimaryTreeBy(-Number.MAX_SAFE_INTEGER)" @keydown.end.prevent="resizePrimaryTreeBy(Number.MAX_SAFE_INTEGER)" />
            <section class="drive-panel__collection" :aria-busy="loading || undefined" @dragover.prevent @drop.stop.prevent="dropItems($event)">
                <div class="drive-panel__collection-header">
                    <nav class="drive-panel__breadcrumbs" :aria-label="t('access.drive.path')"><button type="button" @click="exportsRootOpen ? openExports() : sharedRootOpen ? openSharedWithMe() : openLocation(rootLocation)">{{ exportsRootOpen ? exportRootLabel : rootLocation.displayName }}</button><template v-if="!exportsRootOpen"><template v-for="crumb in projection?.breadcrumbs ?? []" :key="`${crumb.kind}-${crumb.id}`"><span aria-hidden="true">/</span><button type="button" :aria-current="isCurrent(crumb) ? 'page' : undefined" @click="openLocation(crumb)">{{ crumb.displayName }}</button></template></template></nav>
                    <span ref="locationHeading" class="sr-only" tabindex="-1">{{ collectionLabel }}</span>
                </div>
                <div class="drive-panel__collection-tools">
                    <div class="drive-panel__window-action">
    <UIRinoButton ref="treeTrigger" :accessible-label="(mobileViewport ? treeDrawerOpen : primaryTreeVisible) ? 'access.drive.closeTree' : 'access.drive.openTree'" :aria-expanded="mobileViewport ? treeDrawerOpen : primaryTreeVisible" @click="toggleTree" icon="navBar" />
    <UIRinoButton v-if="showSplit" :accessible-label="secondaryOpen ? 'access.drive.closeSecondPanel' : 'access.drive.openSecondPanel'" :aria-expanded="secondaryOpen" @click="emit('toggleSplit')" icon="fileSidePanel" />
</div>
                    <div class="drive-panel__operation-actions" role="toolbar" :aria-label="t('access.drive.actions')">
                        <UIRinoButton v-if="selectedCount || selectedExportIds.length" class="drive-panel__clear-selection" accessible-label="access.drive.clearSelection" @click="clearSelection" icon="fileCleanSelection" />

                        <UIRinoButton accessible-label="access.drive.refresh" :disabled="offline || loading" @click="refresh" icon="fileRefresh" />
                        <UIRinoButton v-if="!isTrashLocation" accessible-label="access.drive.newFolder" :disabled="!canCreateFolder || offline" @click="openCreateFolder" icon="fileNewFolder" />
                        <UIRinoButton v-if="!isTrashLocation" class="drive-panel__action--overflowable" accessible-label="access.drive.upload" :disabled="!canUpload" @click="chooseUploads" icon="driveUpload" />
                        <UIRinoButton class="drive-panel__action--overflowable" accessible-label="access.drive.download" :disabled="!canDownloadSelection || offline || exporting" @click="downloadSelectedItem" icon="driveDownload" />
                        <template v-if="isTrashLocation"><UIRinoButton class="drive-panel__action--overflowable" accessible-label="access.drive.restore" :disabled="!selectedCount || offline || restoringItems" @click="restoreSelectedItems" icon="fileRestore" /><UIRinoButton class="drive-panel__action--overflowable" accessible-label="access.drive.release" :disabled="!selectedCount || offline" @click="openReleaseConfirmation" icon="trashBurn" /></template>
                        <template v-else><UIRinoButton class="drive-panel__action--overflowable" accessible-label="access.drive.move" :disabled="!canMoveSelection || offline" @click="openMoveDialog" icon="fileMove" /><UIRinoButton class="drive-panel__action--overflowable" accessible-label="access.drive.moveToTrash" :disabled="!canTrashSelection || offline" @click="openTrashConfirmation" icon="fileDelete" /></template>
                        <UIRinoButton class="drive-panel__action--overflowable" accessible-label="access.drive.details" :disabled="selectedItem === null || offline" @click="openDetails" icon="fileDetailPanel" />
                        <UiActionPopover class="drive-panel__action-overflow" label="access.drive.moreActions"><UIRinoButton v-if="!isTrashLocation" accessible-label="access.drive.upload" :disabled="!canUpload" @click="chooseUploads" icon="driveUpload" /><UIRinoButton accessible-label="access.drive.download" :disabled="!canDownloadSelection || offline || exporting" @click="downloadSelectedItem" icon="driveDownload" /><template v-if="isTrashLocation"><UIRinoButton accessible-label="access.drive.restore" :disabled="!selectedCount || offline || restoringItems" @click="restoreSelectedItems" icon="fileRestore" /><UIRinoButton accessible-label="access.drive.release" :disabled="!selectedCount || offline" @click="openReleaseConfirmation" icon="trashBurn" /></template><template v-else><UIRinoButton accessible-label="access.drive.move" :disabled="!canMoveSelection || offline" @click="openMoveDialog" icon="fileMove" /><UIRinoButton accessible-label="access.drive.moveToTrash" :disabled="!canTrashSelection || offline" @click="openTrashConfirmation" icon="fileDelete" /></template><UIRinoButton accessible-label="access.drive.details" :disabled="selectedItem === null || offline" @click="openDetails" icon="fileDetailPanel" /></UiActionPopover>
                    </div>
                    <div class="drive-panel__collection-tools-end"><UiControlGroup class="drive-panel__view-modes" :label="t('access.drive.viewMode')">
    <UIRinoButton v-for="mode in [{ id: 'grid', label: 'access.drive.grid', icon: 'fileGrade' }, { id: 'list', label: 'access.drive.list', icon: 'fileList' }, { id: 'details', label: 'access.drive.detailsView', icon: 'fileDetails' }]" :key="mode.id" class="drive-panel__view-mode" :accessible-label="mode.label" :icon="mode.icon" :toggle-group="viewModeGroup" :selected="viewMode === mode.id" @update:selected="setViewMode(mode.id as ViewMode, $event)" />
</UiControlGroup></div>
                </div>
                <div class="drive-panel__collection-body">
                    <div v-if="exportsRootOpen" class="drive-panel__items" :class="`drive-panel__items--${viewMode}`" role="list" aria-label="Exportações temporárias"><button v-for="entry in sessionExports" :key="entry.exportId" type="button" class="drive-panel__item" :class="{ 'drive-panel__item--selected': selectedExportIds.includes(entry.exportId) }" :aria-pressed="selectedExportIds.includes(entry.exportId)" @click="selectExport(entry, $event)"><img class="drive-panel__item-icon" :src="rasterIconSource('fileZipExport', 'md') ?? ''" alt=""><span class="drive-panel__item-name">Exportação ZIP</span><span class="drive-panel__item-type">{{ exportStateLabel(entry.state) }}</span><span class="drive-panel__item-size">{{ entry.expiresAt ? `Expira ${formatDate(entry.expiresAt)}` : 'Prazo não informado' }}</span><span class="drive-panel__item-date">{{ formatDate(entry.createdAt) }}</span></button></div>
                    <p v-else-if="loading && !hasData" class="drive-panel__loading" aria-live="polite">{{ t('access.drive.loading') }}</p>
                    <p v-else-if="!loading && !items.length" class="drive-panel__empty">{{ t(projection?.location.kind === 'trash' ? 'access.drive.emptyTrash' : 'access.drive.emptyFolder') }}</p>
                    <div v-else class="drive-panel__items" :class="`drive-panel__items--${viewMode}`" role="list" :aria-label="t('access.drive.itemCollection', { name: collectionLabel })">
                        <button v-for="item in items" :key="`${item.kind}-${item.id}`" type="button" class="drive-panel__item" :class="{ 'drive-panel__item--selected': selectedKeys.includes(itemKey(item)) }" :draggable="selectedKeys.includes(itemKey(item))" role="listitem" :aria-pressed="selectedKeys.includes(itemKey(item))" @click="selectItem(item, $event)" @dragstart="startItemDrag(item, $event)" @dragend="emit('dragEnd')" @dragover.prevent @drop.stop.prevent="item.kind === 'folder' ? dropItems($event, folderDestination(item)) : dropItems($event)" @dblclick="openItem(item)" @keydown.enter.prevent="openItem(item)" @keydown.space.prevent="selectItem(item, $event)"><img class="drive-panel__item-icon" :class="{ 'drive-panel__item-icon--folder': item.kind === 'folder' }" :src="itemIconSource(item)" alt="" aria-hidden="true"><span class="drive-panel__item-name">{{ item.displayName }}</span><span class="drive-panel__item-type">{{ itemTypeLabel(item) }}</span><span class="drive-panel__item-size">{{ formatBytes(item.logicalSizeBytes) }}</span><span class="drive-panel__item-date">{{ formatDate(item.modifiedAt) }}</span></button>
                    </div>
                </div>
                <DriveDetailsPanel :open="detailsOpen" :details="details" :loading="detailsLoading" :error="detailsError" @close="clearDetails" @retry="openDetails" @share="openSharing" />
                <ResourceSharingPanel v-if="sharingFolder && target" :target="target" :folder="sharingFolder" @close="sharingFolder = null" />
                <footer class="drive-panel__status-bar" aria-live="polite"><span>{{ statusSummary }}{{ selectionSummary }}</span><span v-for="entry in uploadQueue" :key="entry.id" class="drive-panel__status-upload"><span>{{ entry.name }} · {{ t('access.drive.uploadState', { progress: entry.progress, state: uploadStateLabel(entry.state) }) }}</span><UIRinoButton :accessible-label="entry.state === 'queued' || entry.state === 'uploading' ? 'access.drive.cancelUpload' : 'access.drive.dismissUploadMessage'" icon="btCancel" @click="entry.state === 'queued' || entry.state === 'uploading' ? cancelUpload(entry) : dismissUpload(entry.id)" /></span></footer>
            </section>
        </div>
        <DriveOperationDialog v-model="createFolderOpen" :title="t('access.drive.newFolder')" confirm-label="access.drive.newFolder" cancel-label="access.drive.cancel" :loading="creatingFolder" :error="createFolderError" @submit="createFolder"><label>{{ t('access.drive.folderName') }}<input v-model="createFolderName" maxlength="160" autocomplete="off" /></label></DriveOperationDialog>
        <DriveOperationDialog v-model="trashConfirmationOpen" :title="t('access.drive.trashConfirmationTitle')" confirm-label="access.drive.moveToTrash" cancel-label="access.drive.cancel" :loading="trashingItems" :error="trashError" @submit="trashSelectedItems"><p>{{ t('access.drive.trashConfirmationDescription', { count: selectedCount }, selectedCount) }}</p></DriveOperationDialog>
        <DriveOperationDialog v-model="moveDialogOpen" :title="t('access.drive.move')" confirm-label="access.drive.move" cancel-label="access.drive.cancel" :loading="movingItem" :error="moveError" @submit="moveSelectedItem"><label>{{ t('access.drive.moveDestination') }}<select v-model="moveDestinationId"><option :value="null">{{ rootLocation.displayName }}</option><option v-for="folder in treeFolders.filter((folder) => canMoveTo(folder))" :key="folder.id" :value="folder.id">{{ '— '.repeat(folder.depth) }}{{ folder.displayName }}</option></select></label></DriveOperationDialog>
        <DriveOperationDialog v-model="releaseConfirmationOpen" :title="t('access.drive.releaseTitle')" confirm-label="access.drive.release" cancel-label="access.drive.cancel" :loading="releasingItems" :error="releaseError" @submit="releaseSelectedItems"><p>{{ t('access.drive.releaseDescription', { count: selectedCount }, selectedCount) }}</p></DriveOperationDialog>
    </section>
</template>

<style scoped>

.drive-panel { position: relative; display: flex; min-height: 0; height: 100%; flex-direction: column; gap: var(--space-4); color: var(--color-text-secondary); }

.drive-panel__usage, .drive-panel__empty, .drive-panel__loading { margin: 0; }
.drive-panel__feedback { display: grid; gap: var(--space-2); }
.drive-panel__layout { display: grid; min-height: 0; height: 100%; grid-template-columns: minmax(12rem, 15rem) minmax(0, 1fr); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
.drive-panel__tree { display: grid; min-height: 0; grid-template-rows: auto minmax(0, 1fr) auto; border-right: var(--component-border-width) solid var(--color-border-subtle); background: var(--color-surface-muted); }
.drive-panel__tree-heading { display: flex; align-items: center; justify-content: space-between; padding: var(--space-3); color: var(--color-text-primary); text-transform: uppercase; font-size: var(--font-size-sm); letter-spacing: var(--letter-spacing-wide); }
.drive-panel__tree-list { display: grid; align-content: start; gap: var(--space-1); overflow: auto; padding: 0 var(--space-2) var(--space-3); }
.drive-panel__tree-item { display: flex; min-width: 0; align-items: center; gap: var(--space-2); padding: var(--space-2); border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--color-text-secondary); font: inherit; text-align: left; cursor: pointer; }
.drive-panel__tree-item span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.drive-panel__tree-item:hover, .drive-panel__tree-item--active { background: var(--color-surface-raised); color: var(--color-text-primary); }
.drive-panel__tree-item--active { box-shadow: inset .15rem 0 0 var(--color-action-primary); }
.drive-panel__tree-item--trash { margin-top: var(--space-2); }
.drive-panel__usage { padding: var(--space-3); border-top: var(--component-border-width) solid var(--color-border-subtle); font-size: var(--font-size-sm); }
.drive-panel__collection { display: grid; min-width: 0; min-height: 0; grid-template-rows: auto auto minmax(0, 1fr); }
.drive-panel__collection-header { min-width: 0; padding: var(--space-3) var(--space-4); border-bottom: var(--component-border-width) solid var(--color-border-subtle); }
.drive-panel__breadcrumbs { display: flex; min-width: 0; gap: var(--space-2); overflow: auto; }
.drive-panel__breadcrumbs button { flex: none; padding: 0; border: 0; background: transparent; color: var(--color-action-primary); font: inherit; cursor: pointer; }
.drive-panel__breadcrumbs span { color: var(--color-text-secondary); }
.drive-panel__collection-tools { justify-content: space-between; padding: var(--space-3) var(--space-4); }
.drive-panel__drawer-close { display: none; }
.drive-panel__items { min-height: 0; overflow: auto; padding: var(--space-4); }
.drive-panel__items--grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr)); align-content: start; gap: var(--space-3); }
.drive-panel__item { display: grid; min-width: 0; gap: var(--space-2); padding: var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface-raised); color: var(--color-text-secondary); font: inherit; text-align: left; cursor: pointer; }
.drive-panel__item:hover, .drive-panel__item--selected { border-color: var(--color-action-primary); background: var(--color-surface-muted); }
.drive-panel__item-icon { display: grid; width: calc(2.25rem * var(--component-scale)); height: calc(2.25rem * var(--component-scale)); place-items: center; border-radius: var(--radius-sm); background: var(--color-surface-muted); color: var(--color-text-primary); font-size: var(--font-size-xs); font-weight: var(--font-weight-bold); }
.drive-panel__item-icon--folder { color: var(--color-action-primary); font-size: var(--font-size-xl); }
.drive-panel__item-name { overflow: hidden; color: var(--color-text-primary); font-weight: var(--font-weight-semibold); text-overflow: ellipsis; white-space: nowrap; }
.drive-panel__item-type, .drive-panel__item-size, .drive-panel__item-date { color: var(--color-text-secondary); font-size: var(--font-size-xs); }
.drive-panel__items--list { display: grid; align-content: start; gap: var(--space-1); }
.drive-panel__items--list .drive-panel__item { grid-template-columns: calc(2.25rem * var(--component-scale)) minmax(0, 1fr) auto; align-items: center; padding: var(--space-2) var(--space-3); }
.drive-panel__items--list .drive-panel__item-type, .drive-panel__items--list .drive-panel__item-date { display: none; }
.drive-panel__empty, .drive-panel__loading { display: grid; place-items: center; min-height: 12rem; padding: var(--space-4); text-align: center; }

@media (max-width: 700px) { .drive-panel__layout { display: block; min-height: 0; overflow: visible; border: 0; }
.drive-panel__tree { position: fixed; z-index: 30; inset: 2.5vh 2.5vw; display: none; border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); box-shadow: var(--component-workspace-window-shadow); }
.drive-panel__tree--open { display: grid; }
.drive-panel__tree-heading { padding: var(--space-4); }
.drive-panel__drawer-close { display: inline-flex; }
.drive-panel__collection { min-height: 0; border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
.drive-panel__items { padding: var(--space-3); }
.drive-panel__items--grid { grid-template-columns: repeat(auto-fill, minmax(7rem, 1fr)); gap: var(--space-2); } }

.drive-panel__items--details { display: grid; align-content: start; gap: var(--space-1); }
.drive-panel__items--details .drive-panel__item { grid-template-columns: calc(2.25rem * var(--component-scale)) minmax(10rem, 1fr) minmax(5rem, .5fr) minmax(4rem, .3fr) minmax(8rem, .6fr); align-items: center; padding: var(--space-2) var(--space-3); }
@media (max-width: 700px) { .drive-panel__items--details .drive-panel__item { grid-template-columns: calc(2.25rem * var(--component-scale)) minmax(0, 1fr) auto; }
.drive-panel__items--details .drive-panel__item-type, .drive-panel__items--details .drive-panel__item-date { display: none; } }
.drive-panel__collection { position: relative; }

/* Navegação de desktop: a árvore mantém-se dentro do painel e tem largura ajustável. */
.drive-panel__layout { grid-template-columns: minmax(7.5rem, var(--drive-primary-tree-width, 18%)) var(--space-2) minmax(0, 1fr); }
.drive-panel__layout--tree-hidden { grid-template-columns: minmax(0, 1fr); }
.drive-panel__layout--tree-hidden .drive-panel__tree { display: none; }
.drive-panel__tree { border-right: 0; }
.drive-panel__tree-divider { position: relative; z-index: 1; display: grid; min-height: 100%; place-items: center; cursor: col-resize; touch-action: none; }
.drive-panel__tree-divider::before { width: var(--component-border-width); height: calc(100% - var(--space-4)); border-radius: var(--radius-pill); background: var(--color-border-subtle); content: ''; transition: background-color var(--duration-feedback) var(--easing-standard), width var(--duration-feedback) var(--easing-standard); }
.drive-panel__tree-divider:hover::before, .drive-panel__tree-divider:focus-visible::before { width: calc(var(--component-border-width) * 2); background: var(--color-action-primary); }
.drive-panel__tree-row { display: flex; min-width: 0; align-items: center; }
.drive-panel__tree-row--nested { position: relative; margin-left: calc(var(--space-2) + var(--drive-depth) * var(--space-3)); border-left: var(--component-border-width) solid color-mix(in srgb, var(--color-border-subtle) 70%, transparent); padding-left: var(--space-1); }
.drive-panel__tree-toggle { display: inline-grid; width: var(--control-height-sm); height: var(--control-height-sm); flex: 0 0 var(--control-height-sm); place-items: center; padding: 0; border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--color-text-secondary); cursor: pointer; }
.drive-panel__tree-toggle:hover { background: var(--color-surface-raised); color: var(--color-text-primary); }
.drive-panel__tree-toggle--empty { cursor: default; }
.drive-panel__tree-toggle--empty:hover { background: transparent; }
.drive-panel__tree-toggle img { width: var(--icon-size-sm); height: var(--icon-size-sm); object-fit: contain; }
.drive-panel__tree-row .drive-panel__tree-item { flex: 1 1 auto; }
.drive-panel__tree-item > img { width: var(--icon-size-sm); height: var(--icon-size-sm); flex: 0 0 auto; object-fit: contain; }
.drive-panel__drive-section { display: grid; gap: var(--space-1); }
.drive-panel__drive-header { display: flex; min-width: 0; align-items: center; gap: var(--space-2); padding: var(--space-2); border: 0; border-radius: var(--radius-sm); background: color-mix(in srgb, var(--color-surface-raised) 72%, transparent); color: var(--color-text-primary); font: inherit; font-weight: var(--font-weight-semibold); text-align: left; cursor: pointer; }
.drive-panel__drive-header:hover, .drive-panel__drive-section--active .drive-panel__drive-header { background: var(--color-surface-raised); box-shadow: inset .15rem 0 0 var(--color-action-primary); }
.drive-panel__drive-header img { width: var(--icon-size-sm); height: var(--icon-size-sm); flex: 0 0 auto; object-fit: contain; }
.drive-panel__drive-header span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.drive-panel__drive-content { display: grid; gap: var(--space-1); }

.drive-panel__collection-tools { flex-wrap: wrap; align-items: center; gap: var(--space-2); }
.drive-panel__window-action, .drive-panel__operation-actions, .drive-panel__collection-tools-end { display: flex; min-width: 0; flex-wrap: wrap; align-items: center; gap: var(--space-2); }
.drive-panel__operation-actions { margin-left: auto; }
.drive-panel__collection-tools-end { margin-left: var(--space-2); }
.drive-panel__breadcrumbs { font-weight: var(--font-weight-semibold); }
.drive-panel__breadcrumbs button { color: var(--color-text-primary); }
.drive-panel__breadcrumbs button[aria-current='page'] { color: var(--color-action-primary); }
.drive-panel__status-bar { display: flex; min-height: var(--control-height-sm); align-items: center; gap: var(--space-3); overflow: auto; padding: var(--space-2) var(--space-4); border-top: var(--component-border-width) solid var(--color-border-subtle); color: var(--color-text-secondary); font-size: var(--font-size-sm); white-space: nowrap; }
.drive-panel__status-bar > :first-child { color: var(--color-text-primary); }
.drive-panel__status-upload { display: inline-flex; align-items: center; gap: var(--space-2); }

.drive-panel__collection { height: 100%; grid-template-rows: auto auto minmax(0, 1fr) auto; }
.drive-panel__collection-body { display: grid; min-width: 0; min-height: 0; grid-template-rows: minmax(0, 1fr); }
.drive-panel__collection { container-type: inline-size; }
@container (max-width: 56rem) { .drive-panel__operation-actions { flex-wrap: nowrap; }
.drive-panel__operation-actions .drive-panel__action--overflowable { display: none; } }
.drive-panel__items--list { display: grid; align-content: start; gap: var(--space-1); }
.drive-panel__items--list .drive-panel__item { grid-template-columns: calc(2.25rem * var(--component-scale)) minmax(0, 1fr) auto; align-items: center; padding: var(--space-2) var(--space-3); }
@media (max-width: 900px) { .drive-panel__layout { grid-template-columns: minmax(7.5rem, 18%) minmax(0, 1fr); }
.drive-panel__tree-divider { display: none; } }
.drive-panel__drive-content > .drive-panel__tree-item--trash { margin-left: var(--space-4); }
.drive-panel__usage { overflow: hidden; padding: var(--space-2) var(--space-3); font-size: var(--font-size-xs); text-overflow: ellipsis; white-space: nowrap; }
.drive-panel__drive-control { display: flex; min-width: 0; align-items: stretch; gap: var(--space-1); }
.drive-panel__drive-toggle { display: grid; width: var(--control-height-sm); flex: 0 0 var(--control-height-sm); place-items: center; padding: 0; border: 0; border-radius: var(--radius-sm); background: color-mix(in srgb, var(--color-surface-raised) 72%, transparent); cursor: pointer; }
.drive-panel__drive-toggle img { width: var(--icon-size-sm); height: var(--icon-size-sm); object-fit: contain; }
.drive-panel__drive-header { flex: 1 1 auto; }
.drive-panel__tree-item--active, .drive-panel__drive-section--active .drive-panel__drive-header { box-shadow: none; background: color-mix(in srgb, var(--color-action-primary) 14%, var(--color-surface-raised)); color: var(--color-text-primary); }

.drive-panel { min-width: 0; min-height: 0; height: 100%; display: flex; flex-direction: column; gap: var(--space-2); }
.drive-panel__layout { flex: 1 1 0; height: 0; min-width: 0; }
.drive-panel__collection-header, .drive-panel__collection-tools { display: flex; align-items: center; gap: var(--space-2); }
.drive-panel__collection-tools { flex-wrap: nowrap; }
.drive-panel__window-action { flex: 0 0 auto; display: flex; gap: var(--space-2); }
.drive-panel__operation-actions { display: flex; margin-left: auto; min-width: 0; gap: var(--space-2); align-items: center; flex-wrap: nowrap; }
.drive-panel__collection-tools-end { flex: 0 0 auto; }
.drive-panel__collection-body { display: grid; min-width: 0; min-height: 0; grid-template-rows: minmax(0, 1fr); }
.drive-panel__action-overflow { display: none; }
@container (max-width: 56rem) { .drive-panel__action--overflowable { display: none; } .drive-panel__action-overflow { display: block; } }
@container (max-width: 30rem) { .drive-panel__collection-tools { gap: var(--space-1); padding: var(--space-2); overflow-x: auto; } }

</style>
