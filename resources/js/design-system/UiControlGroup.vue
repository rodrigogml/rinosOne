<script setup lang="ts">
defineProps<{ label: string }>();
</script>

<template><div class="ui-control-group" role="group" :aria-label="label"><slot /></div></template>
