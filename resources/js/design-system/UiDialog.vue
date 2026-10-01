<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ modelValue: boolean; title: string; destructive?: boolean; escapable?: boolean; backdropDismissible?: boolean; contained?: boolean; wide?: boolean }>(), { destructive: false, escapable: true, backdropDismissible: true, contained: false, wide: false });
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const dialog = ref<HTMLElement | null>(null);
let returnFocus: HTMLElement | null = null;
const focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function close() { emit('update:modelValue', false); }
async function focusDialog() {
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    await nextTick();
    dialog.value?.querySelector<HTMLElement>(focusableSelector)?.focus();
}
function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && props.escapable) { event.preventDefault(); close(); return; }
    if (event.key !== 'Tab' || !dialog.value) return;
    const focusable = Array.from(dialog.value.querySelectorAll<HTMLElement>(focusableSelector));
    if (!focusable.length) { event.preventDefault(); dialog.value.focus(); return; }
    const first = focusable[0]; const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}
watch(() => props.modelValue, async (visible) => {
    if (visible) { await focusDialog(); return; }
    returnFocus?.focus(); returnFocus = null;
});
onMounted(async () => { if (props.modelValue) await focusDialog(); });
onBeforeUnmount(() => returnFocus?.focus());
</script>

<template><div v-if="modelValue" class="ui-dialog-backdrop" :class="{ 'ui-dialog-backdrop--contained': contained }" @mousedown.self="backdropDismissible && close()"><section ref="dialog" class="ui-dialog" :class="{ 'ui-dialog--wide': wide }" :role="destructive ? 'alertdialog' : 'dialog'" :aria-label="title" aria-modal="true" tabindex="-1" @keydown="handleKeydown"><h2 class="ui-dialog__title">{{ title }}</h2><slot :close="close" /></section></div></template>
