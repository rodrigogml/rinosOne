<script setup lang="ts">
withDefaults(defineProps<{ centered?: boolean; labelledBy?: string }>(), { centered: false, labelledBy: undefined });
</script>

<template><main class="app-shell" :class="{ 'app-shell--centered': centered }" :aria-labelledby="labelledBy"><div class="app-shell__content"><slot /></div></main></template>
