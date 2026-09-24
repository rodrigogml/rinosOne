<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVisualPreferencesStore, type DensityPreference, type ThemePreference } from '../preferences/visualPreferences';
import IconButton from './IconButton.vue';
import SegmentedChoiceGroup from './SegmentedChoiceGroup.vue';

const { t } = useI18n();
const store = useVisualPreferencesStore();
const root = ref<HTMLElement | null>(null);
const opener = ref<{ focus: () => void } | null>(null);
const panel = ref<HTMLElement | null>(null);
const open = ref(false);
const preferences = computed(() => store.preferences);
const effectiveTheme = computed<'light' | 'dark'>(() => {
    if (preferences.value.theme !== 'system') return preferences.value.theme;

    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
});
const densityOptions = computed(() => [
    { value: 'compact' as const, label: t('access.presentation.compact') },
    { value: 'default' as const, label: t('access.presentation.default') },
    { value: 'comfortable' as const, label: t('access.presentation.comfortable') },
]);

function updateTheme(theme: ThemePreference) { store.update({ theme }); }
function updateFontScale(fontScale: DensityPreference) { store.update({ fontScale }); }
function updateSpacingScale(spacingScale: DensityPreference) { store.update({ spacingScale }); }
function updateComponentScale(componentScale: DensityPreference) { store.update({ componentScale }); }
function close() { open.value = false; }
function toggle() { open.value = !open.value; }
function handleKeydown(event: KeyboardEvent) { if (event.key === 'Escape') { event.preventDefault(); close(); } }
function handlePointerDown(event: PointerEvent) { if (open.value && root.value && !root.value.contains(event.target as Node)) close(); }

watch(open, async (visible) => {
    if (visible) { await nextTick(); panel.value?.focus(); return; }
    opener.value?.focus();
});
onMounted(() => document.addEventListener('pointerdown', handlePointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', handlePointerDown));
</script>

<template><div ref="root" class="presentation-control"><IconButton ref="opener" :label="t('access.presentation.visualPreferences')" :aria-expanded="open" aria-haspopup="dialog" @click="toggle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" /><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.1 2.1-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56v.1h-3v-.1a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-2.1-2.1.06-.06A1.7 1.7 0 0 0 7.06 15a1.7 1.7 0 0 0-1.56-1.03h-.1v-3h.1A1.7 1.7 0 0 0 7.06 9.94a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.1-2.1.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1.03-1.56v-.1h3v.1a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.1 2.1-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.56 1.03h.1v3h-.1A1.7 1.7 0 0 0 19.4 15Z" /></svg></IconButton><section v-if="open" ref="panel" class="presentation-popover" role="dialog" :aria-label="t('access.presentation.visualPreferences')" tabindex="-1" @keydown="handleKeydown"><SegmentedChoiceGroup id="theme-choice" :label="t('access.presentation.theme')" :model-value="effectiveTheme" :options="[{ value: 'light', label: t('access.presentation.light') }, { value: 'dark', label: t('access.presentation.dark') }]" @update:model-value="updateTheme" /><SegmentedChoiceGroup id="font-scale-choice" :label="t('access.presentation.textDensity')" :model-value="preferences.fontScale" :options="densityOptions" @update:model-value="updateFontScale" /><SegmentedChoiceGroup id="spacing-scale-choice" :label="t('access.presentation.spacing')" :model-value="preferences.spacingScale" :options="densityOptions" @update:model-value="updateSpacingScale" /><SegmentedChoiceGroup id="component-scale-choice" :label="t('access.presentation.componentSize')" :model-value="preferences.componentScale" :options="[{ value: 'compact', label: t('access.presentation.compact') }, { value: 'default', label: t('access.presentation.default') }, { value: 'comfortable', label: t('access.presentation.large') }]" @update:model-value="updateComponentScale" /></section></div></template>
