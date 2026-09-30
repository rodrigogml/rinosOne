<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import UiAlert from '../design-system/UiAlert.vue';
import { rasterIconSource } from '../design-system/rasterIconAssets';
import { loadDriveLocation, loadDriveTree, type DriveCatalogEntry, type DriveItem, type DriveLocation, type DriveLocationProjection, type DriveWorkspaceTarget } from './driveWorkspaceApi';

const props = defineProps<{ catalog: DriveCatalogEntry[] }>();
const emit = defineEmits<{ locationChange: [target: DriveWorkspaceTarget, projection: DriveLocationProjection] }>();
const { t } = useI18n();
const target = ref<DriveWorkspaceTarget | null>(null);
const tree = ref<DriveItem[]>([]);
const projection = ref<DriveLocationProjection | null>(null);
const loading = ref(false);
const error = ref('');

const targetKey = computed(() => target.value?.kind === 'tenant' ? `tenant:${target.value.tenantId}` : target.value?.kind ?? '');
const selectedDrive = computed(() => props.catalog.find((entry) => entry.target.kind === target.value?.kind && (entry.target.kind !== 'tenant' || target.value?.kind !== 'tenant' || entry.target.tenantId === target.value.tenantId)) ?? null);
const root = computed<DriveLocation>(() => ({ kind: 'root', id: null, displayName: selectedDrive.value?.displayName ?? t('access.drive.root'), parentFolderId: null }));
const treeFolders = computed(() => tree.value);
const items = computed(() => projection.value ? [...projection.value.folders, ...projection.value.files] : []);

function targetFromKey(key: string): DriveWorkspaceTarget | null {
    if (key === 'personal') return { kind: 'personal' };
    const tenantId = Number(key.replace('tenant:', ''));
    return Number.isInteger(tenantId) && tenantId > 0 ? { kind: 'tenant', tenantId } : null;
}
async function load(location: DriveLocation = root.value): Promise<void> {
    if (!target.value) return;
    loading.value = true;
    error.value = '';
    try {
        const [nextTree, nextProjection] = await Promise.all([loadDriveTree(target.value), loadDriveLocation(target.value, location)]);
        tree.value = nextTree;
        projection.value = nextProjection;
        emit('locationChange', target.value, nextProjection);
    } catch { error.value = t('access.drive.loadFailed'); }
    finally { loading.value = false; }
}
async function chooseTarget(value: string): Promise<void> {
    const nextTarget = targetFromKey(value);
    if (!nextTarget) return;
    target.value = nextTarget;
    await load();
}
function openFolder(folder: DriveItem): void {
    if (folder.kind === 'folder') void load({ kind: 'folder', id: folder.id, displayName: folder.displayName, parentFolderId: folder.parentFolderId });
}
function itemIcon(item: DriveItem): string { return rasterIconSource(item.kind === 'folder' ? 'folderClose' : 'file', 'md') ?? ''; }

watch(() => props.catalog, (nextCatalog) => {
    if (!target.value && nextCatalog.length) void chooseTarget(nextCatalog[0].target.kind === 'tenant' ? `tenant:${nextCatalog[0].target.tenantId}` : 'personal');
}, { immediate: true });
onMounted(() => { if (!target.value && props.catalog.length) void chooseTarget(props.catalog[0].target.kind === 'tenant' ? `tenant:${props.catalog[0].target.tenantId}` : 'personal'); });
</script>

