<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppShell from './AppShell.vue';
import BrandMark from './BrandMark.vue';
import LanguageSelector from './LanguageSelector.vue';
import UiCard from './UiCard.vue';
import VisualPreferencesPopover from './VisualPreferencesPopover.vue';

const { t } = useI18n();
</script>

<template><AppShell centered><div class="access-frame"><BrandMark class="access-frame__brand" :alt="t('access.brand')" /><UiCard><slot /></UiCard><div class="access-frame__utilities"><VisualPreferencesPopover /><LanguageSelector /></div></div></AppShell></template>
