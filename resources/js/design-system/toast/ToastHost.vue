<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { subscribeToasts, type ToastKind, type ToastMessage } from './toastService';

interface ToastLane {
    active: ToastMessage | null;
    queue: ToastMessage[];
    visible: boolean;
    interactionObserved: boolean;
    minimumElapsed: boolean;
}

const props = withDefaults(defineProps<{ hasTopBar?: boolean; minimumVisibleMs?: number }>(), { hasTopBar: false, minimumVisibleMs: 3000 });
const kinds: readonly ToastKind[] = ['success', 'info'];
const lanes = reactive<Record<ToastKind, ToastLane>>({
    success: { active: null, queue: [], visible: false, interactionObserved: false, minimumElapsed: false },
    info: { active: null, queue: [], visible: false, interactionObserved: false, minimumElapsed: false },
});
const minimumTimers: Partial<Record<ToastKind, ReturnType<typeof setTimeout>>> = {};
const hostStyle = ref<Record<string, string>>({});
let unsubscribe: (() => void) | null = null;
let topBarObserver: ResizeObserver | null = null;

function clearMinimumTimer(kind: ToastKind): void {
    if (minimumTimers[kind] !== undefined) clearTimeout(minimumTimers[kind]);
    delete minimumTimers[kind];
}

function release(kind: ToastKind): void {
    const lane = lanes[kind];
    if (!lane.active || !lane.visible) return;

    clearMinimumTimer(kind);
    lane.visible = false;
}

function enqueue(toast: ToastMessage): void {
    const lane = lanes[toast.kind];

    if (lane.active === null) {
        lane.active = toast;
        lane.visible = true;
        lane.interactionObserved = false;
        lane.minimumElapsed = false;
        return;
    }

    lane.queue.push(toast);
}

function beginMinimumVisibility(kind: ToastKind): void {
    const lane = lanes[kind];
    if (!lane.active || !lane.visible) return;

    clearMinimumTimer(kind);
    minimumTimers[kind] = setTimeout(() => {
        lane.minimumElapsed = true;
        if (lane.interactionObserved) release(kind);
    }, props.minimumVisibleMs);
}

function completeLeave(kind: ToastKind): void {
    const lane = lanes[kind];
    if (lane.visible) return;

    lane.active = lane.queue.shift() ?? null;
    lane.interactionObserved = false;
    lane.minimumElapsed = false;
    if (lane.active !== null) lane.visible = true;
}

function measureTopBar(): void {
    if (!props.hasTopBar) return;

    const topBar = document.querySelector<HTMLElement>('.application-top-bar');
    if (topBar === null) return;

    const updateHeight = (): void => {
        hostStyle.value = { '--toast-top-bar-height': `${topBar.getBoundingClientRect().height}px` };
    };
    updateHeight();
    if (typeof ResizeObserver === 'undefined') return;
    topBarObserver = new ResizeObserver(updateHeight);
    topBarObserver.observe(topBar);
}

function observeInteraction(): void {
    kinds.forEach((kind) => {
        const lane = lanes[kind];
        if (!lane.active || !lane.visible) return;

        lane.interactionObserved = true;
        if (lane.minimumElapsed) release(kind);
    });
}

function dismissFromToast(kind: ToastKind): void {
    release(kind);
}

onMounted(() => {
    unsubscribe = subscribeToasts(enqueue);
    measureTopBar();
    window.addEventListener('pointermove', observeInteraction, { passive: true });
    window.addEventListener('keydown', observeInteraction);
    window.addEventListener('wheel', observeInteraction, { passive: true });
    window.addEventListener('touchstart', observeInteraction, { passive: true });
});
onBeforeUnmount(() => {
    unsubscribe?.();
    topBarObserver?.disconnect();
    kinds.forEach(clearMinimumTimer);
    window.removeEventListener('pointermove', observeInteraction);
    window.removeEventListener('keydown', observeInteraction);
    window.removeEventListener('wheel', observeInteraction);
    window.removeEventListener('touchstart', observeInteraction);
});
</script>

<template>
    <div class="toast-host" :class="{ 'toast-host--with-top-bar': hasTopBar }" :style="hostStyle" aria-label="Mensagens transitórias">
        <section v-for="kind in kinds" :key="kind" class="toast-host__lane" :class="`toast-host__lane--${kind}`" :aria-label="kind === 'success' ? 'Mensagens de sucesso' : 'Mensagens informativas'">
            <Transition :name="`toast-${kind}`" @after-enter="beginMinimumVisibility(kind)" @after-leave="completeLeave(kind)">
                <aside v-if="lanes[kind].active && lanes[kind].visible" :key="lanes[kind].active.id" class="toast-host__toast" :class="`toast-host__toast--${kind}`" role="status" aria-live="polite" tabindex="0" @click="dismissFromToast(kind)" @keydown.enter.prevent="dismissFromToast(kind)" @keydown.space.prevent="dismissFromToast(kind)">
                    <img class="toast-host__icon" :src="`/assets/icons/${kind}_24.png`" alt="" aria-hidden="true">
                    <p>{{ lanes[kind].active.message }}</p>
                </aside>
            </Transition>
            <Transition :name="`toast-${kind}-peek`">
                <aside v-if="lanes[kind].active && lanes[kind].queue.length" :key="lanes[kind].queue[0].id" class="toast-host__peek" :class="`toast-host__peek--${kind}`" aria-hidden="true">
                    <img class="toast-host__icon" :src="`/assets/icons/${kind}_24.png`" alt="">
                    <p>{{ lanes[kind].queue[0].message }}</p>
                </aside>
            </Transition>
        </section>
    </div>
</template>
