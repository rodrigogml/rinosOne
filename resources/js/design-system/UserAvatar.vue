<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { deriveAvatarFallback } from './avatarPresentation';

const props = withDefaults(defineProps<{
    displayName?: string | null;
    imageSrc?: string | null;
    label: string;
}>(), { displayName: null, imageSrc: null });

const imageFailed = ref(false);
const fallback = computed(() => deriveAvatarFallback(props.displayName));
const hasImage = computed(() => Boolean(props.imageSrc) && !imageFailed.value);

watch(() => props.imageSrc, () => { imageFailed.value = false; });
</script>

<template>
    <span class="user-avatar" :class="`user-avatar--${fallback.kind}`" role="img" :aria-label="label">
        <img v-if="hasImage" class="user-avatar__image" :src="imageSrc ?? undefined" alt="" @error="imageFailed = true">
        <span v-else class="user-avatar__fallback" aria-hidden="true">{{ fallback.label }}</span>
    </span>
</template>
