<script setup lang="ts">
withDefaults(defineProps<{ alt: string; variant?: 'landscape' | 'icon' }>(), { variant: 'landscape' });

const brandAssetVersion = '20260930';
</script>

<template><img class="brand-mark" :class="`brand-mark--${variant}`" :src="variant === 'landscape' ? `/assets/brand/logo-768.png?v=${brandAssetVersion}` : `/assets/brand/crest-192.png?v=${brandAssetVersion}`" :alt="alt"></template>
