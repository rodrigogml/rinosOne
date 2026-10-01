<script setup lang="ts">
import { computed } from 'vue';
import type { AdvancedFilterCondition, AdvancedFilterField, AdvancedFilterGroup, AdvancedFilterNode, AdvancedFilterSchema } from './advancedFilter';
import { createAdvancedFilterGroup, createAdvancedFilterNodeId } from './advancedFilter';
import { useI18n } from 'vue-i18n';

defineOptions({ name: 'AdvancedFilterGroup' });
const props = defineProps<{ node: AdvancedFilterGroup; schema: AdvancedFilterSchema; depth: number; inheritedRelation?: string | null; root?: boolean }>();
const emit = defineEmits<{ update: [node: AdvancedFilterGroup]; remove: [] }>();
const { t } = useI18n();
const scope = computed(() => props.inheritedRelation ?? (props.node.matchMode === 'SAME_RECORD' ? props.node.relation : null));
const fields = computed(() => scope.value === null ? props.schema.fields : props.schema.fields.filter((field) => field.relation === scope.value));
const relationLabel = computed(() => props.schema.relations.find((relation) => relation.key === scope.value)?.label ?? '');
const canAddGroup = computed(() => props.depth < props.schema.maximumDepth);
function copy(): AdvancedFilterGroup { return structuredClone(props.node); }
function update(mutator: (node: AdvancedFilterGroup) => void): void { const next = copy(); mutator(next); emit('update', next); }
function firstField(): AdvancedFilterField | undefined { return fields.value[0]; }
function newCondition(): AdvancedFilterCondition | null { const field = firstField(); return field ? { kind: 'condition', id: createAdvancedFilterNodeId(), field: field.key, operator: field.operators[0], value: field.type === 'ENUM' ? field.options[0]?.value ?? '' : '', negated: false } : null; }
function addCondition(): void { const condition = newCondition(); if (condition) update((group) => group.children.push(condition)); }
function addGroup(): void { update((group) => { const child = createAdvancedFilterGroup(); if (scope.value !== null) { child.matchMode = 'SAME_RECORD'; child.relation = scope.value; } const condition = newCondition(); if (condition) child.children.push(condition); group.children.push(child); }); }
function updateChild(index: number, child: AdvancedFilterNode): void { update((group) => { group.children.splice(index, 1, child); }); }
function removeChild(index: number): void { update((group) => { group.children.splice(index, 1); }); }
function updateCondition(index: number, changes: Partial<AdvancedFilterCondition>): void { const child = props.node.children[index]; if (child?.kind !== 'condition') return; updateChild(index, { ...child, ...changes }); }
function changeField(index: number, fieldKey: string): void { const field = props.schema.fields.find((candidate) => candidate.key === fieldKey); if (!field) return; updateCondition(index, { field: field.key, operator: field.operators[0], value: field.type === 'ENUM' ? field.options[0]?.value ?? '' : '' }); }
function fieldFor(condition: AdvancedFilterCondition): AdvancedFilterField | undefined { return props.schema.fields.find((field) => field.key === condition.field); }
function needsValue(condition: AdvancedFilterCondition): boolean { return !['IS_EMPTY', 'IS_NOT_EMPTY'].includes(condition.operator); }
function isCompatible(condition: AdvancedFilterCondition): boolean { return fields.value.some((field) => field.key === condition.field); }
function changeMatchMode(mode: 'ANY_RECORD' | 'SAME_RECORD'): void { update((group) => { group.matchMode = mode; group.relation = mode === 'SAME_RECORD' ? props.schema.relations[0]?.key ?? null : null; }); }
function targetValue(event: Event): string { return (event.target as HTMLInputElement | HTMLSelectElement).value; }
function targetChecked(event: Event): boolean { return (event.target as HTMLInputElement).checked; }
function changeCombinator(event: Event): void { update((group) => { group.combinator = targetValue(event) === 'OR' ? 'OR' : 'AND'; }); }
function changeNegated(event: Event): void { update((group) => { group.negated = targetChecked(event); }); }
function changeGroupRelation(event: Event): void { update((group) => { group.relation = targetValue(event); }); }
function changeConditionOperator(index: number, event: Event): void { updateCondition(index, { operator: targetValue(event) as AdvancedFilterCondition['operator'] }); }
function changeConditionValue(index: number, event: Event): void { updateCondition(index, { value: targetValue(event) }); }
function changeConditionNegated(index: number, event: Event): void { updateCondition(index, { negated: targetChecked(event) }); }
</script>

