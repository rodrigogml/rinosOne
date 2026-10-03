<script setup lang="ts">
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, toRaw, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { tableFeatures, useTable } from '@tanstack/vue-table';
import { useVirtualizer } from '@tanstack/vue-virtual';
import UiDialog from '../design-system/UiDialog.vue';
import UIRinoButton from '../design-system/UIRinoButton.vue';
import UiControlGroup from '../design-system/UiControlGroup.vue';
import AdvancedFilterBuilder from '../design-system/AdvancedFilterBuilder.vue';
import { createAdvancedFilterGroup, createAdvancedFilterNodeId, type AdvancedFilterGroup, type AdvancedFilterSchema } from '../design-system/advancedFilter';
import { rasterIconSource } from '../design-system/rasterIconAssets';
import type { PeopleCapabilities } from '../tenant/tenantTypes';
import { useWorkspaceStore } from '../workspace/workspaceStore';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';
import { loadPeopleFilterSchema, queryPeople, resolvePeopleSelection, type PersonSort, type PersonSummary } from './peopleApi';
type Action = 'duplicate' | 'edit' | 'view' | 'delete';
type CatalogPerson = PersonSummary & { outsideSearch: boolean };
type ColumnKey = 'displayName' | 'personType' | 'document' | 'status';
const defaultColumnOrder: ColumnKey[] = ['displayName', 'personType', 'document', 'status'];
const defaultColumnWidths: Record<ColumnKey, number> = { displayName: 320, personType: 160, document: 220, status: 140 };
const props = withDefaults(defineProps<{ surface: WorkspaceSurface; capabilities?: PeopleCapabilities }>(), { capabilities: () => ({}) });
const emit = defineEmits<{ create: []; action: [personId: number, action: Action, trigger: HTMLElement] }>();
const { t } = useI18n(); const workspace = useWorkspaceStore(); const root = ref<HTMLElement | null>(null); const scrollArea = ref<HTMLElement | null>(null); const columnMenu = ref<HTMLElement | null>(null); const columnMenuTrigger = ref<HTMLElement | null>(null);
const search = ref(''); const rows = ref<CatalogPerson[]>([]); const total = ref(0); const matchedTotal = ref(0); const hiddenTotal = ref(0); const loading = ref(false); const offline = ref(!navigator.onLine); const filterDialogOpen = ref(false); const searchReplacementOpen = ref(false); const filterSchema = ref<AdvancedFilterSchema | null>(null); const advancedFilter = ref<AdvancedFilterGroup | null>(null); const filterDraft = ref<AdvancedFilterGroup | null>(null); const filterError = ref('');
const sortCriteria = ref<PersonSort[]>([{ column: 'displayName', direction: 'asc' }]); const selected = ref(new Set<number>()); const keepSelection = ref(false); const showSelected = ref(false); const includeHidden = ref(false);
const visibleColumns = ref<Record<ColumnKey, boolean>>({ displayName: true, personType: true, document: true, status: true }); const columnOrder = ref<ColumnKey[]>([...defaultColumnOrder]); const columnWidths = ref<Record<ColumnKey, number>>({ ...defaultColumnWidths }); const visibleColumnOrder = computed(() => columnOrder.value.filter((column) => visibleColumns.value[column])); const columnMenuOpen = ref(false); const message = ref(''); const messageOpen = ref(false); let debounce: ReturnType<typeof setTimeout> | null = null; let queryRevision = 0; let reloadAfterCurrentRequest = false; const chunk = 100;
const selectionCount = computed(() => selected.value.size); const tableWidth = computed(() => 48 + visibleColumnOrder.value.reduce((width, column) => width + columnWidths.value[column], 0)); const funnelIcon = rasterIconSource('funnel', 'sm') ?? '/assets/icons/funnel_24.png';
const table = useTable({
    features: tableFeatures({}),
    columns: [
        { accessorKey: 'displayName', header: 'displayName' },
        { accessorKey: 'personType', header: 'personType' },
        { accessorKey: 'document', header: 'document' },
        { accessorKey: 'status', header: 'status' },
    ],
    data: computed(() => rows.value),
});
const tableRows = computed(() => table.getRowModel().rows as unknown as Array<{ id: string; original: CatalogPerson }>);
const rowVirtualizer = useVirtualizer(computed(() => ({
    count: tableRows.value.length,
    getScrollElement: () => scrollArea.value,
    estimateSize: () => 42,
    getItemKey: (index: number) => tableRows.value[index]?.id ?? String(index),
    overscan: 8,
})));
const virtualRows = computed(() => rowVirtualizer.value.getVirtualItems());
const renderedRows = computed(() => virtualRows.value.length > 0 ? virtualRows.value : tableRows.value.map((row, index) => ({ key: row.id, index, start: index * 42 })));
const virtualHeight = computed(() => Math.max(rowVirtualizer.value.getTotalSize(), tableRows.value.length * 42));
function notify(text: string): void { message.value = text; messageOpen.value = true; }
function queryPayload() { const selectedIds = [...selected.value]; return { search: search.value || undefined, advancedFilter: advancedFilter.value ?? undefined, status: 'ACTIVE' as const, sorts: sortCriteria.value, includeIds: showSelected.value || includeHidden.value ? selectedIds : [], selectedIds, selectedOnly: showSelected.value }; }
async function load(reset = false): Promise<void> { if (props.surface.tenantId === null || offline.value) return; if (reset) queryRevision += 1; if (loading.value) { reloadAfterCurrentRequest = reloadAfterCurrentRequest || reset; return; } const revision = queryRevision; const offset = reset ? 0 : rows.value.length; loading.value = true; try { const result = await queryPeople(props.surface.tenantId, { ...queryPayload(), offset, limit: chunk }); if (revision !== queryRevision) return; rows.value = reset ? result.people : [...rows.value, ...result.people.filter((person) => !rows.value.some((row) => row.id === person.id))]; total.value = result.range.total; matchedTotal.value = result.matchedTotal; hiddenTotal.value = result.hiddenSelectedTotal; } catch (reason) { if (revision === queryRevision) workspace.enqueueNotification({ kind: 'error', messageKey: axios.isAxiosError(reason) && reason.response?.status === 422 && advancedFilter.value !== null ? 'access.people.advancedFilterInvalid' : advancedFilter.value !== null ? 'access.people.advancedFilterLoadFailed' : 'access.people.listRefreshFailed', persistent: true }); } finally { loading.value = false; if (reloadAfterCurrentRequest) { reloadAfterCurrentRequest = false; void load(); } } }
function resetQuery(clearSelection = true): void { if (clearSelection && !keepSelection.value) selected.value = new Set(); void load(true); }
function runSearch(): void { if (advancedFilter.value !== null) { searchReplacementOpen.value = true; return; } resetQuery(); }
function confirmSearchReplacement(): void { advancedFilter.value = null; searchReplacementOpen.value = false; resetQuery(); }
async function openAdvancedFilter(): Promise<void> { if (props.surface.tenantId === null) return; filterError.value = ''; try { filterSchema.value ??= await loadPeopleFilterSchema(props.surface.tenantId); filterDraft.value = structuredClone(toRaw(advancedFilter.value ?? createAdvancedFilterGroup())); if (filterDraft.value.children.length === 0) filterDraft.value.children.push({ kind: 'condition', id: createAdvancedFilterNodeId(), field: 'displayName', operator: 'CONTAINS', value: '' }); filterDialogOpen.value = true; } catch { notify(t('access.advancedFilter.schemaLoadFailed')); } }
function invalidAdvancedFilter(node: AdvancedFilterGroup, scope: string | null = null): boolean {
    const currentScope = node.matchMode === 'SAME_RECORD' ? node.relation : scope;
    if (node.children.length === 0 || (node.matchMode === 'SAME_RECORD' && currentScope === null)) return true;
    return node.children.some((child) => child.kind === 'group' ? invalidAdvancedFilter(child, currentScope) : filterSchema.value?.fields.some((field) => field.key === child.field && (currentScope === null || field.relation === currentScope)) !== true);
}
function applyAdvancedFilter(): void { if (filterDraft.value === null) return; if (invalidAdvancedFilter(filterDraft.value)) { filterError.value = t('access.advancedFilter.incompatibleScope'); return; } filterError.value = ''; if (search.value) search.value = ''; advancedFilter.value = structuredClone(toRaw(filterDraft.value)); filterDialogOpen.value = false; resetQuery(); }
function sortIndex(column: ColumnKey): number { return sortCriteria.value.findIndex((sort) => sort.column === column); }
function sortDirection(column: ColumnKey): 'asc' | 'desc' | null { return sortCriteria.value[sortIndex(column)]?.direction ?? null; }
function toggleSort(column: ColumnKey, event: MouseEvent): void {
    const currentIndex = sortIndex(column);
    const current = currentIndex >= 0 ? sortCriteria.value[currentIndex] : null;
    if (!event.shiftKey) {
        sortCriteria.value = currentIndex === 0
            ? [{ column, direction: current?.direction === 'asc' ? 'desc' : 'asc' }]
            : [{ column, direction: 'asc' }];
    } else if (currentIndex === 0) {
        sortCriteria.value = [{ column, direction: current?.direction === 'asc' ? 'desc' : 'asc' }, ...sortCriteria.value.slice(1)];
    } else {
        sortCriteria.value = [{ column, direction: current?.direction ?? 'asc' }, ...sortCriteria.value.filter((sort) => sort.column !== column)].slice(0, 3);
    }
    resetQuery();
}
function toggleSelected(id: number): void { const next = new Set(selected.value); next.has(id) ? next.delete(id) : next.add(id); selected.value = next; }
async function selectAll(): Promise<void> { if (props.surface.tenantId === null) return; try { const result = await resolvePeopleSelection(props.surface.tenantId, { search: search.value || undefined, advancedFilter: advancedFilter.value ?? undefined, status: 'ACTIVE' }); if (result.exceedsLimit) { notify(t('access.people.selectionLimit', { count: result.total })); return; } selected.value = new Set(result.ids); } catch { notify(t('access.people.selectionFailed')); } }
function clearSelection(): void { if (!selected.value.size) { notify(t('access.people.noSelection')); return; } selected.value = new Set(); showSelected.value = false; includeHidden.value = false; resetQuery(false); }
function request(action: Action, event: Event, id?: number): void { const ids = id ? [id] : [...selected.value]; if (ids.length !== 1) { notify(ids.length === 0 ? t('access.people.noSelectionForAction', { action: t(`access.people.${action}`) }) : t('access.people.multipleSelectionForAction', { action: t(`access.people.${action}`) })); return; } emit('action', ids[0], action, event.currentTarget as HTMLElement); }
function displayColumnValue(column: ColumnKey, person: CatalogPerson): string { if (column === 'status') return person.status === 'ACTIVE' ? t('access.people.active') : t('access.people.inactive'); if (column === 'document') return person.document ?? '—'; return String(person[column]); }
function handleScroll(): void { const element = scrollArea.value; if (element && !loading.value && rows.value.length < total.value && element.scrollTop + element.clientHeight >= element.scrollHeight - 240) void load(); }
const columnsStorageKey = 'rinos.data-grid.tenant.people.columns.v2';
function saveColumns(): void { localStorage.setItem(columnsStorageKey, JSON.stringify({ visible: visibleColumns.value, order: columnOrder.value, widths: columnWidths.value })); }
function restoreColumns(): void { visibleColumns.value = { displayName: true, personType: true, document: true, status: true }; columnOrder.value = [...defaultColumnOrder]; columnWidths.value = { ...defaultColumnWidths }; localStorage.removeItem(columnsStorageKey); }
function toggleColumn(column: ColumnKey): void { if (visibleColumns.value[column] && visibleColumnOrder.value.length === 1) { notify(t('access.people.columnsNeedOneVisible')); return; } visibleColumns.value = { ...visibleColumns.value, [column]: !visibleColumns.value[column] }; saveColumns(); }
const draggedColumn = ref<ColumnKey | null>(null);
function startColumnDrag(column: ColumnKey, event: DragEvent): void { draggedColumn.value = column; event.dataTransfer?.setData('text/plain', column); if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'; }
function dropColumn(target: ColumnKey): void { const source = draggedColumn.value; draggedColumn.value = null; if (source === null || source === target) return; const order = [...columnOrder.value]; order.splice(order.indexOf(source), 1); order.splice(order.indexOf(target), 0, source); columnOrder.value = order; saveColumns(); }
let stopResizing: (() => void) | null = null;
function startColumnResize(column: ColumnKey, event: PointerEvent): void {
    const startX = event.clientX; const columnWidth = columnWidths.value[column];
    const move = (pointer: PointerEvent): void => { columnWidths.value = { ...columnWidths.value, [column]: Math.max(120, columnWidth + pointer.clientX - startX) }; };
    const stop = (): void => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', stop); stopResizing = null; saveColumns(); };
    stopResizing?.(); stopResizing = stop; window.addEventListener('pointermove', move); window.addEventListener('pointerup', stop, { once: true });
}
watch([showSelected, includeHidden], () => resetQuery(false));
const goOnline = (): void => { offline.value = false; void load(true); }; const goOffline = (): void => { offline.value = true; };
function closeColumnsWhenClickingOutside(event: PointerEvent): void { const target = event.target; if (!(target instanceof Node) || !columnMenuOpen.value || columnMenu.value?.contains(target) || columnMenuTrigger.value?.contains(target)) return; columnMenuOpen.value = false; }
onMounted(() => { const stored = localStorage.getItem(columnsStorageKey); if (stored) try { const preferences = JSON.parse(stored) as { visible?: Record<ColumnKey, boolean>; order?: ColumnKey[]; widths?: Record<ColumnKey, number> }; if (preferences.visible) visibleColumns.value = { ...visibleColumns.value, ...preferences.visible }; if (preferences.order?.length === defaultColumnOrder.length && preferences.order.every((column) => defaultColumnOrder.includes(column))) columnOrder.value = preferences.order; if (preferences.widths) columnWidths.value = { ...columnWidths.value, ...preferences.widths }; } catch { /* defaults are safe */ } window.addEventListener('online', goOnline); window.addEventListener('offline', goOffline); window.addEventListener('pointerdown', closeColumnsWhenClickingOutside); void load(true); }); onBeforeUnmount(() => { if (debounce) clearTimeout(debounce); stopResizing?.(); window.removeEventListener('online', goOnline); window.removeEventListener('offline', goOffline); window.removeEventListener('pointerdown', closeColumnsWhenClickingOutside); }); async function focus(): Promise<void> { await nextTick(); root.value?.focus(); } defineExpose({ refresh: () => load(true), focus });
</script>
<template>
    <section ref="root" class="people-catalog" tabindex="-1" :aria-label="t('access.people.title')">
        <header class="people-catalog__toolbar">
            <UiControlGroup class="people-catalog__search-tools" label="Pesquisar pessoas">
                <label class="people-catalog__search">
                    <span class="sr-only">{{ t('access.people.search') }}</span>
                    <input v-model="search" type="search" :placeholder="t('access.people.searchHint')" @keydown.enter.prevent="runSearch">
                </label>
                <UIRinoButton command="search" @click="runSearch" />
                <UIRinoButton class="people-catalog__filter-trigger" command="filters" :selected="advancedFilter !== null" toggle @click="openAdvancedFilter" />
            </UiControlGroup>
            <div class="people-catalog__toolbar-actions">
                <UiControlGroup class="people-catalog__selection-tools" :label="t('access.people.actions')">
                    <UIRinoButton command="keepSelection" v-model:selected="keepSelection" toggle />
                    <UIRinoButton command="showSelected" v-model:selected="showSelected" toggle />
                    <UIRinoButton command="includeHidden" v-model:selected="includeHidden" toggle />
                    <UIRinoButton command="clearSelection" @click="clearSelection" />
                </UiControlGroup>
                <span ref="columnMenuTrigger"><UIRinoButton command="columns" :aria-expanded="columnMenuOpen" @click="columnMenuOpen = !columnMenuOpen" /></span>
            </div>
            <aside v-if="columnMenuOpen" ref="columnMenu" class="people-catalog__columns" :aria-label="t('access.people.columns')">
                <p class="people-catalog__columns-caption">{{ t('access.people.columns') }}</p>
                <button v-for="column in defaultColumnOrder" :key="column" class="people-catalog__column-toggle" :class="{ 'people-catalog__column-toggle--active': visibleColumns[column] }" type="button" :aria-pressed="visibleColumns[column]" @click="toggleColumn(column)"><span class="people-catalog__column-toggle-mark" aria-hidden="true">{{ visibleColumns[column] ? '✓' : '' }}</span>{{ t(`access.people.${column}`) }}</button>
                <UIRinoButton variant="secondary" @click="restoreColumns" label="access.people.restoreColumns" />
            </aside>
        </header>
        <p v-if="offline" class="people-catalog__feedback" role="status">{{ t('access.people.offline') }}</p>
        <div ref="scrollArea" class="people-catalog__grid" @scroll="handleScroll">
            <table :style="{ width: `${tableWidth}px`, minWidth: '100%' }">
                <thead><tr><th class="people-catalog__selection-column"><input type="checkbox" :checked="total > 0 && selectionCount === total" :aria-label="t('access.people.selectAll')" @change="selectAll"></th><th v-for="column in visibleColumnOrder" :key="column" :style="{ width: `${columnWidths[column]}px` }" draggable="true" @dragstart="startColumnDrag(column, $event)" @dragover.prevent @drop="dropColumn(column)"><button class="people-catalog__sort-trigger" type="button" :aria-sort="sortIndex(column) === 0 ? sortDirection(column) === 'asc' ? 'ascending' : 'descending' : undefined" @click="toggleSort(column, $event)"><span>{{ t(`access.people.${column}`) }}</span><span v-if="sortIndex(column) >= 0" class="people-catalog__sort-state"><span class="people-catalog__sort-direction" aria-hidden="true">{{ sortDirection(column) === 'asc' ? '▲' : '▼' }}</span><span class="people-catalog__sort-priority">{{ sortIndex(column) + 1 }}</span></span></button><button class="people-catalog__column-resizer" type="button" :aria-label="`Redimensionar ${t(`access.people.${column}`)}`" @click.stop @pointerdown.stop.prevent="startColumnResize(column, $event)"><span aria-hidden="true" /></button></th></tr></thead>
                <tbody :style="{ height: `${virtualHeight}px` }"><tr v-for="virtualRow in renderedRows" :key="String(virtualRow.key)" :class="{ 'people-catalog__row--outside': tableRows[virtualRow.index]?.original.outsideSearch }" :style="{ transform: `translateY(${virtualRow.start}px)` }" @dblclick="request('view', $event, tableRows[virtualRow.index]!.original.id)"><td class="people-catalog__selection-column"><input type="checkbox" :checked="selected.has(tableRows[virtualRow.index]!.original.id)" :aria-label="tableRows[virtualRow.index]!.original.displayName" @change="toggleSelected(tableRows[virtualRow.index]!.original.id)"></td><td v-for="column in visibleColumnOrder" :key="column" :style="{ width: `${columnWidths[column]}px` }">{{ displayColumnValue(column, tableRows[virtualRow.index]!.original) }}<sup v-if="column === 'displayName' && tableRows[virtualRow.index]!.original.outsideSearch">1</sup></td></tr><tr v-if="!loading && rows.length === 0"><td :colspan="visibleColumnOrder.length + 1">{{ t('access.people.empty') }}</td></tr></tbody>
            </table>
            <p v-if="loading" role="status">{{ t('access.people.loading') }}</p>
        </div>
        <footer class="people-catalog__status" role="status">{{ t('access.people.results', { count: matchedTotal }, matchedTotal) }}<template v-if="selectionCount"> · {{ t('access.people.selectedCount', { count: selectionCount }, selectionCount) }}</template><template v-if="hiddenTotal"> · {{ t('access.people.hiddenSelected', { count: hiddenTotal }, hiddenTotal) }}¹</template><small v-if="hiddenTotal">¹ {{ t('access.people.hiddenSelectedNote') }}</small></footer>
        <UiControlGroup class="people-catalog__actions" :label="t('access.people.actions')">
            <UIRinoButton v-if="capabilities.canCreatePeople" command="insert" @click="emit('create')" />
            <UIRinoButton v-if="capabilities.canDuplicatePeople" command="duplicate" @click="request('duplicate', $event)" />
            <UIRinoButton v-if="capabilities.canUpdatePeople" command="alter" @click="request('edit', $event)" />
            <UIRinoButton command="view" @click="request('view', $event)" />
            <UIRinoButton v-if="capabilities.canDeletePeople" command="delete" @click="request('delete', $event)" />
        </UiControlGroup>
        <UiDialog v-model="messageOpen" contained :title="t('access.people.actionNeedsSelection')"><p>{{ message }}</p><UIRinoButton variant="secondary" @click="messageOpen = false" label="access.people.closeMessage" /></UiDialog>
        <UiDialog v-model="searchReplacementOpen" contained :title="t('access.people.replaceAdvancedFilterTitle')"><p>{{ t('access.people.replaceAdvancedFilterDescription') }}</p><footer class="people-catalog__filter-dialog-actions"><UIRinoButton command="cancel" @click="searchReplacementOpen = false" /><UIRinoButton @click="confirmSearchReplacement" variant="primary" label="access.people.replaceAdvancedFilterConfirm" /></footer></UiDialog>
        <UiDialog v-model="filterDialogOpen" contained wide fluid window-like :icon-src="funnelIcon" :subtitle="t('access.advancedFilter.description')" :title="t('access.advancedFilter.title')"><div class="people-catalog__filter-dialog-content"><p v-if="filterError" role="alert">{{ filterError }}</p><AdvancedFilterBuilder v-if="filterSchema !== null && filterDraft !== null" v-model="filterDraft" :schema="filterSchema" /><footer class="people-catalog__filter-dialog-actions"><UIRinoButton command="cancel" @click="filterDialogOpen = false" /><UIRinoButton @click="applyAdvancedFilter" variant="primary" icon="btConfirm" label="access.advancedFilter.apply" /></footer></div></UiDialog>
    </section>
</template>
<style scoped>
.people-catalog {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto auto;
    gap: var(--space-3);
    min-block-size: 100%;
    block-size: 100%;
    position: relative;
}
.people-catalog__toolbar, .people-catalog__toolbar-actions, .people-catalog__actions { display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap; }
.people-catalog__toolbar { min-inline-size: 0; justify-content: end; }
.people-catalog__search-tools { width: min(100%, 24rem); min-inline-size: 0; flex: 0 1 24rem; }
.people-catalog__toolbar-actions { flex: 0 1 auto; }
.people-catalog__search { display: flex; flex: 1 1 auto; align-items: center; min-inline-size: 0; border: 0; border-radius: 0; background: var(--component-field-background); }
.people-catalog__search input { min-inline-size: 0; min-height: var(--control-height-md); flex: 1; border: 0; outline: 0; background: transparent; color: var(--color-text-primary); padding: var(--space-2) var(--space-3); font: inherit; }
.people-catalog__search input::placeholder { font-style: italic; color: var(--color-text-secondary); }
.people-catalog__search:focus-within { border-color: var(--color-focus-ring); }
.people-catalog__search-tools:focus-within { outline: 3px solid color-mix(in srgb, var(--color-focus-ring) 20%, transparent); outline-offset: 0; border-radius: var(--radius-md); }
.people-catalog__search img, .people-catalog__search-tools img, .people-catalog__toolbar-actions img, .people-catalog__actions img { width: var(--icon-size-md); height: var(--icon-size-md); object-fit: contain; }
.people-catalog__columns { position: absolute; z-index: 2; inset-block-start: calc(var(--control-height-md) + var(--space-2)); inset-inline-end: 0; display: grid; min-inline-size: 14rem; gap: var(--space-2); padding: var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface-raised); box-shadow: var(--shadow-float); }
.people-catalog__columns-caption { margin: 0; color: var(--color-text-secondary); font-size: var(--font-size-xs); font-weight: var(--font-weight-bold); letter-spacing: var(--letter-spacing-wide); text-transform: uppercase; }
.people-catalog__column-toggle { display: flex; min-height: var(--control-height-sm); align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-3); border: var(--component-border-width) solid transparent; border-radius: var(--radius-sm); background: transparent; color: var(--color-text-secondary); font: inherit; font-weight: var(--font-weight-medium); text-align: start; cursor: pointer; }
.people-catalog__column-toggle:hover { border-color: var(--color-border-subtle); background: var(--color-surface-muted); color: var(--color-text-primary); }
.people-catalog__column-toggle-mark { display: inline-grid; inline-size: 1.25rem; block-size: 1.25rem; flex: 0 0 auto; place-items: center; border: var(--component-border-width) solid var(--color-border-strong); border-radius: var(--radius-sm); color: var(--color-action-primary); font-weight: var(--font-weight-bold); }
.people-catalog__column-toggle--active .people-catalog__column-toggle-mark { border-color: var(--color-action-primary); background: var(--color-action-primary); color: var(--color-action-primary-content); }
.people-catalog__grid { min-block-size: 0; overflow: auto; border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg) var(--radius-lg) 0 0; background: var(--color-surface); }
table { inline-size: 100%; border-collapse: collapse; table-layout: fixed; }
thead, tbody { display: block; inline-size: 100%; }
thead { position: sticky; z-index: 1; inset-block-start: 0; background: color-mix(in srgb, var(--color-surface-muted) 72%, var(--color-surface-raised)); box-shadow: 0 1px 0 var(--color-border-strong); }
thead tr, tbody tr { display: table; inline-size: 100%; table-layout: fixed; }
tbody { position: relative; background: var(--color-surface); }
tbody tr { position: absolute; inset-inline-start: 0; background: var(--color-surface); }
tbody tr:nth-child(even) { background: color-mix(in srgb, var(--color-surface-muted) 48%, var(--color-surface)); }
tbody tr:hover { background: color-mix(in srgb, var(--color-action-primary) var(--color-mix-subtle), var(--color-surface-muted)); }
.people-catalog__selection-column { inline-size: 3rem; }
th, td { padding: var(--space-2) var(--space-3); border-bottom: var(--component-border-width) solid var(--color-border-subtle); color: var(--color-text-primary); text-align: start; white-space: nowrap; }
th { position: relative; color: var(--color-text-primary); font-size: var(--font-size-sm); }
.people-catalog__sort-trigger { display: flex; inline-size: calc(100% - var(--space-3)); align-items: center; justify-content: space-between; gap: var(--space-2); padding: var(--space-0); border: 0; background: transparent; color: inherit; font: inherit; font-weight: var(--font-weight-bold); text-align: start; cursor: pointer; }
.people-catalog__sort-state { display: inline-flex; align-items: center; gap: var(--space-1); color: var(--color-action-primary); }
.people-catalog__sort-direction { font-size: var(--font-size-xs); }
.people-catalog__sort-priority { display: inline-grid; inline-size: 1.1rem; block-size: 1.1rem; place-items: center; border-radius: var(--radius-pill); background: var(--color-action-primary); color: var(--color-action-primary-content); font-size: .65rem; line-height: 1; }
.people-catalog__column-resizer { position: absolute; inset-block: var(--space-1); inset-inline-end: var(--space-0); display: grid; inline-size: var(--space-3); place-items: center; padding: var(--space-0); border: 0; background: transparent; cursor: col-resize; touch-action: none; }
.people-catalog__column-resizer span { inline-size: var(--component-border-width); block-size: 100%; border-radius: var(--radius-pill); background: var(--color-border-strong); }
.people-catalog__column-resizer:hover span, .people-catalog__column-resizer:focus-visible span { inline-size: calc(var(--component-border-width) * 2); background: var(--color-action-primary); }
.people-catalog__row--outside { font-style: italic; }
.people-catalog__status { z-index: 1; display: flex; flex-wrap: wrap; gap: var(--space-1); margin-block-start: calc(var(--space-3) * -1); padding: var(--space-2) var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-top: 0; border-radius: 0 0 var(--radius-lg) var(--radius-lg); background: var(--color-surface-muted); color: var(--color-text-secondary); }
.people-catalog__status small { flex-basis: 100%; }
.people-catalog__filter-dialog-content { display: grid; grid-template-rows: minmax(0, 1fr) auto; min-block-size: 0; gap: var(--space-3); overflow: hidden; }
.people-catalog :deep(.ui-dialog-backdrop--contained) { inset: calc(var(--component-workspace-surface-padding) * -1); }
.people-catalog__filter-dialog-content > [role='alert'] { margin: 0; color: var(--color-danger); }
.people-catalog__filter-dialog-content :deep(.advanced-filter-builder) { min-block-size: 0; }
.people-catalog__filter-dialog-actions { display: flex; justify-content: end; gap: var(--space-2); }
.people-catalog__filter-dialog-actions img { inline-size: var(--icon-size-md); block-size: var(--icon-size-md); object-fit: contain; }
.people-catalog__actions { justify-self: end; }
.people-catalog__feedback { position: absolute; z-index: 3; inset-block-start: calc(var(--control-height-md) + var(--space-4)); inset-inline: var(--space-3); margin: 0; padding: var(--space-2) var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface-raised); box-shadow: var(--shadow-float); }
.sr-only { position: absolute; inline-size: 1px; block-size: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
@media (max-width: 700px) { .people-catalog { block-size: auto; min-block-size: 100%; } .people-catalog__toolbar { justify-content: stretch; } .people-catalog__search-tools, .people-catalog__toolbar-actions, .people-catalog__actions { width: 100%; max-width: 100%; } .people-catalog__search-tools, .people-catalog__toolbar-actions { flex: 1 1 100%; } .people-catalog__search { flex: 1 1 auto; min-inline-size: 0; } .people-catalog__grid { min-block-size: 18rem; } .people-catalog__actions { justify-self: stretch; } }
</style>
