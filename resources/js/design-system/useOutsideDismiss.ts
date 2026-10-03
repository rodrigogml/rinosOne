import { onBeforeUnmount, onMounted, type Ref } from 'vue';

/** Shared dismissal for anchored popovers, including teleported content. */
export function useOutsideDismiss(elements: Ref<HTMLElement | null>[], close: () => void): void {
    const outside = (event: Event): void => {
        if (!elements.some(element => element.value && event.composedPath().includes(element.value))) close();
    };
    const escape = (event: KeyboardEvent): void => { if (event.key === 'Escape') close(); };
    onMounted(() => {
        document.addEventListener('pointerdown', outside, true);
        document.addEventListener('click', outside, true);
        document.addEventListener('keydown', escape);
    });
    onBeforeUnmount(() => {
        document.removeEventListener('pointerdown', outside, true);
        document.removeEventListener('click', outside, true);
        document.removeEventListener('keydown', escape);
    });
}
