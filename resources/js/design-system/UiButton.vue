<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'primary' | 'secondary' | 'destructive'; type?: 'button' | 'submit' | 'reset'; loading?: boolean; disabled?: boolean }>(), { variant: 'primary', type: 'button', loading: false, disabled: false });
</script>

<template><button class="ui-button" :class="`ui-button--${variant}`" :type="type" :disabled="disabled || loading" :aria-busy="loading || undefined"><slot /></button></template>
