<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { TenantSummary } from '../tenant/tenantTypes';
import UiDialog from './UiDialog.vue';

const props = defineProps<{ modelValue: boolean; tenants: TenantSummary[]; selectedTenantId?: number | null }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; select: [tenant: TenantSummary] }>();
const { t } = useI18n();
const filter = ref('');
const activeIndex = ref(0);
const input = ref<HTMLInputElement | null>(null);
const filteredTenants = computed(() => props.tenants.filter((tenant) => tenant.displayName.toLocaleLowerCase().includes(filter.value.trim().toLocaleLowerCase())));

function close() { emit('update:modelValue', false); }
function choose(tenant: TenantSummary) { if (tenant.selectable) emit('select', tenant); }
function handleKeydown(event: KeyboardEvent) {
    if (!filteredTenants.value.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
    if (event.key === 'ArrowDown') { event.preventDefault(); activeIndex.value = (activeIndex.value + 1) % filteredTenants.value.length; return; }
    if (event.key === 'ArrowUp') { event.preventDefault(); activeIndex.value = (activeIndex.value - 1 + filteredTenants.value.length) % filteredTenants.value.length; return; }
    event.preventDefault();
    const tenant = filteredTenants.value[activeIndex.value];
    if (tenant) choose(tenant);
}
watch(filter, () => { activeIndex.value = 0; });
watch(() => props.modelValue, async (visible) => {
    if (!visible) return;
    filter.value = ''; activeIndex.value = 0;
    await nextTick(); input.value?.focus();
});
</script>

<template>
    <UiDialog :model-value="modelValue" :title="t('access.tenant.searchTitle')" @update:model-value="close">
        <div class="tenant-search" @keydown="handleKeydown">
            <input ref="input" v-model="filter" class="tenant-search__input" type="search" :placeholder="t('access.tenant.searchPlaceholder')" :aria-label="t('access.tenant.searchPlaceholder')" autocomplete="off">
            <div v-if="filteredTenants.length" class="tenant-search__list" role="listbox" :aria-label="t('access.tenant.searchResults')">
                <button v-for="(tenant, index) in filteredTenants" :key="tenant.id" class="tenant-search__card" :class="{ 'tenant-search__card--active': index === activeIndex }" type="button" :disabled="!tenant.selectable" :aria-selected="selectedTenantId === tenant.id" @mouseenter="activeIndex = index" @click="choose(tenant)"><span>{{ tenant.displayName }}</span><span v-if="selectedTenantId === tenant.id" class="tenant-search__selected" aria-hidden="true">✓</span><small v-else-if="!tenant.selectable">{{ t(`access.tenant.states.${tenant.state}`) }}</small></button>
            </div>
            <p v-else class="tenant-search__empty">{{ t('access.tenant.searchEmpty') }}</p>
        </div>
    </UiDialog>
</template>
