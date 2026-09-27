<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';
import WorkspaceSurfaceIcon from './WorkspaceSurfaceIcon.vue';

const props = defineProps<{ modelValue: boolean; surfaces: readonly WorkspaceSurface[]; activeSurfaceId: string | null; title: string; closeLabel: string; emptyLabel: string; closeSurfaceLabel: string; dirtyLabel: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; activate: [surfaceId: string]; requestClose: [surfaceId: string] }>();
const { t } = useI18n();
const panel = ref<HTMLElement | null>(null);
let returnFocus: HTMLElement | null = null;
const focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function close(): void { emit('update:modelValue', false); }
function activate(surfaceId: string): void { emit('activate', surfaceId); close(); }
function requestClose(surfaceId: string): void { emit('requestClose', surfaceId); }
async function focusPanel(): Promise<void> {
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    await nextTick();
    panel.value?.querySelector<HTMLButtonElement>('button:not([disabled])')?.focus();
}
function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') { event.preventDefault(); close(); return; }
    if (event.key !== 'Tab' || !panel.value) return;
    const focusable = Array.from(panel.value.querySelectorAll<HTMLElement>(focusableSelector));
    const first = focusable[0]; const last = focusable.at(-1);
    if (!first || !last) { event.preventDefault(); panel.value.focus(); return; }
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}
watch(() => props.modelValue, async (visible) => {
    if (visible) { await focusPanel(); return; }
    returnFocus?.focus(); returnFocus = null;
});
onMounted(async () => { if (props.modelValue) await focusPanel(); });
onBeforeUnmount(() => returnFocus?.focus());
</script>

<template>
    <div v-if="modelValue" class="application-overlay workspace-mobile-task-overlay" @mousedown.self="close">
        <aside ref="panel" class="workspace-mobile-task-panel" role="dialog" aria-modal="true" :aria-label="title" tabindex="-1" @keydown="handleKeydown">
            <header class="workspace-mobile-task-panel__header">
                <h2>{{ title }}</h2>
                <button class="workspace-mobile-task-panel__close" type="button" :aria-label="closeLabel" @click="close"><svg class="ui-icon-button__icon" width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path d="M14 14 34 34M34 14 14 34" stroke-linecap="round" /></svg></button>
            </header>
            <div v-if="surfaces.length" class="workspace-mobile-task-panel__list">
                <div v-for="surface in surfaces" :key="surface.id" class="workspace-mobile-task-panel__item" :class="{ 'workspace-mobile-task-panel__item--active': surface.id === activeSurfaceId }">
                    <button class="workspace-mobile-task-panel__activate" type="button" :aria-current="surface.id === activeSurfaceId ? 'page' : undefined" :aria-label="surface.label ?? t(surface.titleKey)" @click="activate(surface.id)">
                        <WorkspaceSurfaceIcon :name="surface.icon" /><span>{{ surface.label ?? t(surface.titleKey) }}</span><span v-if="surface.dirty" :aria-label="dirtyLabel">●</span>
                    </button>
                    <button class="workspace-mobile-task-panel__request-close" type="button" :aria-label="`${closeSurfaceLabel}: ${surface.label ?? t(surface.titleKey)}`" @click="requestClose(surface.id)"><svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path d="M14 14 34 34M34 14 14 34" stroke-linecap="round" /></svg></button>
                </div>
            </div>
            <p v-else class="workspace-mobile-task-panel__empty">{{ emptyLabel }}</p>
        </aside>
    </div>
</template>
