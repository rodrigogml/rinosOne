<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import UIRinoButton from './UIRinoButton.vue';
import { useOutsideDismiss } from './useOutsideDismiss';

const props = defineProps<{ label: string }>();
const { t } = useI18n();
const root = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const opener = ref<{ focus: () => void } | null>(null);
const open = ref(false);
const position = ref({ left: '0px', top: '0px', visibility: 'hidden' as 'visible' | 'hidden' });
function close(): void { open.value = false; }
useOutsideDismiss([root, panel], close);
function reposition(): void {
    const anchor = root.value?.getBoundingClientRect();
    const bounds = panel.value?.getBoundingClientRect();
    if (!anchor || !bounds) return;
    const left = Math.max(8, Math.min(anchor.right - bounds.width, window.innerWidth - bounds.width - 8));
    const top = anchor.bottom + bounds.height + 8 > window.innerHeight ? Math.max(8, anchor.top - bounds.height - 4) : anchor.bottom + 4;
    position.value = { left: `${left}px`, top: `${top}px`, visibility: 'visible' };
}
async function toggle(): Promise<void> {
    open.value = !open.value;
    if (!open.value) return;
    position.value.visibility = 'hidden';
    await nextTick();
    reposition();
    panel.value?.querySelector<HTMLButtonElement>('button:not(:disabled)')?.focus();
}
function selected(event: MouseEvent): void {
    if ((event.target as HTMLElement).closest('button:not(:disabled)')) { close(); opener.value?.focus(); }
}
function escape(event: KeyboardEvent): void { if (event.key === 'Escape') { close(); opener.value?.focus(); } }
window.addEventListener('resize', close);
window.addEventListener('scroll', close, true);
onBeforeUnmount(() => { window.removeEventListener('resize', close); window.removeEventListener('scroll', close, true); });
</script>

<template>
    <div ref="root" class="ui-action-popover">
        <UIRinoButton ref="opener" icon="moreActions" :accessible-label="props.label" :aria-expanded="open" aria-haspopup="dialog" @click="toggle" />
        <Teleport to="body">
            <div v-if="open" ref="panel" class="ui-action-popover__panel" :style="position" role="dialog" :aria-label="t(props.label)" @click="selected" @keydown="escape"><slot /></div>
        </Teleport>
    </div>
</template>
