<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import IconButton from '../design-system/IconButton.vue';
import { rasterIconSource } from '../design-system/rasterIconAssets';
import { loadDriveLocation, loadDriveTree, type DriveCatalogEntry, type DriveItem, type DriveLocation, type DriveLocationProjection, type DriveWorkspaceTarget } from './driveWorkspaceApi';

const props = defineProps<{ catalog: DriveCatalogEntry[] }>();
const emit = defineEmits<{ locationChange: [target: DriveWorkspaceTarget, projection: DriveLocationProjection]; transferDrop: []; close: [] }>();
const { t, locale } = useI18n();

type ViewMode = 'grid' | 'list' | 'details';
type TreeFolder = DriveItem & { depth: number; hasChildren: boolean };
const target = ref<DriveWorkspaceTarget | null>(null);
const tree = ref<DriveItem[]>([]);
const projection = ref<DriveLocationProjection | null>(null);
const selectedIds = ref<number[]>([]);
const expandedFolderIds = ref(new Set<number>());
const expandedDriveKeys = ref(new Set<string>());
const loading = ref(false);
const error = ref('');
const viewMode = ref<ViewMode>('grid');
const treeVisible = ref(true);
const bodyElement = ref<HTMLElement | null>(null);
const treeWidth = ref<number | null>(null);

const selectedDrive = computed(() => props.catalog.find((entry) => sameTarget(entry.target, target.value)) ?? null);
const root = computed<DriveLocation>(() => ({ kind: 'root', id: null, displayName: selectedDrive.value?.displayName ?? t('access.drive.root'), parentFolderId: null }));
const items = computed(() => projection.value ? [...projection.value.folders, ...projection.value.files] : []);
const selectedCount = computed(() => selectedIds.value.length);
const selectedItem = computed(() => selectedCount.value === 1 ? items.value.find((item) => item.id === selectedIds.value[0]) ?? null : null);
const itemCounts = computed(() => ({ total: items.value.length, files: items.value.filter((item) => item.kind === 'file').length, folders: items.value.filter((item) => item.kind === 'folder').length }));
const selectedItems = computed(() => items.value.filter((item) => selectedIds.value.includes(item.id)));
const selectedItemCounts = computed(() => ({ total: selectedItems.value.length, files: selectedItems.value.filter((item) => item.kind === 'file').length, folders: selectedItems.value.filter((item) => item.kind === 'folder').length }));
const selectedLogicalSizeBytes = computed(() => selectedItems.value.reduce((total, item) => total + (item.logicalSizeBytes ?? 0), 0));
const statusSummary = computed(() => t('access.drive.statusSummary', itemCounts.value));
const selectionSummary = computed(() => selectedCount.value ? t('access.drive.statusSelectionSummary', { ...selectedItemCounts.value, size: formatBytes(selectedLogicalSizeBytes.value) }) : '');
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
    target.value = nextTarget;
    expandedFolderIds.value = new Set();
    expandedDriveKeys.value = new Set(expandedDriveKeys.value).add(targetKey(nextTarget));
    tree.value = [];
    projection.value = null;
    await load();
}
function openLocation(location: DriveLocation): void { void load(location); }
function openItem(item: DriveItem): void { if (item.kind === 'folder') openLocation({ kind: 'folder', id: item.id, displayName: item.displayName, parentFolderId: item.parentFolderId }); }
function toggleFolder(folder: TreeFolder): void {
    if (!folder.hasChildren) return;
    const next = new Set(expandedFolderIds.value);
    if (next.has(folder.id)) next.delete(folder.id); else next.add(folder.id);
    expandedFolderIds.value = next;
}
function selectItem(item: DriveItem, event?: MouseEvent): void {
    if (event?.ctrlKey || event?.metaKey) selectedIds.value = selectedIds.value.includes(item.id) ? selectedIds.value.filter((id) => id !== item.id) : [...selectedIds.value, item.id];
    else selectedIds.value = [item.id];
}
function clearSelection(): void { selectedIds.value = []; }
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

defineExpose({ refresh: (): Promise<void> => load(projection.value?.location ?? root.value) });
watch(() => props.catalog, (entries) => { if (!target.value && entries.length) void toggleDrive(entries[0].target); }, { immediate: true });
onMounted(() => { if (!target.value && props.catalog.length) void toggleDrive(props.catalog[0].target); });
onBeforeUnmount(stopTreeResize);
</script>

