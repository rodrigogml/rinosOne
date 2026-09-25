<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'info' | 'success' | 'warning' | 'error' }>(), { tone: 'info' });
</script>

<template><div class="ui-alert" :class="`ui-alert--${tone}`" :role="tone === 'error' ? 'alert' : 'status'" :aria-live="tone === 'error' ? 'assertive' : 'polite'"><slot /></div></template>
