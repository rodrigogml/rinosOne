<script setup lang="ts" generic="T extends string">
import { computed } from 'vue';

export interface Choice<Value extends string> { value: Value; label: string; icon?: string; }

const props = defineProps<{ id: string; label: string; modelValue: T; options: Choice<T>[] }>();
const emit = defineEmits<{ 'update:modelValue': [value: T] }>();
const selectedIndex = computed(() => props.options.findIndex((option) => option.value === props.modelValue));

function select(value: T) { emit('update:modelValue', value); }
function move(event: KeyboardEvent) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const lastIndex = props.options.length - 1;
    const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? lastIndex : (selectedIndex.value + (event.key === 'ArrowRight' ? 1 : -1) + props.options.length) % props.options.length;
    const option = props.options[nextIndex];
    select(option.value);
    document.getElementById(`${props.id}-${option.value}`)?.focus();
}
</script>

<template><fieldset class="segmented-choice" :aria-labelledby="`${id}-label`" @keydown="move"><legend :id="`${id}-label`" class="segmented-choice__label">{{ label }}</legend><div class="segmented-choice__options" :class="{ 'segmented-choice__options--icons': options.every((option) => option.icon), 'segmented-choice__options--pair': options.length === 2 && options.every((option) => option.icon) }" role="radiogroup" :aria-label="label"><button v-for="option in options" :id="`${id}-${option.value}`" :key="option.value" type="button" role="radio" class="segmented-choice__option" :class="{ 'segmented-choice__option--icon': option.icon, 'segmented-choice__option--selected': modelValue === option.value }" :aria-label="option.icon ? option.label : undefined" :aria-checked="modelValue === option.value" :tabindex="modelValue === option.value ? 0 : -1" @click="select(option.value)"><img v-if="option.icon" :src="option.icon" alt="" aria-hidden="true"><template v-else>{{ option.label }}</template></button></div></fieldset></template>