<template>
    <section class="advanced-filter-group" :class="{ 'advanced-filter-group--root': root }">
        <aside class="advanced-filter-group__trunk"><label><span class="sr-only">{{ t('access.advancedFilter.combinator') }}</span><select :value="node.combinator" @change="changeCombinator"><option value="AND">{{ t('access.advancedFilter.and') }}</option><option value="OR">{{ t('access.advancedFilter.or') }}</option></select></label></aside>
        <div class="advanced-filter-group__body">
            <header class="advanced-filter-group__header">
                <strong>{{ root ? t('access.advancedFilter.filter') : t('access.advancedFilter.group') }}</strong>
                <label><input type="checkbox" :checked="node.negated" @change="changeNegated"> {{ t('access.advancedFilter.not') }}</label>
                <template v-if="inheritedRelation === undefined || inheritedRelation === null">
                    <label>{{ t('access.advancedFilter.matchMode') }}<select :value="node.matchMode" @change="changeMatchMode(targetValue($event) === 'SAME_RECORD' ? 'SAME_RECORD' : 'ANY_RECORD')"><option value="ANY_RECORD">{{ t('access.advancedFilter.anyRecord') }}</option><option value="SAME_RECORD">{{ t('access.advancedFilter.sameRecord') }}</option></select></label>
                    <label v-if="node.matchMode === 'SAME_RECORD'">{{ t('access.advancedFilter.relation') }}<select :value="node.relation ?? ''" @change="changeGroupRelation"><option v-for="relation in schema.relations" :key="relation.key" :value="relation.key">{{ relation.label }}</option></select></label>
                </template>
                <span v-else class="advanced-filter-group__context">{{ t('access.advancedFilter.sameRecordIn', { relation: relationLabel }) }}</span>
                <button v-if="!root" class="ui-icon-button advanced-filter-group__remove" type="button" :title="t('access.advancedFilter.removeGroup')" :aria-label="t('access.advancedFilter.removeGroup')" @click="emit('remove')"><img :src="'/assets/icons/dataDelete_24.png'" alt=""></button>
            </header>
            <p v-if="node.matchMode === 'SAME_RECORD' && !inheritedRelation" class="advanced-filter-group__hint">{{ t('access.advancedFilter.sameRecordHint', { relation: relationLabel }) }}</p>
            <div class="advanced-filter-group__children">
                <template v-for="(child, index) in node.children" :key="child.id">
                    <AdvancedFilterGroup v-if="child.kind === 'group'" :node="child" :schema="schema" :depth="depth + 1" :inherited-relation="scope" @update="updateChild(index, $event)" @remove="removeChild(index)" />
                    <div v-else class="advanced-filter-condition" :class="{ 'advanced-filter-condition--incompatible': !isCompatible(child) }">
                        <label><span class="sr-only">{{ t('access.advancedFilter.field') }}</span><select :value="child.field" @change="changeField(index, targetValue($event))"><option v-if="!isCompatible(child)" :value="child.field">{{ fieldFor(child)?.label ?? child.field }} — {{ t('access.advancedFilter.incompatibleField') }}</option><option v-for="field in fields" :key="field.key" :value="field.key">{{ field.label }}</option></select></label>
                        <label><span class="sr-only">{{ t('access.advancedFilter.operator') }}</span><select :value="child.operator" @change="changeConditionOperator(index, $event)"><option v-for="operator in fieldFor(child)?.operators ?? []" :key="operator" :value="operator">{{ t(`access.advancedFilter.operators.${operator}`) }}</option></select></label>
                        <label v-if="needsValue(child)" class="advanced-filter-condition__value"><span class="sr-only">{{ t('access.advancedFilter.value') }}</span><select v-if="fieldFor(child)?.type === 'ENUM'" :value="String(child.value ?? '')" @change="changeConditionValue(index, $event)"><option v-for="option in fieldFor(child)?.options ?? []" :key="option.value" :value="option.value">{{ option.label }}</option></select><input v-else :value="String(child.value ?? '')" type="text" @input="changeConditionValue(index, $event)"></label>
                        <label class="advanced-filter-condition__not"><input type="checkbox" :checked="child.negated" @change="changeConditionNegated(index, $event)"> {{ t('access.advancedFilter.not') }}</label>
                        <button class="ui-icon-button" type="button" :title="t('access.advancedFilter.removeCondition')" :aria-label="t('access.advancedFilter.removeCondition')" @click="removeChild(index)"><img :src="'/assets/icons/dataDelete_24.png'" alt=""></button>
                    </div>
                </template>
            </div>
            <footer class="advanced-filter-group__actions"><button class="ui-icon-button" type="button" :title="t('access.advancedFilter.addCondition')" :aria-label="t('access.advancedFilter.addCondition')" @click="addCondition"><img :src="'/assets/icons/add-condition_24.png'" alt=""></button><button v-if="canAddGroup" class="ui-icon-button" type="button" :title="t('access.advancedFilter.addGroup')" :aria-label="t('access.advancedFilter.addGroup')" @click="addGroup"><img :src="'/assets/icons/add-condition-group_24.png'" alt=""></button></footer>
        </div>
    </section>
