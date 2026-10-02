<script setup lang="ts">
import { ref, toRaw } from 'vue';
import type { AdvancedFilterGroup, AdvancedFilterSchema } from './advancedFilter';
import AdvancedFilterGroupEditor from './AdvancedFilterGroup.vue';

const props = defineProps<{ modelValue: AdvancedFilterGroup; schema: AdvancedFilterSchema }>();
const emit = defineEmits<{ 'update:modelValue': [value: AdvancedFilterGroup] }>();
type DropPosition = 'before' | 'after' | 'end';
const draggedId = ref<string | null>(null);

function findGroup(group: AdvancedFilterGroup, id: string): AdvancedFilterGroup | null {
    if (group.id === id) return group;
    for (const child of group.children) if (child.kind === 'group') { const found = findGroup(child, id); if (found) return found; }
    return null;
}
function findParent(group: AdvancedFilterGroup, id: string): AdvancedFilterGroup | null {
    if (group.children.some((child) => child.id === id)) return group;
    for (const child of group.children) if (child.kind === 'group') { const found = findParent(child, id); if (found) return found; }
    return null;
}
function containsNode(group: AdvancedFilterGroup, id: string): boolean { return group.id === id || group.children.some((child) => child.id === id || (child.kind === 'group' && containsNode(child, id))); }
function moveNode(sourceNodeId: string, targetGroupId: string, targetNodeId: string | null, position: DropPosition): void {
    const root = structuredClone(toRaw(props.modelValue));
    const source = findParent(root, sourceNodeId); const target = findGroup(root, targetGroupId);
    if (!source || !target) return;
    const sourceIndex = source.children.findIndex((child) => child.id === sourceNodeId);
    const candidate = source.children[sourceIndex];
    if (!candidate || (candidate.kind === 'group' && containsNode(candidate, target.id))) return;
    const [dragged] = source.children.splice(sourceIndex, 1);
    let destinationIndex = position === 'end' || targetNodeId === null ? target.children.length : target.children.findIndex((child) => child.id === targetNodeId);
    if (destinationIndex < 0) return;
    if (position === 'after') destinationIndex += 1;
    if (source.id === target.id && sourceIndex < destinationIndex) destinationIndex -= 1;
    target.children.splice(Math.max(0, Math.min(destinationIndex, target.children.length)), 0, dragged);
    draggedId.value = null;
    emit('update:modelValue', root);
}
</script>

<template>
    <div class="advanced-filter-builder">
        <AdvancedFilterGroupEditor :model-value="modelValue" :schema="schema" :depth="1" root :move-node="moveNode" :dragged-id="draggedId" @drag-start="draggedId = $event" @drag-end="draggedId = null" @update:model-value="emit('update:modelValue', $event)" />
    </div>
</template>

<style scoped>
.advanced-filter-builder {
    display: grid;
    min-block-size: 0;
    overflow: auto;
    align-content: start;
    padding: var(--space-3);
}
</style>
