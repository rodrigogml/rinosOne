<script setup lang="ts">
const props = withDefaults(defineProps<{ name: string; size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' });

const paths: Record<string, string[]> = {
    cashflow: ['M4 7h16M7 4v6M17 4v6M5 12h14v7H5z', 'M8 15h3M13 15h3'],
    contacts: ['M8.5 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z', 'M3.5 19c.65-3 2.3-4.5 5-4.5s4.35 1.5 5 4.5M16 8h4M18 6v4'],
    opportunities: ['M5 19V9l7-5 7 5v10', 'M8 19v-5h8v5M12 8v2'],
    items: ['M4 7 12 3l8 4-8 4-8-4Z', 'M4 7v8l8 4 8-4V7M12 11v8'],
    invoices: ['M7 3h8l3 3v15H7z', 'M15 3v4h4M10 12h5M10 16h5'],
    ledger: ['M5 4h14v16H5z', 'M8 8h8M8 12h8M8 16h5'],
    performance: ['M5 19V9M10 19V5M15 19v-7M20 19V3'],
};
const fallback = ['M4 5h16v14H4z', 'M8 9h8M8 13h5'];
</script>

<template><svg class="workspace-surface-icon" :class="`workspace-surface-icon--${size}`" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path v-for="path in paths[name] ?? fallback" :key="path" :d="path" /></svg></template>
