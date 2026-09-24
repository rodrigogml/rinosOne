<script setup lang="ts">
import { ref } from 'vue';

withDefaults(defineProps<{ label: string; type?: 'button' | 'submit' | 'reset'; disabled?: boolean }>(), { type: 'button', disabled: false });
const button = ref<HTMLButtonElement | null>(null);
defineExpose({ focus: () => button.value?.focus() });
</script>

<template><button ref="button" class="ui-icon-button" :type="type" :disabled="disabled" :aria-label="label"><span class="ui-icon-button__icon" aria-hidden="true"><slot /></span></button></template>
