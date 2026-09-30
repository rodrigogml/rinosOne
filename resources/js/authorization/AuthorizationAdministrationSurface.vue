<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import UiAlert from '../design-system/UiAlert.vue';
import UiButton from '../design-system/UiButton.vue';
import UiField from '../design-system/UiField.vue';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';
import AdvancedAuthorizationControls from './AdvancedAuthorizationControls.vue';
import { ContextualAuthorizationAdministrationResponseShapeError, parseContextualAdministrationAuditEvent, parseContextualAdministrationCatalogItem, parseContextualAdministrationContext, parseContextualAdministrationSubject, type ContextualAdministrationAuditEvent, type ContextualAdministrationCatalogItem, type ContextualAdministrationContext, type ContextualAdministrationSubject } from './contextualAdministrationApi';

const props = defineProps<{ surface: WorkspaceSurface }>();
const emit = defineEmits<{ accessDenied: [] }>();
const { t } = useI18n();
const context = ref<ContextualAdministrationContext | null>(null);
const contextHeading = ref<HTMLHeadingElement | null>(null);
const subjects = ref<ContextualAdministrationSubject[]>([]);
const roles = ref<ContextualAdministrationCatalogItem[]>([]);
const groups = ref<ContextualAdministrationCatalogItem[]>([]);
const selectedSubject = ref<ContextualAdministrationSubject | null>(null);
const selectedRoleId = ref<number | null>(null);
const capabilities = ref<string[]>([]);
const auditEvents = ref<ContextualAdministrationAuditEvent[]>([]);
const auditOperation = ref(''); const auditTargetType = ref(''); const auditTargetId = ref(''); const auditOccurredAfter = ref(''); const auditOccurredBefore = ref('');
const query = ref(''); const loading = ref(false); const processing = ref(false); const stale = ref(false); const error = ref(''); const feedback = ref(''); const contextVersion = ref(''); const confirmAssignment = ref(false);
const subjectPage = ref(1); const subjectLastPage = ref(1);
const base = computed(() => {
    if (props.surface.destinationId === 'personal.authorization-administration') return '/api/v1/authorization/personal';
    if (props.surface.destinationId === 'platform.authorization-administration') return '/api/v1/platform/authorization';

    return `/api/v1/tenants/${encodeURIComponent(props.surface.tenantId ?? 0)}/authorization`;
});
const offline = computed(() => !navigator.onLine);
const canManage = computed(() => props.surface.destinationId === 'tenant.authorization-administration' && context.value?.capabilities.canManageRoles === true);
const selectedRole = computed(() => roles.value.find((role) => role.id === selectedRoleId.value) ?? null);
const auditPath = computed(() => props.surface.destinationId === 'tenant.authorization-administration' ? `${base.value}/audit-events/contextual` : `${base.value}/audit-events`);

