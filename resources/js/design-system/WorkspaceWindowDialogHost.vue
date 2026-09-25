<script setup lang="ts">
import { computed } from 'vue';
import UiDialog from './UiDialog.vue';
import type { WorkspaceWindowDialog } from '../workspace/workspaceTypes';

const props = defineProps<{ dialogs: readonly WorkspaceWindowDialog[] }>();
const emit = defineEmits<{ dismiss: [dialogId: string] }>();
const activeDialog = computed(() => props.dialogs.at(-1) ?? null);
function dismissActiveDialog(): void {
    if (activeDialog.value) emit('dismiss', activeDialog.value.id);
}
</script>

<template>
    <UiDialog :model-value="activeDialog !== null" contained :title="activeDialog?.title ?? ''" :backdrop-dismissible="false" @update:model-value="dismissActiveDialog">
        <slot v-if="activeDialog" :dialog="activeDialog" :dismiss="dismissActiveDialog" />
    </UiDialog>
</template>
