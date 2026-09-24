<script setup lang="ts">
import UserAvatar from './UserAvatar.vue';

withDefaults(defineProps<{ displayName?: string | null; label: string }>(), { displayName: null });
</script>

<template><UserAvatar :display-name="displayName" :label="label" /></template>