function message(reason: unknown): string {
    if (reason instanceof ContextualAuthorizationAdministrationResponseShapeError) return t('access.authorization.requestFailed');
    if (axios.isAxiosError(reason) && reason.response?.status === 403) return t('access.authorization.accessDenied');
    if (axios.isAxiosError(reason) && reason.response?.status === 409) return t('access.authorization.lastAdministrator');
    if (axios.isAxiosError(reason) && reason.response?.status === 412) return t('access.authorization.stale');
    return offline.value ? t('access.authorization.offline') : t('access.authorization.requestFailed');
}
function handleFailure(reason: unknown): void {
    error.value = message(reason);
    if (axios.isAxiosError(reason) && reason.response?.status === 403) emit('accessDenied');
}
function pagination(value: unknown): { page: number; lastPage: number } {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return { page: 1, lastPage: 1 };
    const item = value as Record<string, unknown>;
    return { page: typeof item.page === 'number' && item.page > 0 ? item.page : 1, lastPage: typeof item.lastPage === 'number' && item.lastPage > 0 ? item.lastPage : 1 };
}
async function load(page = 1): Promise<void> {
    if ((props.surface.destinationId === 'tenant.authorization-administration' && !props.surface.tenantId) || offline.value) { error.value = t('access.authorization.offline'); return; }
    loading.value = true; error.value = '';
    try {
        const params = new URLSearchParams({ perPage: '25', page: String(page) }); if (query.value.trim()) params.set('query', query.value.trim());
        const [contextResponse, subjectsResponse, rolesResponse, groupsResponse] = await Promise.all([axios.get(`${base.value}/context`), axios.get(`${base.value}/subjects?${params}`), axios.get(`${base.value}/roles?perPage=50`), axios.get(`${base.value}/groups/contextual?perPage=50`)]);
        context.value = parseContextualAdministrationContext(contextResponse.data?.context);
        contextVersion.value = typeof contextResponse.data?.contextVersion === 'string' ? contextResponse.data.contextVersion : '';
        if (!Array.isArray(subjectsResponse.data?.subjects) || !Array.isArray(rolesResponse.data?.roles) || !Array.isArray(groupsResponse.data?.groups)) throw new ContextualAuthorizationAdministrationResponseShapeError();
        subjects.value = subjectsResponse.data.subjects.map(parseContextualAdministrationSubject); roles.value = rolesResponse.data.roles.map(parseContextualAdministrationCatalogItem); groups.value = groupsResponse.data.groups.map(parseContextualAdministrationCatalogItem); ({ page: subjectPage.value, lastPage: subjectLastPage.value } = pagination(subjectsResponse.data?.pagination)); stale.value = false;
    } catch (reason) { stale.value = subjects.value.length > 0; handleFailure(reason); }
    finally { loading.value = false; }
}
async function inspect(subject: ContextualAdministrationSubject): Promise<void> {
    if (offline.value) return; processing.value = true; error.value = '';
    try { const response = await axios.get(`${base.value}/subjects/${encodeURIComponent(subject.subjectType)}/${subject.subjectId}/effective-access`); selectedSubject.value = parseContextualAdministrationSubject(response.data?.effectiveAccess?.subject); capabilities.value = Array.isArray(response.data?.effectiveAccess?.effectiveCapabilities) ? response.data.effectiveAccess.effectiveCapabilities.filter((value: unknown): value is string => typeof value === 'string') : []; }
    catch (reason) { handleFailure(reason); } finally { processing.value = false; }
}
async function loadAudit(): Promise<void> {
    if (offline.value) return; loading.value = true; error.value = '';
    try { const params = new URLSearchParams({ perPage: '25' }); if (auditOperation.value.trim()) params.set('operation', auditOperation.value.trim()); if (auditTargetType.value.trim()) params.set('targetType', auditTargetType.value.trim()); if (auditTargetId.value.trim()) params.set('targetId', auditTargetId.value.trim()); if (auditOccurredAfter.value) params.set('occurredAfter', new Date(auditOccurredAfter.value).toISOString()); if (auditOccurredBefore.value) params.set('occurredBefore', new Date(auditOccurredBefore.value).toISOString()); const response = await axios.get(`${auditPath.value}?${params}`); if (!Array.isArray(response.data?.events)) throw new ContextualAuthorizationAdministrationResponseShapeError(); auditEvents.value = response.data.events.map(parseContextualAdministrationAuditEvent); }
    catch (reason) { handleFailure(reason); } finally { loading.value = false; }
}
function formatTimestamp(value: string | null): string { return value === null ? 'Sem expiração' : new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)); }
async function assign(): Promise<void> {
    if (!selectedSubject.value || selectedSubject.value.subjectType !== 'USER' || !selectedRole.value || !canManage.value || offline.value) return; processing.value = true; error.value = ''; feedback.value = '';
    try { await axios.post(`${base.value}/roles/${selectedRole.value.id}/assignments`, { subjectType: 'USER', subjectId: selectedSubject.value.subjectId, expectedContextVersion: contextVersion.value }); feedback.value = t('access.authorization.roleAssigned'); confirmAssignment.value = false; await load(); }
    catch (reason) { handleFailure(reason); } finally { processing.value = false; }
}
function search(): void { void load(1); }
onMounted(async () => {
    contextHeading.value?.focus();
    await load();
    await loadAudit();
});
</script>

