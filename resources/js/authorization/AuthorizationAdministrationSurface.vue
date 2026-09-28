<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import UiAlert from '../design-system/UiAlert.vue';
import UiButton from '../design-system/UiButton.vue';
import UiField from '../design-system/UiField.vue';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';

type Tab = 'roles' | 'groups' | 'access' | 'advanced' | 'audit';
const props = defineProps<{ surface: WorkspaceSurface }>();
const { t } = useI18n();
const tab = ref<Tab>('roles'); const loading = ref(false); const processing = ref(false); const error = ref(''); const feedback = ref(''); const stale = ref(false);
const roleKey = ref(''); const roleName = ref(''); const roleDescription = ref(''); const groupName = ref(''); const subjectId = ref(''); const permissionKey = ref(''); const assignmentRoleId = ref(''); const assignmentUserId = ref(''); const groupId = ref(''); const groupUserId = ref(''); const membershipId = ref(''); const confirmMembershipRemoval = ref(false);
const effectiveAccess = ref<Record<string, unknown> | null>(null); const explanation = ref<Record<string, unknown> | null>(null); const events = ref<Array<Record<string, unknown>>>([]); const auditOperation = ref(''); const auditPage = ref(1); const auditTotal = ref(0);
const temporaryPermissionId = ref(''); const temporaryStartsAt = ref(''); const temporaryEndsAt = ref(''); const accessRequests = ref<Array<Record<string, unknown>>>([]);
const policyKey = ref(''); const policyPermissionId = ref(''); const policyMaximum = ref(''); const separationPermissionId = ref(''); const incompatiblePermissionId = ref('');
const delegationDelegatorId = ref(''); const delegationRecipientId = ref(''); const delegationPermissionId = ref(''); const delegationOriginId = ref(''); const delegationStartsAt = ref(''); const delegationEndsAt = ref('');
const technicalOwnerId = ref(''); const technicalName = ref(''); const technicalPurpose = ref(''); const technicalPermissionId = ref(''); const technicalIdentityId = ref(''); const technicalCredentialName = ref(''); const issuedApiKey = ref('');
const tenantId = computed(() => props.surface.tenantId);
const base = computed(() => `/api/v1/tenants/${encodeURIComponent(tenantId.value ?? 0)}/authorization`);
const offline = computed(() => !navigator.onLine);
const tabs: Array<{ id: Tab; label: string }> = [
    { id: 'roles', label: t('access.authorization.tabs.roles') }, { id: 'groups', label: t('access.authorization.tabs.groups') },
    { id: 'access', label: t('access.authorization.tabs.access') }, { id: 'advanced', label: t('access.authorization.tabs.advanced') }, { id: 'audit', label: t('access.authorization.tabs.audit') },
];

