<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppShell from './AppShell.vue';
import BrandMark from './BrandMark.vue';
import LanguageSelector from './LanguageSelector.vue';
import VisualPreferencesPopover from './VisualPreferencesPopover.vue';

defineProps<{ labelledBy: string }>();
const { t } = useI18n();
</script>

<template><AppShell :labelled-by="labelledBy"><div class="authenticated-frame"><header class="authenticated-frame__header"><BrandMark class="authenticated-frame__brand" variant="icon" :alt="t('access.brand')" /><slot name="header" /></header><slot /><footer class="authenticated-frame__utilities"><VisualPreferencesPopover /><LanguageSelector /></footer></div></AppShell></template>
