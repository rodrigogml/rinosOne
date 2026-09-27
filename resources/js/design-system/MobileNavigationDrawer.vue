<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BrandMark from './BrandMark.vue';
import type { WorkspaceDestination, WorkspaceNavigationCategory } from '../workspace/workspaceTypes';

const props = defineProps<{ modelValue: boolean; brandLabel: string; title: string; closeLabel: string; emptyLabel: string; categories?: readonly WorkspaceNavigationCategory[]; destinations?: readonly WorkspaceDestination[] }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; openDestination: [destination: WorkspaceDestination] }>();
const { t } = useI18n();
const drawer = ref<HTMLElement | null>(null);
const activeCategoryId = ref<string | null>(null);
const activeDestinations = computed(() => (props.destinations ?? []).filter((destination) => destination.category === activeCategoryId.value));
let returnFocus: HTMLElement | null = null;
const focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function close() { emit('update:modelValue', false); }
function selectCategory(categoryId: string): void { activeCategoryId.value = activeCategoryId.value === categoryId ? null : categoryId; }
function openDestination(destination: WorkspaceDestination): void { emit('openDestination', destination); }
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
    activeCategoryId.value = null;
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
                    <svg class="ui-icon-button__icon" width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path d="M14 14 34 34M34 14 14 34" stroke-linecap="round" /></svg>
                </button>
            </header>
            <div class="mobile-navigation-drawer__content">
                <div class="mobile-navigation-drawer__categories">
                    <section v-for="category in categories ?? []" :key="category.id" class="mobile-navigation-drawer__category">
                        <button class="mobile-navigation-drawer__category-trigger" type="button" :aria-expanded="activeCategoryId === category.id" @click="selectCategory(category.id)">
                            <span>{{ category.label ?? t(category.titleKey) }}</span>
                            <span aria-hidden="true">{{ activeCategoryId === category.id ? '−' : '+' }}</span>
                        </button>
                        <div v-if="activeCategoryId === category.id" class="mobile-navigation-drawer__destinations">
                            <button v-for="destination in activeDestinations" :key="destination.id" class="mobile-navigation-drawer__destination" type="button" @click="openDestination(destination)">{{ destination.label ?? t(destination.titleKey) }}</button>
                            <p v-if="!activeDestinations.length">{{ emptyLabel }}</p>
                        </div>
                    </section>
                    <p v-if="!(categories ?? []).length">{{ emptyLabel }}</p>
                </div>
            </div>
        </aside>
    </div>
</template>
