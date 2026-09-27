<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ name: string; size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' });

const paths: Record<string, string[]> = {
    overview: ['M7 7h14v14H7z', 'M27 7h14v14H27z', 'M7 27h14v14H7z', 'M27 27h14v14H27z'],
    documents: ['M9 15.5A3.5 3.5 0 0 1 12.5 12H21l4 5h10.5A3.5 3.5 0 0 1 39 20.5v15A3.5 3.5 0 0 1 35.5 39h-23A3.5 3.5 0 0 1 9 35.5v-20Z', 'M15 27h18M15 33h12'],
    attachments: ['m31.5 14.5-13 13a5.5 5.5 0 0 0 7.8 7.8l13-13a9 9 0 0 0-12.8-12.7l-14.2 14.2a12.5 12.5 0 1 0 17.7 17.7l11.3-11.3'],
    cashflow: ['M7 12h34v25H7z', 'M7 20h34M15 29h7M27 29h6'],
    contacts: ['M25 17a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z', 'M8 38c1.5-7.2 5.2-10.5 11-10.5S28.5 30.8 30 38M35 15v10M30 20h10'],
    opportunities: ['M37 24a13 13 0 1 1-26 0 13 13 0 0 1 26 0Z', 'M30 24a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z', 'm29 19 10-10M31 9h8v8'],
    items: ['m24 6 15 8.5v19L24 42 9 33.5v-19L24 6Z', 'm9 14.5 15 8.5 15-8.5M24 23v19'],
    invoices: ['M13 6h16l7 7v29H13V6Z', 'M29 6v8h7M19 23h11M19 30h11M19 37h7'],
    ledger: ['M10 6h28v36H10z', 'M17 15h14M17 24h14M17 33h10'],
    performance: ['M9 39V25M19 39V15M29 39V21M39 39V9'],
    settings: ['M24 17.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13Z', 'M24 7v4M24 37v4M41 24h-4M11 24H7m29-12-2.8 2.8M14.8 33.2 12 36m24 0-2.8-2.8M14.8 14.8 12 12M31.4 16.6l2.8-2.8M16.6 31.4l-2.8 2.8m19.4 0-2.8-2.8M16.6 16.6l-2.8-2.8'],
};
const rasterSources: Record<string, Record<'sm' | 'md' | 'lg', string>> = {
    maintenance: {
        sm: '/assets/icons/maintenance_24.png',
        md: '/assets/icons/maintenance_32.png',
        lg: '/assets/icons/maintenance_48.png',
    },
    'rinoUser-tweek': {
        sm: '/assets/icons/rinoUser-tweek_24.png',
        md: '/assets/icons/rinoUser-tweek_32.png',
        lg: '/assets/icons/rinoUser-tweek_48.png',
    },
};
const fallback = ['M8 9h32v30H8z', 'M16 19h16M16 27h16M16 35h10'];
const strokeWidth = computed(() => props.name === 'performance' ? 4 : 2.25);
const rasterSource = computed(() => rasterSources[props.name]?.[props.size] ?? null);
</script>

<template><img v-if="rasterSource" class="workspace-surface-icon" :class="`workspace-surface-icon--${size}`" :src="rasterSource" alt="" aria-hidden="true"><svg v-else class="workspace-surface-icon" :class="`workspace-surface-icon--${size}`" width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" :stroke-width="strokeWidth" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path v-for="path in paths[name] ?? fallback" :key="path" :d="path" /></svg></template>
