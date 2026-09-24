<script setup lang="ts">
withDefaults(defineProps<{ alt: string; variant?: 'landscape' | 'icon' }>(), { variant: 'landscape' });
</script>

<template><img class="brand-mark" :class="`brand-mark--${variant}`" :src="variant === 'landscape' ? '/assets/brand/logo-768.png' : '/assets/brand/icon-192.png'" :alt="alt"></template>
