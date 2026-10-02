<script setup lang="ts">
import BrandMark from '../design-system/BrandMark.vue';
import VisualPreferencesPopover from '../design-system/VisualPreferencesPopover.vue';
</script>

<template>
    <header class="application-top-bar developer-guide-top-bar">
        <BrandMark class="developer-guide-top-bar__brand" alt="Rinos One" />
        <div class="developer-guide-top-bar__actions">
            <VisualPreferencesPopover />
        </div>
    </header>
</template>
