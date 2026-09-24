<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BrandMark from './BrandMark.vue';

const props = defineProps<{ modelValue: boolean; brandLabel: string; title: string; closeLabel: string; emptyLabel: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const drawer = ref<HTMLElement | null>(null);
let returnFocus: HTMLElement | null = null;
const focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function close() { emit('update:modelValue', false); }
async function focusDrawer() {
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    await nextTick();
    drawer.value?.querySelector<HTMLButtonElement>('button:not([disabled])')?.focus();
}
function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') { event.preventDefault(); close(); return; }
    if (event.key !== 'Tab' || !drawer.value) return;
    const focusable = Array.from(drawer.value.querySelectorAll<HTMLElement>(focusableSelector));
    const first = focusable[0]; const last = focusable.at(-1);
    if (!first || !last) { event.preventDefault(); drawer.value.focus(); return; }
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}

watch(() => props.modelValue, async (visible) => {
    if (visible) { await focusDrawer(); return; }
    returnFocus?.focus(); returnFocus = null;
});
onMounted(async () => { if (props.modelValue) await focusDrawer(); });
onBeforeUnmount(() => returnFocus?.focus());
</script>

<template>
    <div v-if="modelValue" class="application-overlay" @mousedown.self="close">
        <aside ref="drawer" class="mobile-navigation-drawer" role="dialog" aria-modal="true" :aria-label="title" tabindex="-1" @keydown="handleKeydown">
            <header class="mobile-navigation-drawer__header">
                <BrandMark class="mobile-navigation-drawer__brand" :alt="brandLabel" />
                <button class="mobile-navigation-drawer__close" type="button" :aria-label="closeLabel" @click="close">
                    <svg class="ui-icon-button__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </header>
            <div class="mobile-navigation-drawer__content"><p>{{ emptyLabel }}</p></div>
        </aside>
    </div>
</template>
