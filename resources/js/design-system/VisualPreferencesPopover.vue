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
const mobilePanelStyle = ref<Record<string, string>>({});
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
function positionMobilePanel() {
    if (!window.matchMedia?.('(max-width: 639px)').matches || !panel.value) { mobilePanelStyle.value = {}; return; }

    const trigger = root.value?.querySelector<HTMLElement>('button');
    if (!trigger) return;

    const gap = 8;
    const gutter = 16;
    const triggerBounds = trigger.getBoundingClientRect();
    const panelBounds = panel.value.getBoundingClientRect();
    const maximumHeight = window.innerHeight - (gutter * 2);
    const panelHeight = Math.min(panelBounds.height, maximumHeight);
    const above = triggerBounds.top - gutter;
    const below = window.innerHeight - triggerBounds.bottom - gutter;
    const top = above >= below
        ? Math.max(gutter, triggerBounds.top - panelHeight - gap)
        : Math.min(window.innerHeight - panelHeight - gutter, triggerBounds.bottom + gap);
    const right = Math.min(
        Math.max(gutter, window.innerWidth - triggerBounds.right),
        Math.max(gutter, window.innerWidth - panelBounds.width - gutter),
    );

    mobilePanelStyle.value = {
        position: 'fixed', top: `${top}px`, right: `${right}px`, bottom: 'auto', left: 'auto', maxHeight: `${maximumHeight}px`, overflowY: 'auto',
    };
}

watch(open, async (visible) => {
    if (visible) { await nextTick(); positionMobilePanel(); panel.value?.focus(); return; }
    mobilePanelStyle.value = {};
    opener.value?.focus();
});
onMounted(() => document.addEventListener('pointerdown', handlePointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', handlePointerDown));
</script>

<template><div ref="root" class="presentation-control"><IconButton ref="opener" :label="t('access.presentation.visualPreferences')" :aria-expanded="open" aria-haspopup="dialog" @click="toggle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.5c-4.85 0-8.5 3.44-8.5 8.08 0 4.93 3.65 8.92 8.15 8.92h1.76a1.62 1.62 0 0 0 1.43-2.37 1.62 1.62 0 0 1 1.43-2.37h.95c1.78 0 3.28-1.45 3.28-3.27C20.5 7.44 16.85 3.5 12 3.5Z" /><path d="M8.35 11.75a2.1 2.1 0 1 0 0-4.2 2.1 2.1 0 0 0 0 4.2Z" /><path d="M12.95 6.7h.01M16.18 9.15h.01M12.8 15.35h.01" stroke-width="2.5" /></svg></IconButton><section v-if="open" ref="panel" class="presentation-popover" :style="mobilePanelStyle" role="dialog" :aria-label="t('access.presentation.visualPreferences')" tabindex="-1" @keydown="handleKeydown"><SegmentedChoiceGroup id="theme-choice" :label="t('access.presentation.theme')" :model-value="effectiveTheme" :options="[{ value: 'light', label: t('access.presentation.light') }, { value: 'dark', label: t('access.presentation.dark') }]" @update:model-value="updateTheme" /><SegmentedChoiceGroup id="font-scale-choice" :label="t('access.presentation.textDensity')" :model-value="preferences.fontScale" :options="densityOptions" @update:model-value="updateFontScale" /><SegmentedChoiceGroup id="spacing-scale-choice" :label="t('access.presentation.spacing')" :model-value="preferences.spacingScale" :options="densityOptions" @update:model-value="updateSpacingScale" /><SegmentedChoiceGroup id="component-scale-choice" :label="t('access.presentation.componentSize')" :model-value="preferences.componentScale" :options="[{ value: 'compact', label: t('access.presentation.compact') }, { value: 'default', label: t('access.presentation.default') }, { value: 'comfortable', label: t('access.presentation.large') }]" @update:model-value="updateComponentScale" /></section></div></template>
