<script setup lang="ts">
import { computed, ref, toRaw } from 'vue';
import type { AdvancedFilterCondition, AdvancedFilterField, AdvancedFilterGroup, AdvancedFilterNode, AdvancedFilterSchema } from './advancedFilter';
import { createAdvancedFilterGroup, createAdvancedFilterNodeId } from './advancedFilter';
import { useI18n } from 'vue-i18n';
import UiMultiSelect from './UiMultiSelect.vue';

defineOptions({ name: 'AdvancedFilterGroup' });
type DropPosition = 'before' | 'after' | 'end';
const props = defineProps<{ modelValue: AdvancedFilterGroup; schema: AdvancedFilterSchema; depth: number; inheritedRelation?: string | null; root?: boolean; moveNode: (draggedId: string, targetGroupId: string, targetNodeId: string | null, position: DropPosition) => void; draggedId: string | null }>();
const emit = defineEmits<{ 'update:modelValue': [node: AdvancedFilterGroup]; remove: []; dragStart: [nodeId: string]; dragEnd: [] }>();
const { t } = useI18n();
const scope = computed(() => props.inheritedRelation ?? (props.modelValue.matchMode === 'SAME_RECORD' ? props.modelValue.relation : null));
const fields = computed(() => scope.value === null ? props.schema.fields : props.schema.fields.filter((field) => field.relation === scope.value));
const relationLabel = computed(() => props.schema.relations.find((relation) => relation.key === scope.value)?.label ?? '');
const canAddGroup = computed(() => props.depth < props.schema.maximumDepth);
const dropTarget = ref<{ nodeId: string | null; position: DropPosition } | null>(null);
function copy(): AdvancedFilterGroup { return structuredClone(toRaw(props.modelValue)); }
function update(mutator: (node: AdvancedFilterGroup) => void): void { const next = copy(); mutator(next); emit('update:modelValue', next); }
function firstField(): AdvancedFilterField | undefined { return fields.value[0]; }
function newCondition(): AdvancedFilterCondition | null { const field = firstField(); return field ? { kind: 'condition', id: createAdvancedFilterNodeId(), field: field.key, operator: field.operators[0], value: field.type === 'ENUM' ? field.options[0]?.value ?? '' : '' } : null; }
function addCondition(): void { const condition = newCondition(); if (condition) update((group) => group.children.push(condition)); }
function addGroup(): void { update((group) => { const child = createAdvancedFilterGroup(); if (scope.value !== null) { child.matchMode = 'SAME_RECORD'; child.relation = scope.value; } const condition = newCondition(); if (condition) child.children.push(condition); group.children.push(child); }); }
function updateChild(index: number, child: AdvancedFilterNode): void { update((group) => { group.children.splice(index, 1, child); }); }
function removeChild(index: number): void { update((group) => { group.children.splice(index, 1); }); }
function updateCondition(index: number, changes: Partial<AdvancedFilterCondition>): void { const child = props.modelValue.children[index]; if (child?.kind !== 'condition') return; updateChild(index, { ...child, ...changes }); }
function changeField(index: number, fieldKey: string): void { const field = props.schema.fields.find((candidate) => candidate.key === fieldKey); if (!field) return; updateCondition(index, { field: field.key, operator: field.operators[0], value: field.type === 'ENUM' ? field.options[0]?.value ?? '' : '' }); }
function fieldFor(condition: AdvancedFilterCondition): AdvancedFilterField | undefined { return props.schema.fields.find((field) => field.key === condition.field); }
function needsValue(condition: AdvancedFilterCondition): boolean { return !['IS_EMPTY', 'IS_NOT_EMPTY'].includes(condition.operator); }
function isCompatible(condition: AdvancedFilterCondition): boolean { return fields.value.some((field) => field.key === condition.field); }
function changeMatchMode(mode: 'ANY_RECORD' | 'SAME_RECORD'): void { update((group) => { group.matchMode = mode; group.relation = mode === 'SAME_RECORD' ? props.schema.relations[0]?.key ?? null : null; }); }
function targetValue(event: Event): string { return (event.target as HTMLInputElement | HTMLSelectElement).value; }
function targetValues(event: Event): string | string[] { const target = event.target as HTMLSelectElement; return target.multiple ? Array.from(target.selectedOptions, (option) => option.value) : target.value; }
function targetChecked(event: Event): boolean { return (event.target as HTMLInputElement).checked; }
function changeCombinator(event: Event): void { update((group) => { group.combinator = targetValue(event) === 'OR' ? 'OR' : 'AND'; }); }
function changeNegated(event: Event): void { update((group) => { group.negated = targetChecked(event); }); }
function changeGroupRelation(event: Event): void { update((group) => { group.relation = targetValue(event); }); }
function changeConditionOperator(index: number, event: Event): void { updateCondition(index, { operator: targetValue(event) as AdvancedFilterCondition['operator'] }); }
function changeConditionValue(index: number, event: Event): void { updateCondition(index, { value: targetValues(event) }); }
function changeConditionValues(index: number, values: string[]): void { updateCondition(index, { value: values }); }
function startDrag(event: DragEvent, nodeId: string): void { event.dataTransfer?.setData('text/plain', nodeId); if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'; emit('dragStart', nodeId); }
function endDrag(): void { dropTarget.value = null; emit('dragEnd'); }
function draggedNodeId(event: DragEvent): string { return props.draggedId ?? event.dataTransfer?.getData('text/plain') ?? ''; }
function markDrop(event: DragEvent, targetNodeId: string): void { if (!draggedNodeId(event) || draggedNodeId(event) === targetNodeId) return; const bounds = (event.currentTarget as HTMLElement).getBoundingClientRect(); dropTarget.value = { nodeId: targetNodeId, position: event.clientY < bounds.top + bounds.height / 2 ? 'before' : 'after' }; }
function clearDrop(): void { dropTarget.value = null; }
function markTail(event: DragEvent): void { if (draggedNodeId(event)) dropTarget.value = { nodeId: null, position: 'end' }; }
function dropOnNode(event: DragEvent, targetNodeId: string): void { const draggedId = draggedNodeId(event); const target = dropTarget.value; clearDrop(); if (!draggedId || draggedId === targetNodeId) return; props.moveNode(draggedId, props.modelValue.id, targetNodeId, target?.nodeId === targetNodeId ? target.position : 'before'); }
function dropAtEnd(event: DragEvent): void { const draggedId = draggedNodeId(event); clearDrop(); if (draggedId) props.moveNode(draggedId, props.modelValue.id, null, 'end'); }
</script>

<template>
    <section class="advanced-filter-group" :class="{ 'advanced-filter-group--root': root }">
        <aside class="advanced-filter-group__trunk"><span v-if="!root" class="advanced-filter-group__drag-handle" draggable="true" :title="t('access.advancedFilter.moveGroup')" :aria-label="t('access.advancedFilter.moveGroup')" @dragstart="startDrag($event, modelValue.id)" @dragend.prevent="endDrag">⠿</span><label><span class="sr-only">{{ t('access.advancedFilter.combinator') }}</span><select :value="modelValue.combinator" @change="changeCombinator"><option value="AND">{{ t('access.advancedFilter.and') }}</option><option value="OR">{{ t('access.advancedFilter.or') }}</option></select></label></aside>
        <div class="advanced-filter-group__body">
            <header class="advanced-filter-group__header">
                <strong>{{ root ? t('access.advancedFilter.filter') : t('access.advancedFilter.group') }}</strong>
                <label><input type="checkbox" :checked="modelValue.negated" @change="changeNegated"> {{ t('access.advancedFilter.not') }}</label>
                <template v-if="inheritedRelation === undefined || inheritedRelation === null">
                    <label>{{ t('access.advancedFilter.matchMode') }}<select :value="modelValue.matchMode" @change="changeMatchMode(targetValue($event) === 'SAME_RECORD' ? 'SAME_RECORD' : 'ANY_RECORD')"><option value="ANY_RECORD">{{ t('access.advancedFilter.anyRecord') }}</option><option value="SAME_RECORD">{{ t('access.advancedFilter.sameRecord') }}</option></select></label>
                    <label v-if="modelValue.matchMode === 'SAME_RECORD'">{{ t('access.advancedFilter.relation') }}<select :value="modelValue.relation ?? ''" @change="changeGroupRelation"><option v-for="relation in schema.relations" :key="relation.key" :value="relation.key">{{ relation.label }}</option></select></label>
                </template>
                <span v-else class="advanced-filter-group__context">{{ t('access.advancedFilter.sameRecordIn', { relation: relationLabel }) }}</span>
                <button v-if="!root" class="ui-icon-button advanced-filter-group__remove" type="button" :title="t('access.advancedFilter.removeGroup')" :aria-label="t('access.advancedFilter.removeGroup')" @click="emit('remove')"><img :src="'/assets/icons/fileDelete_24.png'" alt=""></button>
            </header>
            <p v-if="modelValue.matchMode === 'SAME_RECORD' && !inheritedRelation" class="advanced-filter-group__hint">{{ t('access.advancedFilter.sameRecordHint', { relation: relationLabel }) }}</p>
            <div class="advanced-filter-group__children">
                <template v-for="(child, index) in modelValue.children" :key="child.id">
                    <div v-if="child.kind === 'group'" class="advanced-filter-group__node" :class="{ 'advanced-filter-group__node--drop-before': dropTarget?.nodeId === child.id && dropTarget.position === 'before', 'advanced-filter-group__node--drop-after': dropTarget?.nodeId === child.id && dropTarget.position === 'after' }" @dragover.stop.prevent="markDrop($event, child.id)" @dragleave.stop="clearDrop" @drop.stop.prevent="dropOnNode($event, child.id)"><AdvancedFilterGroup :model-value="child" :schema="schema" :depth="depth + 1" :inherited-relation="scope" :move-node="moveNode" :dragged-id="draggedId" @drag-start="emit('dragStart', $event)" @drag-end="emit('dragEnd')" @update:model-value="updateChild(index, $event)" @remove="removeChild(index)" /></div>
                    <div v-else class="advanced-filter-condition" :class="{ 'advanced-filter-condition--incompatible': !isCompatible(child), 'advanced-filter-condition--drop-before': dropTarget?.nodeId === child.id && dropTarget.position === 'before', 'advanced-filter-condition--drop-after': dropTarget?.nodeId === child.id && dropTarget.position === 'after' }" @dragover.stop.prevent="markDrop($event, child.id)" @dragleave.stop="clearDrop" @drop.stop.prevent="dropOnNode($event, child.id)">
                        <span class="advanced-filter-condition__drag-handle" draggable="true" :title="t('access.advancedFilter.moveCondition')" :aria-label="t('access.advancedFilter.moveCondition')" @dragstart="startDrag($event, child.id)" @dragend.prevent="endDrag">⠿</span>
                        <label><span class="sr-only">{{ t('access.advancedFilter.field') }}</span><select :value="child.field" @change="changeField(index, targetValue($event))"><option v-if="!isCompatible(child)" :value="child.field">{{ fieldFor(child)?.label ?? child.field }} — {{ t('access.advancedFilter.incompatibleField') }}</option><option v-for="field in fields" :key="field.key" :value="field.key">{{ field.label }}</option></select></label>
                        <label><span class="sr-only">{{ t('access.advancedFilter.operator') }}</span><select :value="child.operator" @change="changeConditionOperator(index, $event)"><option v-for="operator in fieldFor(child)?.operators ?? []" :key="operator" :value="operator">{{ t(`access.advancedFilter.operators.${operator}`) }}</option></select></label>
                        <label v-if="needsValue(child)" :key="`${child.field}:${child.operator}`" class="advanced-filter-condition__value"><span class="sr-only">{{ t('access.advancedFilter.value') }}</span><UiMultiSelect v-if="fieldFor(child)?.type === 'ENUM' && ['IN', 'NOT_IN'].includes(child.operator)" :model-value="Array.isArray(child.value) ? child.value : []" :options="fieldFor(child)?.options ?? []" :label="t('access.advancedFilter.value')" @update:model-value="changeConditionValues(index, $event)" /><select v-else-if="fieldFor(child)?.type === 'ENUM'" :value="child.value" @change="changeConditionValue(index, $event)"><option v-for="option in fieldFor(child)?.options ?? []" :key="option.value" :value="option.value">{{ option.label }}</option></select><input v-else :value="String(child.value ?? '')" type="text" @input="changeConditionValue(index, $event)"></label>
                        <button class="ui-icon-button" type="button" :title="t('access.advancedFilter.removeCondition')" :aria-label="t('access.advancedFilter.removeCondition')" @click="removeChild(index)"><img :src="'/assets/icons/fileDelete_24.png'" alt=""></button>
                    </div>
                </template>
                <div class="advanced-filter-group__drop-tail" :class="{ 'advanced-filter-group__drop-tail--active': dropTarget?.position === 'end' }" @dragover.stop.prevent="markTail" @dragleave.stop="clearDrop" @drop.stop.prevent="dropAtEnd" />
            </div>
            <footer class="advanced-filter-group__actions"><button class="ui-icon-button" type="button" :title="t('access.advancedFilter.addCondition')" :aria-label="t('access.advancedFilter.addCondition')" @click="addCondition"><img :src="'/assets/icons/add-condition_24.png'" alt=""></button><button v-if="canAddGroup" class="ui-icon-button" type="button" :title="t('access.advancedFilter.addGroup')" :aria-label="t('access.advancedFilter.addGroup')" @click="addGroup"><img :src="'/assets/icons/add-condition-group_24.png'" alt=""></button></footer>
        </div>
    </section>
</template>

<style scoped>
.advanced-filter-group { display: grid; inline-size: max-content; grid-template-columns: minmax(4.75rem, max-content) max-content; align-items: stretch; gap: var(--space-3); }
.advanced-filter-group--root { min-block-size: 100%; min-inline-size: 50rem; }
.advanced-filter-group__trunk { display: flex; align-items: start; justify-content: end; gap: var(--space-1); padding-block-start: var(--space-3); }.advanced-filter-group__trunk select { min-inline-size: 4.5rem; font-weight: var(--font-weight-bold); }
.advanced-filter-group__body { display: grid; inline-size: max-content; gap: var(--space-3); min-inline-size: 43rem; padding: var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-inline-start: calc(var(--component-border-width) * 4) solid var(--color-action-primary); border-radius: var(--radius-md); background: var(--color-surface-muted); }
.advanced-filter-group__header, .advanced-filter-group__actions { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); }
.advanced-filter-group label { display: inline-flex; align-items: center; gap: var(--space-1); color: var(--color-text-secondary); font-size: var(--font-size-sm); }
.advanced-filter-group select, .advanced-filter-group input[type='text'] { min-block-size: var(--control-height-sm); border: var(--component-border-width) solid var(--component-field-border); border-radius: var(--radius-sm); background: var(--component-field-background); color: var(--color-text-primary); font: inherit; padding-inline: var(--space-2); }
.advanced-filter-group__context, .advanced-filter-group__hint { color: var(--color-text-secondary); font-size: var(--font-size-sm); }
.advanced-filter-group__hint { margin: 0; }
.advanced-filter-group__children { display: grid; gap: var(--space-2); min-inline-size: 0; }
.advanced-filter-group__node { position: relative; }.advanced-filter-group__node--drop-before::before, .advanced-filter-group__node--drop-after::after, .advanced-filter-condition--drop-before::before, .advanced-filter-condition--drop-after::after, .advanced-filter-group__drop-tail--active::before { position: absolute; z-index: 2; inset-inline: var(--space-2); block-size: calc(var(--component-border-width) * 2); border-radius: var(--radius-pill); background: var(--color-action-primary); box-shadow: 0 0 0 var(--space-1) color-mix(in srgb, var(--color-action-primary) 20%, transparent); content: ''; }.advanced-filter-group__node--drop-before::before, .advanced-filter-condition--drop-before::before { inset-block-start: calc(var(--space-1) * -1); }.advanced-filter-group__node--drop-after::after, .advanced-filter-condition--drop-after::after { inset-block-end: calc(var(--space-1) * -1); }
.advanced-filter-group__drop-tail { position: relative; min-block-size: var(--space-3); }.advanced-filter-group__drop-tail--active::before { inset-block-start: calc(var(--space-1) * -1); }
.advanced-filter-condition { position: relative; display: grid; grid-template-columns: 1.25rem minmax(10rem, 1.2fr) minmax(9rem, .9fr) minmax(12rem, 1fr) var(--control-height-sm); align-items: center; gap: var(--space-2); min-inline-size: 43rem; padding: var(--space-2); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-sm); background: var(--color-surface); }
.advanced-filter-condition > label { min-inline-size: 0; }.advanced-filter-condition > label > select { inline-size: 100%; }
.advanced-filter-condition--incompatible { outline: var(--component-border-width) solid var(--color-danger-600); }
.advanced-filter-condition__value { flex: 1 1 12rem; }.advanced-filter-condition__value input, .advanced-filter-condition__value select { inline-size: 100%; }
.advanced-filter-condition .ui-icon-button img, .advanced-filter-group__actions img, .advanced-filter-group__remove img { inline-size: var(--icon-size-sm); block-size: var(--icon-size-sm); object-fit: contain; }
.advanced-filter-condition__drag-handle, .advanced-filter-group__drag-handle { display: inline-grid; place-items: center; color: var(--color-text-secondary); cursor: grab; font-size: var(--font-size-xl); line-height: 1; user-select: none; }.advanced-filter-condition__drag-handle:active, .advanced-filter-group__drag-handle:active { cursor: grabbing; }
.advanced-filter-group__remove { margin-inline-start: auto; }.sr-only { position: absolute; inline-size: 1px; block-size: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
@media (max-width: 700px) { .advanced-filter-group { grid-template-columns: 1fr; gap: var(--space-2); }.advanced-filter-group__trunk { justify-content: start; padding: 0; } }
</style>
