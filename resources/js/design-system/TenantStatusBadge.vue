<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { TenantState } from '../tenant/tenantTypes';

defineProps<{ state: TenantState }>();
const { t } = useI18n();
</script>

<template><span class="tenant-status-badge" :class="`tenant-status-badge--${state.toLowerCase()}`">{{ t(`access.tenant.states.${state}`) }}</span></template>
