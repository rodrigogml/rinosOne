<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BrandMark from './BrandMark.vue';
import type { WorkspaceDestination, WorkspaceDestinationScope, WorkspaceNavigationCategory } from '../workspace/workspaceTypes';
import WorkspaceSurfaceIcon from './WorkspaceSurfaceIcon.vue';

const props = defineProps<{ modelValue: boolean; brandLabel: string; title: string; closeLabel: string; emptyLabel: string; categories?: readonly WorkspaceNavigationCategory[]; destinations?: readonly WorkspaceDestination[]; scopeLabels?: Partial<Record<WorkspaceDestinationScope, string>> }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; openDestination: [destination: WorkspaceDestination] }>();
const { t } = useI18n();
const drawer = ref<HTMLElement | null>(null);
const activeCategoryId = ref<string | null>(null);
const collapsedScopes = ref<WorkspaceDestinationScope[]>([]);
const scopedCategories = computed(() => {
    const groups = new Map<WorkspaceDestinationScope, { scope: WorkspaceDestinationScope; label: string; categories: WorkspaceNavigationCategory[] }>();
    for (const category of props.categories ?? []) {
        const group = groups.get(category.scope) ?? { scope: category.scope, label: category.scopeLabel, categories: [] };
        group.categories.push(category);
        groups.set(category.scope, group);
    }

    return [...groups.values()];
});
const activeDestinationGroups = computed(() => {
    const category = (props.categories ?? []).find((candidate) => candidate.id === activeCategoryId.value);
    const fallbackGroupKey = category?.titleKey ?? '';
    const groups = new Map<string, { label?: string; destinations: WorkspaceDestination[] }>();

    for (const destination of (props.destinations ?? []).filter((candidate) => candidate.category === activeCategoryId.value)) {
        const groupKey = destination.groupKey ?? fallbackGroupKey;
        const group = groups.get(groupKey) ?? { label: destination.groupLabel, destinations: [] };
        groups.set(groupKey, { ...group, destinations: [...group.destinations, destination] });
    }

    return [...groups].map(([key, group]) => ({ key, ...group }));
});
let returnFocus: HTMLElement | null = null;
const focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function close() { emit('update:modelValue', false); }
function selectCategory(categoryId: string): void { activeCategoryId.value = activeCategoryId.value === categoryId ? null : categoryId; }
function scopeLabel(scope: WorkspaceDestinationScope, fallback: string): string { return props.scopeLabels?.[scope] ?? fallback; }
function toggleScope(scope: WorkspaceDestinationScope): void {
    const isCollapsing = !collapsedScopes.value.includes(scope);
    if (isCollapsing && scopedCategories.value.find((group) => group.scope === scope)?.categories.some((category) => category.id === activeCategoryId.value)) activeCategoryId.value = null;
    collapsedScopes.value = isCollapsing
        ? [...collapsedScopes.value, scope]
        : collapsedScopes.value.filter((candidate) => candidate !== scope);
}
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
    collapsedScopes.value = [];
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
                    <section v-for="group in scopedCategories" :key="group.scope" class="mobile-navigation-drawer__scope" :class="{ 'mobile-navigation-drawer__scope--collapsed': collapsedScopes.includes(group.scope) }">
                        <button type="button" class="mobile-navigation-drawer__scope-header" :aria-label="`${collapsedScopes.includes(group.scope) ? 'Expandir' : 'Recolher'} ${scopeLabel(group.scope, group.label)}`" :aria-expanded="!collapsedScopes.includes(group.scope)" @click="toggleScope(group.scope)">
                            <span>{{ scopeLabel(group.scope, group.label) }}</span>
                            <span class="mobile-navigation-drawer__scope-toggle">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path :d="collapsedScopes.includes(group.scope) ? 'm17 20 7 7 7-7' : 'm17 28 7-7 7 7'" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </span>
                        </button>
                        <div v-show="!collapsedScopes.includes(group.scope)" class="mobile-navigation-drawer__scope-categories">
                            <section v-for="category in group.categories" :key="category.id" class="mobile-navigation-drawer__category">
                                <button class="mobile-navigation-drawer__category-trigger" type="button" :aria-expanded="activeCategoryId === category.id" @click="selectCategory(category.id)">
                                    <WorkspaceSurfaceIcon :name="category.icon" size="sm" />
                                    <span>{{ category.label ?? t(category.titleKey) }}</span>
                                    <span class="mobile-navigation-drawer__category-indicator" aria-hidden="true">{{ activeCategoryId === category.id ? '−' : '+' }}</span>
                                </button>
                                <div v-if="activeCategoryId === category.id" class="mobile-navigation-drawer__destinations">
                                    <section v-for="group in activeDestinationGroups" :key="group.key" class="mobile-navigation-drawer__destination-group">
                                        <h3 class="mobile-navigation-drawer__destination-group-title">{{ group.label ?? t(category.titleKey) }}</h3>
                                        <button v-for="destination in group.destinations" :key="destination.id" class="mobile-navigation-drawer__destination" type="button" @click="openDestination(destination)">
                                            <WorkspaceSurfaceIcon :name="destination.icon" size="sm" />
                                            <span>{{ destination.navigationLabel ?? destination.label ?? t(destination.titleKey) }}</span>
                                        </button>
                                    </section>
                                    <p v-if="!activeDestinationGroups.length">{{ emptyLabel }}</p>
                                </div>
                            </section>
                        </div>
                    </section>
                    <p v-if="!scopedCategories.length">{{ emptyLabel }}</p>
                </div>
            </div>
        </aside>
    </div>
</template>