function apiMessage(reason: unknown): string {
    if (axios.isAxiosError(reason) && reason.response?.status === 403) return t('access.authorization.accessDenied');
    return t('access.authorization.requestFailed');
}
async function loadAudit(): Promise<void> {
    if (!tenantId.value || offline.value) { error.value = t('access.authorization.offline'); return; }
    loading.value = true; error.value = '';
    try { const query = new URLSearchParams({ perPage: '25', page: String(auditPage.value) }); if (auditOperation.value.trim()) query.set('operation', auditOperation.value.trim()); const response = await axios.get(`${base.value}/audit-events?${query.toString()}`); events.value = Array.isArray(response.data?.events) ? response.data.events : []; auditTotal.value = Number(response.data?.pagination?.total) || 0; stale.value = false; }
    catch (reason) { stale.value = events.value.length > 0; error.value = apiMessage(reason); }
    finally { loading.value = false; }
}
async function createRole(): Promise<void> {
    if (!roleKey.value.trim() || !roleName.value.trim() || offline.value) return;
    processing.value = true; stale.value = true; error.value = ''; feedback.value = '';
    try { await axios.post(`${base.value}/roles`, { key: roleKey.value.trim(), displayName: roleName.value.trim(), description: roleDescription.value.trim() }); roleKey.value = ''; roleName.value = ''; roleDescription.value = ''; feedback.value = t('access.authorization.roleCreated'); await loadAudit(); }
    catch (reason) { error.value = apiMessage(reason); }
    finally { processing.value = false; }
}
async function createGroup(): Promise<void> {
    if (!groupName.value.trim() || offline.value) return;
    processing.value = true; stale.value = true; error.value = ''; feedback.value = '';
    try { await axios.post(`${base.value}/groups`, { displayName: groupName.value.trim() }); groupName.value = ''; feedback.value = t('access.authorization.groupCreated'); await loadAudit(); }
    catch (reason) { error.value = apiMessage(reason); }
    finally { processing.value = false; }
}
async function assignRole(): Promise<void> {
    const roleId = Number(assignmentRoleId.value); const userId = Number(assignmentUserId.value);
    if (!Number.isSafeInteger(roleId) || roleId < 1 || !Number.isSafeInteger(userId) || userId < 1 || offline.value) return;
    processing.value = true; stale.value = true; error.value = ''; feedback.value = '';
    try { await axios.post(`${base.value}/roles/${encodeURIComponent(roleId)}/assignments`, { userId }); feedback.value = t('access.authorization.roleAssigned'); await loadAudit(); }
    catch (reason) { error.value = apiMessage(reason); } finally { processing.value = false; }
}
async function addGroupMember(): Promise<void> {
    const id = Number(groupId.value); const userId = Number(groupUserId.value);
    if (!Number.isSafeInteger(id) || id < 1 || !Number.isSafeInteger(userId) || userId < 1 || offline.value) return;
    processing.value = true; stale.value = true; error.value = ''; feedback.value = '';
    try { await axios.post(`${base.value}/groups/${encodeURIComponent(id)}/members`, { userId }); feedback.value = t('access.authorization.groupMemberAdded'); await loadAudit(); }
    catch (reason) { error.value = apiMessage(reason); } finally { processing.value = false; }
}
async function removeMembership(): Promise<void> {
    const id = Number(membershipId.value); if (!Number.isSafeInteger(id) || id < 1 || offline.value) return;
    processing.value = true; stale.value = true; error.value = ''; feedback.value = '';
    try { await axios.delete(`${base.value}/memberships/${encodeURIComponent(id)}`); feedback.value = t('access.authorization.membershipRemoved'); membershipId.value = ''; await loadAudit(); }
    catch (reason) { error.value = axios.isAxiosError(reason) && reason.response?.data?.error?.code === 'AUTHORIZATION_ADMINISTRATION_CONFLICT' ? t('access.authorization.lastAdministrator') : apiMessage(reason); }
    finally { processing.value = false; confirmMembershipRemoval.value = false; }
}
async function inspectAccess(explain = false): Promise<void> {
    const id = Number(subjectId.value); if (!Number.isSafeInteger(id) || id < 1 || offline.value) return;
    processing.value = true; error.value = ''; feedback.value = '';
    try {
        if (explain) { const response = await axios.post(`${base.value}/explain/users/${encodeURIComponent(id)}`, { permissionKey: permissionKey.value.trim() }); explanation.value = response.data?.explanation ?? null; }
        else { const response = await axios.get(`${base.value}/effective-access/users/${encodeURIComponent(id)}`); effectiveAccess.value = response.data?.effectiveAccess ?? null; }
    } catch (reason) { error.value = apiMessage(reason); } finally { processing.value = false; }
}
async function loadAccessRequests(): Promise<void> {
    if (!tenantId.value || offline.value) return;
    loading.value = true; error.value = '';
    try { const response = await axios.get(`${base.value}/advanced/access-requests`); accessRequests.value = Array.isArray(response.data?.accessRequests) ? response.data.accessRequests : []; stale.value = false; }
    catch (reason) { stale.value = accessRequests.value.length > 0; error.value = apiMessage(reason); }
    finally { loading.value = false; }
}
async function requestTemporaryAccess(): Promise<void> {
    const permissionId = Number(temporaryPermissionId.value);
    if (!Number.isSafeInteger(permissionId) || permissionId < 1 || !temporaryStartsAt.value || !temporaryEndsAt.value || offline.value) return;
    processing.value = true; error.value = ''; feedback.value = '';
    try { await axios.post(`${base.value}/advanced/access-requests`, { permissionId, startsAt: new Date(temporaryStartsAt.value).toISOString(), endsAt: new Date(temporaryEndsAt.value).toISOString() }); temporaryPermissionId.value = ''; feedback.value = t('access.authorization.temporaryRequested'); await loadAccessRequests(); }
    catch (reason) { error.value = apiMessage(reason); }
    finally { processing.value = false; }
}
async function decideAccessRequest(id: unknown, action: 'approval' | 'revocation'): Promise<void> {
    const requestId = Number(id); if (!Number.isSafeInteger(requestId) || requestId < 1 || offline.value) return;
    processing.value = true; error.value = ''; feedback.value = '';
    try { await axios.post(`${base.value}/advanced/access-requests/${encodeURIComponent(requestId)}/${action}`); feedback.value = action === 'approval' ? t('access.authorization.temporaryApproved') : t('access.authorization.temporaryRevoked'); await loadAccessRequests(); }
    catch (reason) { error.value = apiMessage(reason); }
    finally { processing.value = false; }
}
async function publishPolicy(): Promise<void> {
    const permissionId = Number(policyPermissionId.value); const maximum = Number(policyMaximum.value);
    if (!policyKey.value.trim() || !Number.isSafeInteger(permissionId) || permissionId < 1 || !Number.isFinite(maximum) || maximum < 0 || offline.value) return;
    processing.value = true; error.value = ''; feedback.value = '';
    try { const policy = (await axios.post(`${base.value}/advanced/policies`, { key: policyKey.value.trim(), definition: { all: [{ type: 'AMOUNT_MAXIMUM', maximum }] } })).data?.policy; await axios.post(`${base.value}/advanced/policies/${encodeURIComponent(policy.id)}/bindings`, { permissionId }); policyKey.value = ''; feedback.value = t('access.authorization.policyPublished'); await loadAudit(); }
    catch (reason) { error.value = apiMessage(reason); } finally { processing.value = false; }
}
async function createSeparationRule(): Promise<void> {
    const permissionId = Number(separationPermissionId.value); const incompatiblePermissionIdValue = Number(incompatiblePermissionId.value);
    if (!Number.isSafeInteger(permissionId) || permissionId < 1 || !Number.isSafeInteger(incompatiblePermissionIdValue) || incompatiblePermissionIdValue < 1 || permissionId === incompatiblePermissionIdValue || offline.value) return;
    processing.value = true; error.value = ''; feedback.value = '';
    try { await axios.post(`${base.value}/advanced/separation-rules`, { permissionId, incompatiblePermissionId: incompatiblePermissionIdValue }); feedback.value = t('access.authorization.separationRuleCreated'); await loadAudit(); }
    catch (reason) { error.value = apiMessage(reason); } finally { processing.value = false; }
}
async function createDelegation(): Promise<void> {
    const delegatorUserId = Number(delegationDelegatorId.value); const recipientUserId = Number(delegationRecipientId.value); const permissionId = Number(delegationPermissionId.value); const originAssignmentId = Number(delegationOriginId.value);
    if (![delegatorUserId, recipientUserId, permissionId, originAssignmentId].every((value) => Number.isSafeInteger(value) && value > 0) || !delegationStartsAt.value || !delegationEndsAt.value || offline.value) return;
    processing.value = true; error.value = ''; feedback.value = '';
    try { await axios.post(`${base.value}/advanced/delegations`, { delegatorUserId, recipientUserId, permissionId, originAssignmentId, startsAt: new Date(delegationStartsAt.value).toISOString(), endsAt: new Date(delegationEndsAt.value).toISOString() }); feedback.value = t('access.authorization.delegationCreated'); await loadAudit(); }
    catch (reason) { error.value = apiMessage(reason); } finally { processing.value = false; }
}
async function createTechnicalIdentity(): Promise<void> {
    const ownerUserId = Number(technicalOwnerId.value); const permissionId = Number(technicalPermissionId.value);
    if (!Number.isSafeInteger(ownerUserId) || ownerUserId < 1 || !technicalName.value.trim() || !technicalPurpose.value.trim() || !Number.isSafeInteger(permissionId) || permissionId < 1 || offline.value) return;
    processing.value = true; error.value = ''; feedback.value = '';
    try { const identity = (await axios.post(`${base.value}/advanced/service-identities`, { ownerUserId, displayName: technicalName.value.trim(), purpose: technicalPurpose.value.trim() })).data?.serviceIdentity; await axios.post(`${base.value}/advanced/service-identities/${encodeURIComponent(identity.id)}/permissions`, { permissionId }); technicalIdentityId.value = String(identity.id); feedback.value = t('access.authorization.technicalIdentityCreated'); await loadAudit(); }
    catch (reason) { error.value = apiMessage(reason); } finally { processing.value = false; }
}
async function issueTechnicalCredential(): Promise<void> {
    const identityId = Number(technicalIdentityId.value);
    if (!Number.isSafeInteger(identityId) || identityId < 1 || !technicalCredentialName.value.trim() || offline.value) return;
    processing.value = true; error.value = ''; feedback.value = ''; issuedApiKey.value = '';
    try { const response = await axios.post(`${base.value}/advanced/service-identities/${encodeURIComponent(identityId)}/credentials`, { displayName: technicalCredentialName.value.trim() }); issuedApiKey.value = String(response.data?.apiKey ?? ''); feedback.value = t('access.authorization.technicalCredentialIssued'); await loadAudit(); }
    catch (reason) { error.value = apiMessage(reason); } finally { processing.value = false; }
}
onMounted(async () => { await Promise.all([loadAudit(), loadAccessRequests()]); });
</script>