<template>
    <section class="authorization-administration" :aria-busy="loading || processing">
        <header class="authorization-administration__context"><p>{{ context?.scope ?? 'TENANT' }}</p><h2 ref="contextHeading" tabindex="-1">{{ context?.displayName ?? t('access.authorization.loading') }}</h2></header>
        <UiAlert v-if="offline" tone="warning">{{ t('access.authorization.offline') }}</UiAlert><UiAlert v-if="stale" tone="warning">{{ t('access.authorization.stale') }}</UiAlert><UiAlert v-if="error" tone="error">{{ error }}</UiAlert><UiAlert v-if="feedback" tone="success">{{ feedback }}</UiAlert>
        <section class="authorization-administration__form"><div class="authorization-administration__audit-header"><h3>Pessoas e identidades</h3><UiButton variant="secondary" :loading="loading" :disabled="offline" @click="load(subjectPage)">{{ t('access.authorization.reload') }}</UiButton></div><form @submit.prevent="search"><UiField id="authorization-subject-search" label="Buscar pessoa ou identidade" v-slot="field"><input id="authorization-subject-search" v-model.trim="query" :aria-describedby="field.describedBy"></UiField><UiButton type="submit" variant="secondary" :disabled="offline">{{ t('access.authorization.filter') }}</UiButton></form><p v-if="!loading && !subjects.length">Nenhuma pessoa ou identidade disponível neste contexto.</p><ul class="authorization-administration__subject-list"><li v-for="subject in subjects" :key="`${subject.subjectType}-${subject.subjectId}`"><span><strong>{{ subject.displayName }}</strong> · {{ subject.subjectType === 'SERVICE_IDENTITY' ? 'Identidade de serviço' : 'Pessoa' }}</span><UiButton variant="secondary" :disabled="offline" @click="inspect(subject)">Ver acessos</UiButton></li></ul><nav v-if="subjectLastPage > 1" aria-label="Paginação de participantes"><UiButton variant="secondary" :disabled="offline || loading || subjectPage === 1" @click="load(subjectPage - 1)">{{ t('access.authorization.previousPage') }}</UiButton><span>{{ subjectPage }} / {{ subjectLastPage }}</span><UiButton variant="secondary" :disabled="offline || loading || subjectPage === subjectLastPage" @click="load(subjectPage + 1)">{{ t('access.authorization.nextPage') }}</UiButton></nav></section>
        <section v-if="selectedSubject" class="authorization-administration__result"><h3>O que {{ selectedSubject.displayName }} pode fazer?</h3><p>Vigência do acesso: {{ formatTimestamp(selectedSubject.expiresAt) }}.</p><h4>Por que este acesso existe?</h4><p v-if="!selectedSubject.accessSources.length">Nenhuma fonte adicional de acesso foi identificada.</p><ul v-else><li v-for="source in selectedSubject.accessSources" :key="`${source.type}-${source.displayName}`"><strong>{{ source.displayName }}</strong> · {{ source.type }} · {{ source.scope }} · {{ formatTimestamp(source.expiresAt) }}</li></ul><h4>Capacidades efetivas</h4><p v-if="!capabilities.length">Nenhuma capacidade efetiva disponível.</p><ul v-else><li v-for="capability in capabilities" :key="capability">{{ capability }}</li></ul></section>
        <section class="authorization-administration__form"><h3>Papéis e grupos</h3><p v-if="groups.length">Grupos disponíveis: <span v-for="group in groups.filter((item) => item.active)" :key="group.id">{{ group.displayName }} </span></p><p v-else>Nenhum grupo ativo foi criado neste contexto.</p><h3>Associar papel</h3><p v-if="!canManage">Você pode consultar os papéis deste contexto, mas não alterá-los.</p><label>Pessoa <select v-model="selectedSubject" :disabled="!canManage"><option :value="null">Selecione uma pessoa</option><option v-for="subject in subjects.filter((item) => item.subjectType === 'USER')" :key="subject.subjectId" :value="subject">{{ subject.displayName }}</option></select></label><label>Papel <select v-model="selectedRoleId" :disabled="!canManage"><option :value="null">Selecione um papel</option><option v-for="role in roles.filter((item) => item.active)" :key="role.id" :value="role.id">{{ role.displayName }}{{ role.systemManaged ? ' · sistema' : '' }}</option></select></label><p v-if="selectedRole">{{ selectedRole.description || selectedRole.key }}</p><UiButton :disabled="!canManage || selectedSubject?.subjectType !== 'USER' || !selectedRole || offline" @click="confirmAssignment = true">{{ t('access.authorization.assignRole') }}</UiButton></section>
        <section class="authorization-administration__audit"><div class="authorization-administration__audit-header"><h3>{{ t('access.authorization.audit') }}</h3><UiButton variant="secondary" :loading="loading" :disabled="offline" @click="loadAudit">{{ t('access.authorization.reload') }}</UiButton></div><form class="authorization-administration__audit-filters" @submit.prevent="loadAudit"><UiField id="authorization-audit-operation" label="Operação" v-slot="field"><input id="authorization-audit-operation" v-model.trim="auditOperation" :aria-describedby="field.describedBy"></UiField><UiField id="authorization-audit-target-type" label="Tipo de alvo" v-slot="field"><input id="authorization-audit-target-type" v-model.trim="auditTargetType" :aria-describedby="field.describedBy"></UiField><UiField id="authorization-audit-target-id" label="ID do alvo" v-slot="field"><input id="authorization-audit-target-id" v-model.trim="auditTargetId" inputmode="numeric" :aria-describedby="field.describedBy"></UiField><UiField id="authorization-audit-after" label="A partir de" v-slot="field"><input id="authorization-audit-after" v-model="auditOccurredAfter" type="datetime-local" :aria-describedby="field.describedBy"></UiField><UiField id="authorization-audit-before" label="Até" v-slot="field"><input id="authorization-audit-before" v-model="auditOccurredBefore" type="datetime-local" :aria-describedby="field.describedBy"></UiField><UiButton type="submit" variant="secondary" :disabled="offline">{{ t('access.authorization.filter') }}</UiButton></form><p v-if="!auditEvents.length">{{ t('access.authorization.emptyAudit') }}</p><table v-else><thead><tr><th>{{ t('access.authorization.occurredAt') }}</th><th>{{ t('access.authorization.operation') }}</th><th>{{ t('access.authorization.target') }}</th></tr></thead><tbody><tr v-for="event in auditEvents" :key="event.id"><td>{{ event.occurredAt }}</td><td>{{ event.operation }}</td><td>{{ event.targetType }} #{{ event.targetId }}</td></tr></tbody></table></section>
        <AdvancedAuthorizationControls v-if="surface.destinationId === 'tenant.authorization-administration' && context?.capabilities.canUseAdvancedControls" :surface="surface" />
        <div v-if="confirmAssignment" class="authorization-administration__confirmation" role="dialog" aria-modal="true" aria-label="Confirmar associação de papel"><p>Associar “{{ selectedRole?.displayName }}” a “{{ selectedSubject?.displayName }}” no contexto “{{ context?.displayName }}”?</p><p>Efeito: acesso pelas capacidades do papel, sem vigência definida.</p><UiButton :loading="processing" @click="assign">{{ t('access.authorization.confirm') }}</UiButton><UiButton variant="secondary" :disabled="processing" @click="confirmAssignment = false">{{ t('access.authorization.cancel') }}</UiButton></div>
    </section>
</template>
