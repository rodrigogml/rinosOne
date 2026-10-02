<script setup lang="ts">
import axios from 'axios';
import { computed, nextTick, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import UiAlert from '../design-system/UiAlert.vue';
import UiButton from '../design-system/UiButton.vue';
import UiField from '../design-system/UiField.vue';
import type { DriveItem, DriveWorkspaceTarget } from '../drive/driveWorkspaceApi';
import { ContextualAuthorizationAdministrationResponseShapeError, parseContextualAdministrationResourceShare, parseContextualAdministrationWorkspaceResponsible, type ContextualAdministrationResourceShare, type ContextualAdministrationWorkspaceResponsible } from './contextualAdministrationApi';

const props = defineProps<{ target: DriveWorkspaceTarget; folder: Pick<DriveItem, 'id' | 'displayName'> }>();
const emit = defineEmits<{ close: [] }>();
const { t } = useI18n();
const shares = ref<ContextualAdministrationResourceShare[]>([]);
const responsible = ref<ContextualAdministrationWorkspaceResponsible | null>(null);
const heading = ref<HTMLElement | null>(null);
const revokeHeading = ref<HTMLElement | null>(null);
let opener: HTMLElement | null = null;
let revokeOpener: HTMLElement | null = null;
const loading = ref(true); const error = ref('');
const feedback = ref(''); const contextVersion = ref(''); const recipientQuery = ref(''); const recipients = ref<Array<{ subjectId: number; displayName: string }>>([]); const selectedRecipient = ref<number | null>(null); const newRelation = ref<'READ' | 'EDIT'>('READ'); const editingShareId = ref<number | null>(null); const editingRelation = ref<'READ' | 'EDIT'>('READ'); const revokeShareId = ref<number | null>(null); const processing = ref(false);
const base = computed(() => props.target.kind === 'personal' ? '/api/v1/authorization/personal' : `/api/v1/tenants/${props.target.tenantId}/authorization`);

function commandFailure(reason: unknown): string {
    if (axios.isAxiosError(reason) && reason.response?.status === 412) return t('access.authorization.sharing.stale');
    if (axios.isAxiosError(reason) && reason.response?.status === 409) return t('access.authorization.sharing.conflict');
    return navigator.onLine ? t('access.authorization.sharing.commandFailed') : t('access.authorization.sharing.offline');
}

async function load(): Promise<void> {
    loading.value = true; error.value = '';
    try {
        const response = await axios.get(`${base.value}/resources/FOLDER/${props.folder.id}/shares`);
        if (!Array.isArray(response.data?.shares)) throw new ContextualAuthorizationAdministrationResponseShapeError();
        responsible.value = parseContextualAdministrationWorkspaceResponsible(response.data?.workspaceResponsible);
        shares.value = response.data.shares.map(parseContextualAdministrationResourceShare);
        contextVersion.value = typeof response.data?.contextVersion === 'string' ? response.data.contextVersion : '';
    } catch { error.value = t('access.authorization.sharing.loadFailed'); }
    finally { loading.value = false; }
}
async function searchRecipients(): Promise<void> {
    if (recipientQuery.value.trim().length < 2) { recipients.value = []; return; }
    processing.value = true; error.value = '';
    try {
        const response = await axios.get(`${base.value}/share-recipients?query=${encodeURIComponent(recipientQuery.value.trim())}`);
        if (!Array.isArray(response.data?.recipients) || response.data.recipients.some((item: unknown) => !item || typeof item !== 'object' || typeof (item as { subjectId?: unknown }).subjectId !== 'number' || typeof (item as { displayName?: unknown }).displayName !== 'string')) throw new ContextualAuthorizationAdministrationResponseShapeError();
        recipients.value = response.data.recipients as Array<{ subjectId: number; displayName: string }>;
    } catch (reason) { error.value = commandFailure(reason); }
    finally { processing.value = false; }
}
async function createShare(): Promise<void> {
    if (selectedRecipient.value === null) return;
    processing.value = true; error.value = '';
    try { await axios.post(`${base.value}/resources/FOLDER/${props.folder.id}/shares`, { subjectId: selectedRecipient.value, relation: newRelation.value, expectedContextVersion: contextVersion.value }); selectedRecipient.value = null; recipientQuery.value = ''; recipients.value = []; feedback.value = t('access.authorization.sharing.created'); await load(); }
    catch (reason) { error.value = commandFailure(reason); }
    finally { processing.value = false; }
}
function beginEdit(share: ContextualAdministrationResourceShare): void { editingShareId.value = share.id; editingRelation.value = share.relation === 'READ' ? 'READ' : 'EDIT'; }
async function updateShare(): Promise<void> {
    if (editingShareId.value === null) return;
    processing.value = true; error.value = '';
    try { await axios.patch(`${base.value}/resources/FOLDER/${props.folder.id}/shares/${editingShareId.value}`, { relation: editingRelation.value, expectedContextVersion: contextVersion.value }); editingShareId.value = null; feedback.value = t('access.authorization.sharing.updated'); await load(); }
    catch (reason) { error.value = commandFailure(reason); }
    finally { processing.value = false; }
}
async function revokeShare(): Promise<void> {
    if (revokeShareId.value === null) return;
    processing.value = true; error.value = '';
    try { await axios.delete(`${base.value}/resources/FOLDER/${props.folder.id}/shares/${revokeShareId.value}`); revokeShareId.value = null; feedback.value = t('access.authorization.sharing.revoked'); await load(); }
    catch (reason) { error.value = commandFailure(reason); }
    finally { processing.value = false; }
}
function close(): void { emit('close'); void nextTick(() => opener?.focus()); }
function beginRevoke(shareId: number, event: MouseEvent): void { revokeOpener = event.currentTarget instanceof HTMLElement ? event.currentTarget : null; revokeShareId.value = shareId; void nextTick(() => revokeHeading.value?.focus()); }
function cancelRevoke(): void { revokeShareId.value = null; void nextTick(() => revokeOpener?.focus()); }
function trapFocus(event: KeyboardEvent): void {
    if (event.key !== 'Tab') return;
    const dialog = event.currentTarget as HTMLElement;
    const focusable = Array.from(dialog.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter((item) => !item.hidden);
    if (!focusable.length) return;
    const first = focusable[0]; const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}
onMounted(async () => { opener = document.activeElement instanceof HTMLElement ? document.activeElement : null; await nextTick(); heading.value?.focus(); void load(); });
</script>

<template>
    <aside class="resource-sharing-panel" role="dialog" aria-modal="true" aria-labelledby="resource-sharing-title" :aria-busy="loading || undefined" @keydown="trapFocus">
        <header>
            <div>
                <p>{{ t('access.authorization.sharing.folderSharing', { workspace: target.kind === 'personal' ? t('access.authorization.sharing.personalWorkspace') : t('access.authorization.sharing.tenantWorkspace') }) }}</p>
                <h4 id="resource-sharing-title" ref="heading" tabindex="-1">{{ folder.displayName }}</h4>
            </div>
            <button type="button" :aria-label="t('access.authorization.sharing.close')" @click="close">×</button>
        </header>
        <p v-if="responsible">{{ t('access.authorization.sharing.workspaceResponsible', { name: responsible.displayName }) }}</p>
        <p v-if="loading" aria-live="polite">{{ t('access.authorization.sharing.loading') }}</p>
        <div class="sr-only" aria-live="polite">{{ feedback }}</div>
        <UiAlert v-if="!loading && error" tone="error">{{ error }}</UiAlert>
        <template v-if="!loading && !error">
            <section class="resource-sharing-panel__create">
                <h5>{{ t('access.authorization.sharing.newShare') }}</h5>
                <UiField id="share-recipient-query" :label="t('access.authorization.sharing.recipient')" v-slot="field"><input id="share-recipient-query" v-model.trim="recipientQuery" :aria-describedby="field.describedBy" @input="searchRecipients"></UiField>
                <select v-if="recipients.length" v-model="selectedRecipient" :aria-label="t('access.authorization.sharing.recipientFound')"><option :value="null">{{ t('access.authorization.sharing.selectRecipient') }}</option><option v-for="recipient in recipients" :key="recipient.subjectId" :value="recipient.subjectId">{{ recipient.displayName }}</option></select>
                <select v-model="newRelation" :aria-label="t('access.authorization.sharing.accessLevel')"><option value="READ">{{ t('access.authorization.sharing.read') }}</option><option value="EDIT">{{ t('access.authorization.sharing.edit') }}</option></select>
                <UiButton :disabled="selectedRecipient === null || processing" :loading="processing" @click="createShare">{{ t('access.authorization.sharing.confirm') }}</UiButton>
            </section>
            <p v-if="!shares.length">{{ t('access.authorization.sharing.empty') }}</p>
            <ul v-else>
                <li v-for="share in shares" :key="share.id">
                    <strong>{{ share.grantee.displayName }}</strong> · {{ share.relation === 'READ' ? t('access.authorization.sharing.read') : t('access.authorization.sharing.edit') }} ·
                    <span v-if="share.origin === 'DIRECT'">{{ t('access.authorization.sharing.direct') }}</span><span v-else>{{ t('access.authorization.sharing.inheritedFrom', { resourceId: share.inheritedFrom?.resourceId }) }}</span>
                    <div v-if="share.origin === 'DIRECT' && editingShareId === share.id" class="resource-sharing-panel__actions"><select v-model="editingRelation" :aria-label="t('access.authorization.sharing.newAccessLevel')"><option value="READ">{{ t('access.authorization.sharing.read') }}</option><option value="EDIT">{{ t('access.authorization.sharing.edit') }}</option></select><UiButton :loading="processing" @click="updateShare">{{ t('access.authorization.sharing.confirmChange') }}</UiButton><UiButton variant="secondary" :disabled="processing" @click="editingShareId = null">{{ t('access.authorization.sharing.cancel') }}</UiButton></div>
                    <div v-else-if="share.origin === 'DIRECT'" class="resource-sharing-panel__actions"><UiButton variant="secondary" :disabled="processing" @click="beginEdit(share)">{{ t('access.authorization.sharing.change') }}</UiButton><UiButton variant="secondary" :disabled="processing" @click="beginRevoke(share.id, $event)">{{ t('access.authorization.sharing.revoke') }}</UiButton></div>
                </li>
            </ul>
            <section v-if="revokeShareId !== null" class="resource-sharing-panel__confirmation" role="alertdialog" aria-modal="true" aria-labelledby="resource-sharing-revoke-title"><h5 id="resource-sharing-revoke-title" ref="revokeHeading" tabindex="-1">{{ t('access.authorization.sharing.revoke') }}</h5><p>{{ t('access.authorization.sharing.revokeQuestion') }}</p><UiButton :loading="processing" @click="revokeShare">{{ t('access.authorization.sharing.confirmRevoke') }}</UiButton><UiButton variant="secondary" :disabled="processing" @click="cancelRevoke">{{ t('access.authorization.sharing.cancel') }}</UiButton></section>
            <UiButton variant="secondary" @click="load">{{ t('access.authorization.sharing.reload') }}</UiButton>
        </template>
    </aside>
</template>

<style scoped>
.resource-sharing-panel { position: fixed; z-index: 40; top: 0; right: 0; bottom: 0; display: grid; width: min(24rem, 48vw); align-content: start; gap: var(--space-4); padding: var(--space-4); border-left: var(--component-border-width) solid var(--color-border-subtle); background: var(--color-surface-raised); box-shadow: var(--shadow-float); overflow: auto; }.resource-sharing-panel header { display: flex; align-items: start; justify-content: space-between; gap: var(--space-3); }.resource-sharing-panel header p, .resource-sharing-panel h4, .resource-sharing-panel h5 { margin: 0; }.resource-sharing-panel h4 { color: var(--color-text-primary); font-size: var(--font-size-lg); }.resource-sharing-panel header button { border: 0; background: transparent; font-size: var(--font-size-xl); cursor: pointer; }.resource-sharing-panel ul, .resource-sharing-panel__create, .resource-sharing-panel__actions { display: grid; gap: var(--space-3); margin: 0; padding: 0; }.resource-sharing-panel li { padding-bottom: var(--space-3); border-bottom: var(--component-border-width) solid var(--color-border-subtle); }.resource-sharing-panel select { min-height: var(--control-height-md); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-sm); background: var(--color-surface-raised); color: var(--color-text-primary); font: inherit; }.resource-sharing-panel__actions { margin-top: var(--space-3); grid-template-columns: 1fr 1fr; }.resource-sharing-panel__confirmation { padding: var(--space-3); border: var(--component-border-width) solid var(--color-border-subtle); border-radius: var(--radius-md); background: var(--color-surface-muted); } @media (max-width: 700px) { .resource-sharing-panel { inset: 0; width: auto; border: 0; border-radius: 0; } }
</style>

<style scoped>
.resource-sharing-panel select { border-color: var(--component-field-border); background: var(--component-field-background); }
</style>
