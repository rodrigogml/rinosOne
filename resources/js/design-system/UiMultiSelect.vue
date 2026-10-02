<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export interface UiMultiSelectOption { value: string; label: string; }

const props = withDefaults(defineProps<{ modelValue: string[]; options: UiMultiSelectOption[]; label: string }>(), { modelValue: () => [] });
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>();
const root = ref<HTMLElement | null>(null);
const open = ref(false);
const selectedLabel = computed(() => {
    const labels = props.options.filter((option) => props.modelValue.includes(option.value)).map((option) => option.label);
    return labels.length ? labels.join(', ') : props.label;
});

function toggle(value: string): void {
    const selected = new Set(props.modelValue);
    selected.has(value) ? selected.delete(value) : selected.add(value);
    emit('update:modelValue', props.options.filter((option) => selected.has(option.value)).map((option) => option.value));
}
function closeWhenClickingOutside(event: PointerEvent): void { if (root.value && event.target instanceof Node && !root.value.contains(event.target)) open.value = false; }
function handleKeydown(event: KeyboardEvent): void { if (event.key === 'Escape') { event.preventDefault(); open.value = false; } }
onMounted(() => window.addEventListener('pointerdown', closeWhenClickingOutside));
onBeforeUnmount(() => window.removeEventListener('pointerdown', closeWhenClickingOutside));
</script>

<template>
    <div ref="root" class="ui-multi-select" @keydown="handleKeydown">
        <button class="ui-multi-select__trigger" type="button" :aria-label="label" :aria-expanded="open" aria-haspopup="listbox" @click="open = !open">
            <span>{{ selectedLabel }}</span><span aria-hidden="true">⌄</span>
        </button>
        <div v-if="open" class="ui-multi-select__options" role="listbox" :aria-label="label" aria-multiselectable="true">
            <button v-for="option in options" :key="option.value" class="ui-multi-select__option" :class="{ 'ui-multi-select__option--selected': modelValue.includes(option.value) }" type="button" role="option" :aria-selected="modelValue.includes(option.value)" @click="toggle(option.value)">
                <span class="ui-multi-select__mark" aria-hidden="true">{{ modelValue.includes(option.value) ? '✓' : '' }}</span><span>{{ option.label }}</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.ui-multi-select { position: relative; min-inline-size: 0; }
.ui-multi-select__trigger { display: flex; inline-size: 100%; min-block-size: var(--control-height-sm); align-items: center; justify-content: space-between; gap: var(--space-2); overflow: hidden; border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-sm); background: var(--color-surface); color: var(--color-text-primary); font: inherit; padding-inline: var(--space-2); text-align: start; cursor: pointer; }.ui-multi-select__trigger > span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ui-multi-select__trigger:focus-visible { outline: 3px solid color-mix(in srgb, var(--color-focus-ring) 20%, transparent); outline-offset: 0; }
.ui-multi-select__options { position: absolute; z-index: 4; inset-block-start: calc(100% + var(--space-1)); inset-inline: 0; display: grid; max-block-size: 16rem; overflow: auto; padding: var(--space-1); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-sm); background: var(--color-surface-raised); box-shadow: var(--shadow-float); }
.ui-multi-select__option { display: flex; min-block-size: var(--control-height-sm); align-items: center; gap: var(--space-2); border: var(--component-border-width) solid transparent; border-radius: var(--radius-sm); background: transparent; color: var(--color-text-primary); font: inherit; padding: var(--space-1) var(--space-2); text-align: start; cursor: pointer; }.ui-multi-select__option:hover, .ui-multi-select__option:focus-visible { border-color: var(--color-border-subtle); background: var(--color-surface-muted); outline: 0; }
.ui-multi-select__mark { display: inline-grid; inline-size: 1.125rem; block-size: 1.125rem; flex: 0 0 auto; place-items: center; border: var(--component-border-width) solid var(--color-border-strong); border-radius: var(--radius-sm); color: var(--color-action-primary-content); font-weight: var(--font-weight-bold); }.ui-multi-select__option--selected .ui-multi-select__mark { border-color: var(--color-action-primary); background: var(--color-action-primary); }
</style>
