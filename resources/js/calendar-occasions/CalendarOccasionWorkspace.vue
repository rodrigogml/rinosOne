<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import CalendarOccasionCatalog from './CalendarOccasionCatalog.vue';
import CalendarOccasionForm from './CalendarOccasionForm.vue';

const catalog = ref<InstanceType<typeof CalendarOccasionCatalog> | null>(null);
const editingId = ref<number | null | undefined>(undefined);
const activeOccasionId = computed<number | null>(() => editingId.value ?? null);
async function closeEditor(): Promise<void> { editingId.value = undefined; await nextTick(); await catalog.value?.refresh(); await catalog.value?.focus(); }
</script>

<template>
    <section class="calendar-occasion-workspace">
        <section v-show="editingId === undefined" class="calendar-occasion-workspace__catalog-context">
            <CalendarOccasionCatalog ref="catalog" @create="editingId = null" @edit="editingId = $event" />
        </section>
        <CalendarOccasionForm v-if="editingId !== undefined" :occasion-id="activeOccasionId" @cancel="closeEditor" @saved="closeEditor" />
    </section>
</template>

<style scoped>
:global(.workspace-stage__surface-content:has(.calendar-occasion-workspace)) { align-content: stretch; align-items: stretch; padding: 0; overflow: hidden; }
:global(.workspace-stage__surface-instance:has(.calendar-occasion-workspace)) { min-block-size: 100%; block-size: 100%; }
.calendar-occasion-workspace { display: grid; grid-template-rows: minmax(0, 1fr); min-block-size: 100%; block-size: 100%; min-height: 0; overflow: hidden; }
.calendar-occasion-workspace__catalog-context { position: relative; box-sizing: border-box; min-block-size: 100%; block-size: 100%; padding: var(--component-workspace-surface-padding); }
</style>