<template>
    <section class="drive-navigation-pane" :aria-busy="loading || undefined" aria-label="Navegação paralela do Rinos Drive">
        <header class="drive-navigation-pane__header">
            <label>
                <span class="sr-only">Workspace</span>
                <select :value="targetKey" @change="chooseTarget(($event.target as HTMLSelectElement).value)">
                    <option v-for="drive in catalog" :key="drive.target.kind === 'tenant' ? `tenant:${drive.target.tenantId}` : 'personal'" :value="drive.target.kind === 'tenant' ? `tenant:${drive.target.tenantId}` : 'personal'">{{ drive.displayName }}</option>
                </select>
            </label>
            <button type="button" @click="load()">{{ t('access.drive.refresh') }}</button>
        </header>
        <UiAlert v-if="error" tone="error">{{ error }}</UiAlert>
        <nav class="drive-navigation-pane__path" :aria-label="t('access.drive.path')">
            <button type="button" @click="load()">{{ root.displayName }}</button>
            <template v-for="crumb in projection?.breadcrumbs ?? []" :key="`${crumb.kind}-${crumb.id}`"><span>/</span><button type="button" @click="load(crumb)">{{ crumb.displayName }}</button></template>
        </nav>
        <div class="drive-navigation-pane__body">
            <aside><button v-for="folder in treeFolders" :key="folder.id" type="button" @click="openFolder(folder)">▰ {{ folder.displayName }}</button></aside>
            <div class="drive-navigation-pane__collection">
                <p v-if="loading">{{ t('access.drive.loading') }}</p>
                <p v-else-if="!items.length">{{ t('access.drive.emptyFolder') }}</p>
                <button v-for="item in items" :key="`${item.kind}-${item.id}`" type="button" @dblclick="openFolder(item)"><img :src="itemIcon(item)" alt=""><span>{{ item.displayName }}</span></button>
            </div>
        </div>
    </section>
</template>

<style scoped>
.drive-navigation-pane { display: grid; min-width: 0; min-height: 22rem; grid-template-rows: auto auto minmax(0, 1fr); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); background: var(--color-surface-raised); overflow: hidden; }
.drive-navigation-pane__header, .drive-navigation-pane__path { display: flex; gap: var(--space-2); align-items: center; padding: var(--space-3); border-bottom: var(--component-border-width) solid var(--color-border-subtle); }
.drive-navigation-pane__header select, .drive-navigation-pane__header button, .drive-navigation-pane__path button, .drive-navigation-pane__body button { border: 0; background: transparent; color: var(--color-text-secondary); font: inherit; text-align: left; cursor: pointer; }
.drive-navigation-pane__header select { max-width: 100%; color: var(--color-text-primary); font-weight: var(--font-weight-semibold); }.drive-navigation-pane__header button { margin-left: auto; color: var(--color-action-primary); }
.drive-navigation-pane__path { overflow: auto; white-space: nowrap; }.drive-navigation-pane__path button { color: var(--color-action-primary); }.drive-navigation-pane__path span { color: var(--color-text-secondary); }
.drive-navigation-pane__body { display: grid; min-height: 0; grid-template-columns: minmax(8rem, 10rem) minmax(0, 1fr); }.drive-navigation-pane__body aside { display: grid; align-content: start; gap: var(--space-1); overflow: auto; padding: var(--space-2); border-right: var(--component-border-width) solid var(--color-border-subtle); background: var(--color-surface-muted); }.drive-navigation-pane__body aside button, .drive-navigation-pane__collection > button { min-width: 0; padding: var(--space-2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.drive-navigation-pane__body button:hover { color: var(--color-action-primary); }
.drive-navigation-pane__collection { display: grid; align-content: start; grid-template-columns: repeat(auto-fill, minmax(7rem, 1fr)); gap: var(--space-2); overflow: auto; padding: var(--space-3); }.drive-navigation-pane__collection > p { grid-column: 1 / -1; color: var(--color-text-secondary); }.drive-navigation-pane__collection > button { display: grid; gap: var(--space-2); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface-muted); color: var(--color-text-primary); }.drive-navigation-pane__collection img { width: calc(1.75rem * var(--component-scale)); height: calc(1.75rem * var(--component-scale)); }
@media (max-width: 900px) { .drive-navigation-pane { min-height: 18rem; }.drive-navigation-pane__body { grid-template-columns: minmax(7rem, 8rem) minmax(0, 1fr); } }
</style>
