<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import UiAlert from '../design-system/UiAlert.vue';
import UiButton from '../design-system/UiButton.vue';
import type { DriveItem, DriveWorkspaceTarget } from '../drive/driveWorkspaceApi';
import { ContextualAuthorizationAdministrationResponseShapeError, parseContextualAdministrationResourceShare, parseContextualAdministrationWorkspaceResponsible, type ContextualAdministrationResourceShare, type ContextualAdministrationWorkspaceResponsible } from './contextualAdministrationApi';

const props = defineProps<{ target: DriveWorkspaceTarget; folder: DriveItem }>();
const emit = defineEmits<{ close: [] }>();
const shares = ref<ContextualAdministrationResourceShare[]>([]);
const responsible = ref<ContextualAdministrationWorkspaceResponsible | null>(null);
const loading = ref(true); const error = ref('');
const base = computed(() => props.target.kind === 'personal' ? '/api/v1/authorization/personal' : `/api/v1/tenants/${props.target.tenantId}/authorization`);

async function load(): Promise<void> {
    loading.value = true; error.value = '';
    try {
        const response = await axios.get(`${base.value}/resources/FOLDER/${props.folder.id}/shares`);
        if (!Array.isArray(response.data?.shares)) throw new ContextualAuthorizationAdministrationResponseShapeError();
        responsible.value = parseContextualAdministrationWorkspaceResponsible(response.data?.workspaceResponsible);
        shares.value = response.data.shares.map(parseContextualAdministrationResourceShare);
    } catch { error.value = 'Não foi possível carregar os compartilhamentos desta pasta.'; }
    finally { loading.value = false; }
}
onMounted(() => { void load(); });
</script>

<template>
    <aside class="resource-sharing-panel" aria-label="Compartilhar pasta" :aria-busy="loading || undefined">
        <header><div><p>Compartilhamento de pasta · {{ target.kind === 'personal' ? 'Workspace pessoal' : 'Workspace da organização' }}</p><h4>{{ folder.displayName }}</h4></div><button type="button" aria-label="Fechar compartilhamento" @click="emit('close')">×</button></header>
        <p v-if="responsible">Responsável pelo workspace: <strong>{{ responsible.displayName }}</strong>.</p>
        <p v-if="loading" aria-live="polite">Carregando compartilhamentos…</p>
        <UiAlert v-else-if="error" tone="error">{{ error }}</UiAlert>
        <template v-else><p v-if="!shares.length">Esta pasta não possui compartilhamentos visíveis.</p><ul v-else><li v-for="share in shares" :key="share.id"><strong>{{ share.grantee.displayName }}</strong> · {{ share.relation }} · <span v-if="share.origin === 'DIRECT'">Acesso direto</span><span v-else>Acesso herdado da pasta #{{ share.inheritedFrom?.resourceId }}</span></li></ul><UiButton variant="secondary" @click="load">Recarregar</UiButton></template>
    </aside>
</template>

<style scoped>
.resource-sharing-panel { position: absolute; z-index: 3; top: 0; right: 0; bottom: 0; display: grid; width: min(24rem, 48%); align-content: start; gap: var(--space-4); padding: var(--space-4); border-left: var(--component-border-width) solid var(--color-border-subtle); background: var(--color-surface-raised); box-shadow: var(--shadow-float); overflow: auto; }.resource-sharing-panel header { display: flex; align-items: start; justify-content: space-between; gap: var(--space-3); }.resource-sharing-panel header p, .resource-sharing-panel h4 { margin: 0; }.resource-sharing-panel h4 { color: var(--color-text-primary); font-size: var(--font-size-lg); }.resource-sharing-panel header button { border: 0; background: transparent; font-size: var(--font-size-xl); cursor: pointer; }.resource-sharing-panel ul { display: grid; gap: var(--space-3); margin: 0; padding: 0; list-style: none; }.resource-sharing-panel li { padding-bottom: var(--space-3); border-bottom: var(--component-border-width) solid var(--color-border-subtle); } @media (max-width: 700px) { .resource-sharing-panel { position: fixed; z-index: 40; inset: 2.5vh 2.5vw; width: auto; border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-lg); } }
</style>