<template>
    <section class="authorization-administration" :aria-busy="loading || processing">
        <div class="authorization-administration__tabs" role="tablist" :aria-label="t('access.authorization.title')">
            <button v-for="item in tabs" :key="item.id" type="button" role="tab" :aria-selected="tab === item.id" :class="{ 'authorization-administration__tab--active': tab === item.id }" @click="tab = item.id">{{ item.label }}</button>
        </div>
        <label class="authorization-administration__mobile-tabs"><span>{{ t('access.authorization.section') }}</span><select v-model="tab"><option v-for="item in tabs" :key="item.id" :value="item.id">{{ item.label }}</option></select></label>
        <UiAlert v-if="offline" tone="warning">{{ t('access.authorization.offline') }}</UiAlert>
        <UiAlert v-if="error" tone="error">{{ error }}</UiAlert><UiAlert v-if="feedback" tone="success">{{ feedback }}</UiAlert>
        <section v-if="tab === 'roles'" class="authorization-administration__form"><form @submit.prevent="createRole"><h3>{{ t('access.authorization.createRole') }}</h3><UiField id="authorization-role-key" :label="t('access.authorization.roleKey')" required v-slot="field"><input id="authorization-role-key" v-model.trim="roleKey" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required></UiField><UiField id="authorization-role-name" :label="t('access.authorization.displayName')" required v-slot="field"><input id="authorization-role-name" v-model.trim="roleName" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required></UiField><UiField id="authorization-role-description" :label="t('access.authorization.description')" v-slot="field"><input id="authorization-role-description" v-model.trim="roleDescription" :aria-describedby="field.describedBy"></UiField><UiButton type="submit" :loading="processing" :disabled="offline">{{ t('access.authorization.createRole') }}</UiButton></form><form @submit.prevent="assignRole"><h3>{{ t('access.authorization.assignRole') }}</h3><UiField id="authorization-assignment-role" :label="t('access.authorization.roleId')" required v-slot="field"><input id="authorization-assignment-role" v-model="assignmentRoleId" inputmode="numeric" :aria-describedby="field.describedBy" required></UiField><UiField id="authorization-assignment-user" :label="t('access.authorization.userId')" required v-slot="field"><input id="authorization-assignment-user" v-model="assignmentUserId" inputmode="numeric" :aria-describedby="field.describedBy" required></UiField><UiButton type="submit" :loading="processing" :disabled="offline">{{ t('access.authorization.assignRole') }}</UiButton></form></section>
        <section v-else-if="tab === 'groups'" class="authorization-administration__form"><form @submit.prevent="createGroup"><h3>{{ t('access.authorization.createGroup') }}</h3><UiField id="authorization-group-name" :label="t('access.authorization.displayName')" required v-slot="field"><input id="authorization-group-name" v-model.trim="groupName" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required></UiField><UiButton type="submit" :loading="processing" :disabled="offline">{{ t('access.authorization.createGroup') }}</UiButton></form><form @submit.prevent="addGroupMember"><h3>{{ t('access.authorization.addGroupMember') }}</h3><UiField id="authorization-group-id" :label="t('access.authorization.groupId')" required v-slot="field"><input id="authorization-group-id" v-model="groupId" inputmode="numeric" :aria-describedby="field.describedBy" required></UiField><UiField id="authorization-group-user" :label="t('access.authorization.userId')" required v-slot="field"><input id="authorization-group-user" v-model="groupUserId" inputmode="numeric" :aria-describedby="field.describedBy" required></UiField><UiButton type="submit" :loading="processing" :disabled="offline">{{ t('access.authorization.addGroupMember') }}</UiButton></form></section>
        <section v-else-if="tab === 'access'" class="authorization-administration__form"><h3>{{ t('access.authorization.access') }}</h3><UiField id="authorization-subject" :label="t('access.authorization.userId')" required v-slot="field"><input id="authorization-subject" v-model="subjectId" inputmode="numeric" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required></UiField><UiButton :loading="processing" :disabled="offline" @click="inspectAccess()">{{ t('access.authorization.effectiveAccess') }}</UiButton><UiField id="authorization-permission" :label="t('access.authorization.permissionKey')" v-slot="field"><input id="authorization-permission" v-model.trim="permissionKey" :aria-describedby="field.describedBy"></UiField><UiButton variant="secondary" :loading="processing" :disabled="offline || !permissionKey" @click="inspectAccess(true)">{{ t('access.authorization.explain') }}</UiButton><form @submit.prevent="confirmMembershipRemoval = true"><h3>{{ t('access.authorization.removeMembership') }}</h3><UiField id="authorization-membership" :label="t('access.authorization.membershipId')" required v-slot="field"><input id="authorization-membership" v-model="membershipId" inputmode="numeric" :aria-describedby="field.describedBy" required></UiField><UiButton variant="destructive" type="submit" :disabled="offline">{{ t('access.authorization.removeMembership') }}</UiButton></form><section v-if="effectiveAccess" class="authorization-administration__result"><h4>{{ t('access.authorization.effectiveAccess') }}</h4><p>{{ t('access.authorization.membership') }}: {{ (effectiveAccess.membership as Record<string, unknown> | null)?.state ?? t('access.authorization.notAvailable') }}</p><ul><li v-for="role in (effectiveAccess.directRoles as Array<Record<string, unknown>> ?? [])" :key="String(role.id)">{{ role.key }}</li></ul><p>{{ t('access.authorization.groups') }}: {{ (effectiveAccess.groups as Array<unknown> ?? []).length }}</p><p>{{ t('access.authorization.restrictions') }}: {{ (effectiveAccess.restrictions as Array<unknown> ?? []).length }}</p></section><section v-if="explanation" class="authorization-administration__result"><h4>{{ t('access.authorization.explain') }}</h4><p>{{ t('access.authorization.decision') }}: {{ explanation.allowed ? t('access.authorization.allowed') : t('access.authorization.denied') }}</p><p>{{ explanation.reasonCode }}</p></section></section>
        <section v-else-if="tab === 'advanced'" class="authorization-administration__form"><form @submit.prevent="requestTemporaryAccess"><h3>{{ t('access.authorization.temporaryAccess') }}</h3><p>{{ t('access.authorization.temporaryAccessHelp') }}</p><UiField id="authorization-temporary-permission" :label="t('access.authorization.permissionId')" required v-slot="field"><input id="authorization-temporary-permission" v-model="temporaryPermissionId" inputmode="numeric" :aria-describedby="field.describedBy" required></UiField><UiField id="authorization-temporary-starts-at" :label="t('access.authorization.startsAt')" required v-slot="field"><input id="authorization-temporary-starts-at" v-model="temporaryStartsAt" type="datetime-local" :aria-describedby="field.describedBy" required></UiField><UiField id="authorization-temporary-ends-at" :label="t('access.authorization.endsAt')" required v-slot="field"><input id="authorization-temporary-ends-at" v-model="temporaryEndsAt" type="datetime-local" :aria-describedby="field.describedBy" required></UiField><UiButton type="submit" :loading="processing" :disabled="offline">{{ t('access.authorization.requestTemporaryAccess') }}</UiButton></form><section class="authorization-administration__result"><div class="authorization-administration__audit-header"><h3>{{ t('access.authorization.accessRequestQueue') }}</h3><UiButton variant="secondary" :loading="loading" :disabled="offline" @click="loadAccessRequests">{{ t('access.authorization.reload') }}</UiButton></div><p v-if="!accessRequests.length">{{ t('access.authorization.emptyAccessRequests') }}</p><table v-else><thead><tr><th>{{ t('access.authorization.permissionId') }}</th><th>{{ t('access.authorization.state') }}</th><th>{{ t('access.authorization.endsAt') }}</th><th>{{ t('access.authorization.actions') }}</th></tr></thead><tbody><tr v-for="accessRequest in accessRequests" :key="String(accessRequest.id)"><td>#{{ accessRequest.permissionId }}</td><td>{{ accessRequest.state }}</td><td>{{ accessRequest.endsAt }}</td><td><UiButton v-if="accessRequest.state === 'PENDING'" variant="secondary" :disabled="offline || processing" @click="decideAccessRequest(accessRequest.id, 'approval')">{{ t('access.authorization.approve') }}</UiButton><UiButton v-if="accessRequest.state === 'PENDING' || accessRequest.state === 'APPROVED'" variant="destructive" :disabled="offline || processing" @click="decideAccessRequest(accessRequest.id, 'revocation')">{{ t('access.authorization.revoke') }}</UiButton></td></tr></tbody></table></section></section>
        <section v-else class="authorization-administration__audit"><div class="authorization-administration__audit-header"><h3>{{ t('access.authorization.audit') }}</h3><UiButton variant="secondary" :loading="loading" @click="loadAudit">{{ t('access.authorization.reload') }}</UiButton></div><form @submit.prevent="auditPage = 1; loadAudit()"><UiField id="authorization-audit-operation" :label="t('access.authorization.operation')" v-slot="field"><input id="authorization-audit-operation" v-model.trim="auditOperation" :aria-describedby="field.describedBy"></UiField><UiButton type="submit" variant="secondary" :disabled="offline">{{ t('access.authorization.filter') }}</UiButton></form><p v-if="stale">{{ t('access.authorization.stale') }}</p><p v-if="loading">{{ t('access.authorization.loading') }}</p><p v-else-if="!events.length">{{ t('access.authorization.emptyAudit') }}</p><table v-else><thead><tr><th>{{ t('access.authorization.occurredAt') }}</th><th>{{ t('access.authorization.operation') }}</th><th>{{ t('access.authorization.target') }}</th></tr></thead><tbody><tr v-for="event in events" :key="String(event.id)"><td>{{ event.occurredAt }}</td><td>{{ event.operation }}</td><td>{{ event.targetType }} #{{ event.targetId }}</td></tr></tbody></table><p v-if="auditTotal">{{ t('access.authorization.total', { count: auditTotal }) }}</p><UiButton v-if="auditPage > 1" variant="secondary" @click="auditPage--; loadAudit()">{{ t('access.authorization.previousPage') }}</UiButton><UiButton v-if="events.length === 25 && auditPage * 25 < auditTotal" variant="secondary" @click="auditPage++; loadAudit()">{{ t('access.authorization.nextPage') }}</UiButton></section>
        <div v-if="confirmMembershipRemoval" class="authorization-administration__confirmation" role="dialog" aria-modal="true" :aria-label="t('access.authorization.removeMembership')"><p>{{ t('access.authorization.removeMembershipConfirmation') }}</p><UiButton variant="destructive" :loading="processing" @click="removeMembership">{{ t('access.authorization.confirm') }}</UiButton><UiButton variant="secondary" :disabled="processing" @click="confirmMembershipRemoval = false">{{ t('access.authorization.cancel') }}</UiButton></div>
    </section>
</template>