<template>
    <section class="drive-navigation-pane" :aria-busy="loading || undefined" aria-label="Painel secundário do Rinos Drive" @dragover.prevent @drop.prevent="emit('transferDrop')">
        <div ref="bodyElement" class="drive-navigation-pane__layout" :class="{ 'drive-navigation-pane__layout--tree-hidden': !treeVisible }" :style="treeWidth === null ? undefined : { '--drive-secondary-tree-width': `${treeWidth}px` }">
            <aside v-if="treeVisible" class="drive-navigation-pane__tree" :aria-label="t('access.drive.tree')">
                <header class="drive-navigation-pane__tree-heading"><span>{{ t('access.drive.tree') }}</span><button type="button" class="drive-navigation-pane__mobile-close" :aria-label="t('access.drive.closeSecondPanel')" :title="t('access.drive.closeSecondPanel')" @click="emit('close')">×</button></header>
                <nav class="drive-navigation-pane__tree-list" role="tree" :aria-label="t('access.drive.tree')">
                    <section v-for="drive in catalog" :key="targetKey(drive.target)" class="drive-navigation-pane__drive-section" :class="{ 'drive-navigation-pane__drive-section--active': isActiveDrive(drive.target) }">
                        <button type="button" role="treeitem" class="drive-navigation-pane__drive-header" :aria-current="isActiveDrive(drive.target) ? 'page' : undefined" :aria-expanded="isActiveDrive(drive.target) ? driveExpanded(drive.target) : undefined" @click="toggleDrive(drive.target)"><img :src="rasterIconSource('drive', 'sm') ?? ''" alt="" aria-hidden="true"><span>{{ drive.displayName }}</span></button>
                        <div v-if="isActiveDrive(drive.target) && driveExpanded(drive.target)" class="drive-navigation-pane__drive-content">
                            <div v-for="folder in treeFolders" :key="folder.id" class="drive-navigation-pane__tree-row" :style="{ '--drive-depth': folder.depth }"><button v-if="folder.hasChildren" type="button" class="drive-navigation-pane__tree-toggle" :aria-expanded="expandedFolderIds.has(folder.id)" @click="toggleFolder(folder)"><img :src="folderIcon(folder)" alt="" aria-hidden="true"></button><span v-else class="drive-navigation-pane__tree-toggle drive-navigation-pane__tree-toggle--empty"><img :src="folderIcon(folder)" alt="" aria-hidden="true"></span><button type="button" class="drive-navigation-pane__tree-item" :class="{ 'drive-navigation-pane__tree-item--active': projection?.location.kind === 'folder' && projection.location.id === folder.id }" @click="openLocation({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId })"><span>{{ folder.displayName }}</span></button></div>
                            <button type="button" class="drive-navigation-pane__tree-item drive-navigation-pane__tree-item--trash" :class="{ 'drive-navigation-pane__tree-item--active': projection?.location.kind === 'trash' }" @click="openLocation({ kind: 'trash', id: null, displayName: t('access.drive.trash'), parentFolderId: null })"><img :src="trashIcon()" alt="" aria-hidden="true"><span>{{ t('access.drive.trash') }}</span></button>
                        </div>
                    </section>
                </nav>
                <p v-if="projection" class="drive-navigation-pane__usage">{{ t('access.drive.used', { size: formatBytes(projection.usage.totalBytes) }) }}</p>
            </aside>
            <div v-if="treeVisible" class="drive-navigation-pane__tree-divider" :aria-label="t('access.drive.resizeTree')" tabindex="0" @pointerdown="startTreeResize" @keydown.arrow-left.prevent="resizeTreeBy(-20)" @keydown.arrow-right.prevent="resizeTreeBy(20)" @keydown.home.prevent="resizeTreeBy(-Number.MAX_SAFE_INTEGER)" @keydown.end.prevent="resizeTreeBy(Number.MAX_SAFE_INTEGER)" />
            <section class="drive-navigation-pane__collection">
                <header class="drive-navigation-pane__collection-header"><nav class="drive-navigation-pane__breadcrumbs" :aria-label="t('access.drive.path')"><button type="button" @click="openLocation(root)">{{ root.displayName }}</button><template v-for="crumb in projection?.breadcrumbs ?? []" :key="`${crumb.kind}-${crumb.id}`"><span aria-hidden="true">/</span><button type="button" @click="openLocation(crumb)">{{ crumb.displayName }}</button></template></nav></header>
                <div class="drive-navigation-pane__tools"><IconButton :label="t(treeVisible ? 'access.drive.closeTree' : 'access.drive.openTree')" :aria-pressed="treeVisible" @click="treeVisible = !treeVisible"><img :src="rasterIconSource('navBar', 'md') ?? ''" alt=""></IconButton><div class="drive-navigation-pane__actions" role="toolbar" :aria-label="t('access.drive.actions')"><IconButton v-if="selectedCount" :label="t('access.drive.clearSelection')" @click="clearSelection"><img :src="rasterIconSource('fileCleanSelection', 'md') ?? ''" alt=""></IconButton><IconButton :label="t('access.drive.refresh')" :disabled="loading" @click="load()"><img :src="rasterIconSource('fileRefresh', 'md') ?? ''" alt=""></IconButton><IconButton :label="t('access.drive.newFolder')" disabled><img :src="rasterIconSource('fileNewFolder', 'md') ?? ''" alt=""></IconButton><IconButton :label="t('access.drive.upload')" disabled><img :src="rasterIconSource('driveUpload', 'md') ?? ''" alt=""></IconButton><IconButton :label="t('access.drive.download')" :disabled="selectedItem === null"><img :src="rasterIconSource('driveDownload', 'md') ?? ''" alt=""></IconButton><IconButton :label="t('access.drive.move')" :disabled="selectedItem === null"><img :src="rasterIconSource('fileMove', 'md') ?? ''" alt=""></IconButton><IconButton :label="t('access.drive.moveToTrash')" :disabled="selectedItem === null"><img :src="rasterIconSource('dataDelete', 'md') ?? ''" alt=""></IconButton><IconButton :label="t('access.drive.details')" :disabled="selectedItem === null"><img :src="rasterIconSource('fileDetailPanel', 'md') ?? ''" alt=""></IconButton></div><div class="drive-navigation-pane__view-modes" role="group" :aria-label="t('access.drive.viewMode')"><button v-for="mode in [{ id: 'grid', label: t('access.drive.grid'), icon: 'fileGrade' }, { id: 'list', label: t('access.drive.list'), icon: 'fileList' }, { id: 'details', label: t('access.drive.detailsView'), icon: 'fileDetails' }]" :key="mode.id" type="button" class="drive-navigation-pane__view-mode" :class="{ 'drive-navigation-pane__view-mode--active': viewMode === mode.id }" :aria-label="mode.label" :aria-pressed="viewMode === mode.id" :title="mode.label" @click="viewMode = mode.id as ViewMode"><img :src="rasterIconSource(mode.icon, 'md') ?? ''" alt=""></button></div></div>
                <p v-if="loading" class="drive-navigation-pane__empty">{{ t('access.drive.loading') }}</p><p v-else-if="!items.length" class="drive-navigation-pane__empty">{{ t(projection?.location.kind === 'trash' ? 'access.drive.emptyTrash' : 'access.drive.emptyFolder') }}</p><div v-else class="drive-navigation-pane__items" :class="`drive-navigation-pane__items--${viewMode}`" role="list"><button v-for="item in items" :key="`${item.kind}-${item.id}`" type="button" class="drive-navigation-pane__item" :class="{ 'drive-navigation-pane__item--selected': selectedIds.includes(item.id) }" :draggable="selectedIds.includes(item.id)" @click="selectItem(item, $event)" @dblclick="openItem(item)" @dragstart="selectItem(item, $event)"><img :src="itemIcon(item)" alt=""><span>{{ item.displayName }}</span><small>{{ itemTypeLabel(item) }}</small><small>{{ formatBytes(item.logicalSizeBytes) }}</small></button></div>
                <footer class="drive-navigation-pane__status" aria-live="polite"><span>{{ statusSummary }}{{ selectionSummary }}</span></footer>
            </section>
        </div>
    </section>
