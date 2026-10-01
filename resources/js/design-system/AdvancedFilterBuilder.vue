<script setup lang="ts">
import type { AdvancedFilterGroup, AdvancedFilterSchema } from './advancedFilter';
import AdvancedFilterGroupEditor from './AdvancedFilterGroup.vue';

defineProps<{ modelValue: AdvancedFilterGroup; schema: AdvancedFilterSchema }>();
const emit = defineEmits<{ 'update:modelValue': [value: AdvancedFilterGroup] }>();
</script>

<template>
    <div class="advanced-filter-builder">
        <AdvancedFilterGroupEditor :node="modelValue" :schema="schema" :depth="1" root @update="emit('update:modelValue', $event)" />
    </div>
</template>

<style scoped>
.advanced-filter-builder {
    display: grid;
    min-block-size: 0;
    overflow: auto;
    align-content: center;
    padding: var(--space-3);
}
</style>