</template>

<style scoped>
.advanced-filter-group { display: grid; grid-template-columns: minmax(4.75rem, max-content) minmax(0, 1fr); align-items: stretch; gap: var(--space-3); }
.advanced-filter-group--root { min-block-size: 100%; }
.advanced-filter-group__trunk { display: flex; align-items: start; justify-content: end; padding-block-start: var(--space-3); }.advanced-filter-group__trunk select { min-inline-size: 4.5rem; font-weight: var(--font-weight-bold); }
.advanced-filter-group__body { display: grid; gap: var(--space-3); min-inline-size: 0; padding: var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-inline-start: calc(var(--component-border-width) * 4) solid var(--color-action-primary); border-radius: var(--radius-md); background: var(--color-surface-muted); }
.advanced-filter-group__header, .advanced-filter-condition, .advanced-filter-group__actions { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); }
.advanced-filter-group label { display: inline-flex; align-items: center; gap: var(--space-1); color: var(--color-text-secondary); font-size: var(--font-size-sm); }
.advanced-filter-group select, .advanced-filter-group input[type='text'] { min-block-size: var(--control-height-sm); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-sm); background: var(--color-surface); color: var(--color-text-primary); font: inherit; padding-inline: var(--space-2); }
.advanced-filter-group__context, .advanced-filter-group__hint { color: var(--color-text-secondary); font-size: var(--font-size-sm); }
.advanced-filter-group__hint { margin: 0; }
.advanced-filter-group__children { position: relative; display: grid; gap: var(--space-2); padding-inline-start: var(--space-5); border-inline-start: calc(var(--component-border-width) * 2) solid var(--color-border-strong); }
.advanced-filter-group__children > * { position: relative; }.advanced-filter-group__children > *::before { position: absolute; inset-block-start: calc(var(--control-height-sm) / 2); inset-inline-start: calc(var(--space-5) * -1); inline-size: var(--space-5); border-block-start: calc(var(--component-border-width) * 2) solid var(--color-border-strong); content: ''; }
.advanced-filter-condition { padding: var(--space-2); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-sm); background: var(--color-surface); }
.advanced-filter-condition--incompatible { outline: var(--component-border-width) solid var(--color-danger-600); }
.advanced-filter-condition__value { flex: 1 1 12rem; }.advanced-filter-condition__value input, .advanced-filter-condition__value select { inline-size: 100%; }
.advanced-filter-condition .ui-icon-button img, .advanced-filter-group__actions img, .advanced-filter-group__remove img { inline-size: var(--icon-size-sm); block-size: var(--icon-size-sm); object-fit: contain; }
.advanced-filter-group__remove { margin-inline-start: auto; }.sr-only { position: absolute; inline-size: 1px; block-size: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
@media (max-width: 700px) { .advanced-filter-group { grid-template-columns: 1fr; gap: var(--space-2); }.advanced-filter-group__trunk { justify-content: start; padding: 0; }.advanced-filter-group__children { padding-inline-start: var(--space-4); }.advanced-filter-group__children > *::before { inset-inline-start: calc(var(--space-4) * -1); inline-size: var(--space-4); }.advanced-filter-condition > label:not(.advanced-filter-condition__not) { flex: 1 1 100%; }.advanced-filter-condition select { inline-size: 100%; } }
</style>
