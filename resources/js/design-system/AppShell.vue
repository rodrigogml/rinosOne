<script setup lang="ts">
withDefaults(defineProps<{ centered?: boolean; labelledBy?: string; label?: string }>(), { centered: false, labelledBy: undefined, label: undefined });
</script>

<template><main class="app-shell" :class="{ 'app-shell--centered': centered }" :aria-labelledby="labelledBy" :aria-label="labelledBy ? undefined : label"><div class="app-shell__content"><slot /></div></main></template>