</template>

<style scoped>
.drive-navigation-pane { min-width: 0; min-height: 0; height: 100%; color: var(--color-text-secondary); }.drive-navigation-pane__layout { display: grid; min-width: 0; min-height: 0; height: 100%; grid-template-columns: minmax(7.5rem, var(--drive-secondary-tree-width, 18%)) var(--space-2) minmax(0, 1fr); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); overflow: hidden; }.drive-navigation-pane__layout--tree-hidden { grid-template-columns: minmax(0, 1fr); }.drive-navigation-pane__tree { display: grid; min-width: 0; min-height: 0; grid-template-rows: auto minmax(0, 1fr) auto; background: var(--color-surface-muted); }.drive-navigation-pane__tree-heading { display: flex; align-items: center; justify-content: space-between; padding: var(--space-3); color: var(--color-text-primary); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); letter-spacing: var(--letter-spacing-wide); text-transform: uppercase; }.drive-navigation-pane__tree-list { display: grid; align-content: start; gap: var(--space-1); overflow: auto; padding: 0 var(--space-2) var(--space-3); }.drive-navigation-pane__drive-section, .drive-navigation-pane__drive-content { display: grid; gap: var(--space-1); }.drive-navigation-pane__drive-header, .drive-navigation-pane__tree-item { display: flex; min-width: 0; align-items: center; gap: var(--space-2); padding: var(--space-2); border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--color-text-secondary); font: inherit; text-align: left; cursor: pointer; }.drive-navigation-pane__drive-header { background: color-mix(in srgb, var(--color-surface-raised) 72%, transparent); color: var(--color-text-primary); font-weight: var(--font-weight-semibold); }.drive-navigation-pane__drive-header:hover, .drive-navigation-pane__drive-section--active .drive-navigation-pane__drive-header { background: var(--color-surface-raised); box-shadow: inset .15rem 0 0 var(--color-action-primary); }.drive-navigation-pane__drive-header img, .drive-navigation-pane__tree-item > img { width: var(--icon-size-sm); height: var(--icon-size-sm); flex: 0 0 auto; object-fit: contain; }.drive-navigation-pane__drive-header span, .drive-navigation-pane__tree-item span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.drive-navigation-pane__tree-row { display: flex; min-width: 0; align-items: center; margin-left: calc(var(--space-2) + var(--drive-depth) * var(--space-3)); border-left: var(--component-border-width) solid color-mix(in srgb, var(--color-border-subtle) 70%, transparent); padding-left: var(--space-1); }.drive-navigation-pane__tree-row .drive-navigation-pane__tree-item { flex: 1 1 auto; }.drive-navigation-pane__tree-item:hover, .drive-navigation-pane__tree-item--active { background: var(--color-surface-raised); color: var(--color-text-primary); }.drive-navigation-pane__tree-item--active { box-shadow: inset .15rem 0 0 var(--color-action-primary); }.drive-navigation-pane__tree-item--trash { margin-top: var(--space-2); }.drive-navigation-pane__tree-toggle { display: inline-grid; width: var(--control-height-sm); height: var(--control-height-sm); flex: 0 0 var(--control-height-sm); place-items: center; padding: 0; border: 0; border-radius: var(--radius-sm); background: transparent; cursor: pointer; }.drive-navigation-pane__tree-toggle img { width: var(--icon-size-sm); height: var(--icon-size-sm); object-fit: contain; }.drive-navigation-pane__tree-toggle--empty { cursor: default; }.drive-navigation-pane__usage { margin: 0; padding: var(--space-3); border-top: var(--component-border-width) solid var(--color-border-subtle); font-size: var(--font-size-sm); }.drive-navigation-pane__tree-divider { display: grid; min-height: 100%; place-items: center; cursor: col-resize; touch-action: none; }.drive-navigation-pane__tree-divider::before { width: var(--component-border-width); height: calc(100% - var(--space-4)); border-radius: var(--radius-pill); background: var(--color-border-subtle); content: ''; }.drive-navigation-pane__collection { display: grid; min-width: 0; min-height: 0; height: 100%; grid-template-rows: auto auto minmax(0, 1fr) auto; }.drive-navigation-pane__collection-header { min-width: 0; padding: var(--space-3) var(--space-4); border-bottom: var(--component-border-width) solid var(--color-border-subtle); }.drive-navigation-pane__breadcrumbs { display: flex; min-width: 0; gap: var(--space-2); overflow: auto; font-weight: var(--font-weight-semibold); }.drive-navigation-pane__breadcrumbs button { flex: none; padding: 0; border: 0; background: transparent; color: var(--color-text-primary); font: inherit; cursor: pointer; }.drive-navigation-pane__breadcrumbs span { color: var(--color-text-secondary); }.drive-navigation-pane__tools { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-3) var(--space-4); }.drive-navigation-pane__actions { display: flex; min-width: 0; flex-wrap: wrap; align-items: center; gap: var(--space-2); margin-left: auto; }.drive-navigation-pane__tools :deep(.ui-icon-button) { border-color: transparent; background: transparent; }.drive-navigation-pane__tools :deep(.ui-icon-button:not(:disabled):hover) { background: var(--color-surface-muted); color: var(--color-action-primary); }.drive-navigation-pane__view-modes { display: flex; gap: var(--space-1); }.drive-navigation-pane__view-mode { position: relative; display: inline-grid; width: var(--control-height-md); height: var(--control-height-md); place-items: center; padding: 0; border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--color-text-secondary); cursor: pointer; }.drive-navigation-pane__view-mode:hover { background: var(--color-surface-muted); color: var(--color-action-primary); }.drive-navigation-pane__view-mode--active { color: var(--color-action-primary); }.drive-navigation-pane__view-mode--active::after { position: absolute; bottom: var(--space-1); left: 50%; width: calc(var(--icon-size-md) * .42); height: calc(var(--component-border-width) * 2); border-radius: var(--radius-pill); background: currentcolor; content: ''; transform: translateX(-50%); }.drive-navigation-pane__view-mode img { width: var(--icon-size-md); height: var(--icon-size-md); object-fit: contain; }.drive-navigation-pane__items { min-height: 0; overflow: auto; padding: var(--space-4); }.drive-navigation-pane__items--grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr)); align-content: start; gap: var(--space-3); }.drive-navigation-pane__item { display: grid; min-width: 0; gap: var(--space-2); padding: var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface-raised); color: var(--color-text-secondary); font: inherit; text-align: left; cursor: pointer; }.drive-navigation-pane__item:hover, .drive-navigation-pane__item--selected { border-color: var(--color-action-primary); background: var(--color-surface-muted); }.drive-navigation-pane__item img { width: calc(2.25rem * var(--component-scale)); height: calc(2.25rem * var(--component-scale)); object-fit: contain; }.drive-navigation-pane__item span { overflow: hidden; color: var(--color-text-primary); font-weight: var(--font-weight-semibold); text-overflow: ellipsis; white-space: nowrap; }.drive-navigation-pane__item small { color: var(--color-text-secondary); }.drive-navigation-pane__items--list, .drive-navigation-pane__items--details { display: grid; align-content: start; gap: var(--space-1); }.drive-navigation-pane__items--list .drive-navigation-pane__item, .drive-navigation-pane__items--details .drive-navigation-pane__item { grid-template-columns: auto minmax(0, 1fr) auto auto; align-items: center; padding: var(--space-2) var(--space-3); }.drive-navigation-pane__items--list .drive-navigation-pane__item small:first-of-type { display: none; }.drive-navigation-pane__empty { display: grid; min-height: 0; place-items: center; margin: 0; padding: var(--space-4); text-align: center; }.drive-navigation-pane__status { display: flex; min-height: var(--control-height-sm); align-items: center; padding: var(--space-2) var(--space-4); border-top: var(--component-border-width) solid var(--color-border-subtle); color: var(--color-text-secondary); font-size: var(--font-size-sm); }.drive-navigation-pane__status span { color: var(--color-text-primary); }.drive-navigation-pane__mobile-close { display: none; }
@media (max-width: 900px) { .drive-navigation-pane__layout { grid-template-columns: minmax(7.5rem, 18%) minmax(0, 1fr); }.drive-navigation-pane__tree-divider { display: none; } }
@media (max-width: 700px) { .drive-navigation-pane { position: fixed; z-index: 70; inset: calc(env(safe-area-inset-top) + 2vh) 2.5vw calc(env(safe-area-inset-bottom) + 2vh); }.drive-navigation-pane__mobile-close { display: inline-grid; width: var(--control-height-sm); height: var(--control-height-sm); place-items: center; padding: 0; border: 0; background: transparent; color: var(--color-text-secondary); font-size: var(--font-size-xl); }.drive-navigation-pane__layout { grid-template-columns: minmax(6rem, 7rem) minmax(0, 1fr); }.drive-navigation-pane__tools { overflow: auto; } }
</style>
