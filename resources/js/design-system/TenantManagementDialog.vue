<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { tenantApi } from '../tenant/tenantApi';
import { createTenantIdempotencyKey } from '../tenant/idempotencyKey';
import type { TenantSummary } from '../tenant/tenantTypes';
import TenantStatusBadge from './TenantStatusBadge.vue';
import UiAlert from './UiAlert.vue';
import UiButton from './UiButton.vue';
import UiDialog from './UiDialog.vue';
import UiField from './UiField.vue';

const open = defineModel<boolean>({ required: true });
const { t } = useI18n();
const displayName = ref('');
const fieldError = ref('');
const feedback = ref('');
const loading = ref(false);
const creating = ref(false);
const changingTenantId = ref<number | null>(null);
const tenants = ref<TenantSummary[]>([]);
const tenantPendingDisable = ref<TenantSummary | null>(null);
const disableConfirmationOpen = computed({
    get: () => tenantPendingDisable.value !== null,
    set: (visible: boolean) => { if (!visible) tenantPendingDisable.value = null; },
});
const online = ref(navigator.onLine);
let idempotencyKey: string | null = null;
let refreshTimer: number | undefined;

async function refresh() {
    window.clearTimeout(refreshTimer);
    loading.value = true; feedback.value = '';
    try { tenants.value = await tenantApi.list(); }
    catch { feedback.value = t('access.tenant.loadFailed'); }
    finally {
        loading.value = false;
        if (open.value && tenants.value.some((tenant) => tenant.state === 'PROVISIONING')) {
            refreshTimer = window.setTimeout(() => { void refresh(); }, 10_000);
        }
    }
}
function validateName(): boolean {
    const trimmed = displayName.value.trim();
    if (!trimmed) { fieldError.value = t('access.tenant.nameRequired'); return false; }
    if (trimmed.length > 120) { fieldError.value = t('access.tenant.nameTooLong'); return false; }
    fieldError.value = '';
    return true;
}
async function create() {
    if (!validateName() || creating.value) return;
    if (!navigator.onLine) { feedback.value = t('access.tenant.offline'); return; }
    creating.value = true; feedback.value = '';
    idempotencyKey ??= createTenantIdempotencyKey();
    try {
        const creation = await tenantApi.create(displayName.value.trim(), idempotencyKey);
        const administratorTenant: TenantSummary = creation.tenant;
        const existingIndex = tenants.value.findIndex((tenant) => tenant.id === administratorTenant.id);
        if (existingIndex === -1) tenants.value = [administratorTenant, ...tenants.value];
        else tenants.value.splice(existingIndex, 1, administratorTenant);
        window.clearTimeout(refreshTimer);
        refreshTimer = window.setTimeout(() => { if (open.value) void refresh(); }, 10_000);
        displayName.value = ''; idempotencyKey = null;
        feedback.value = t('access.tenant.creationAccepted');
    } catch { feedback.value = t('access.tenant.createFailed'); }
    finally { creating.value = false; }
}
async function changeAvailability(tenant: TenantSummary) {
    if (!tenant.canManageAvailability || changingTenantId.value) return;
    if (!navigator.onLine) { feedback.value = t('access.tenant.offline'); return; }
    changingTenantId.value = tenant.id; feedback.value = '';
    const state = tenant.state === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
    try {
        const updated = await tenantApi.changeAvailability(tenant.id, state);
        const index = tenants.value.findIndex((candidate) => candidate.id === tenant.id);
        if (index !== -1) tenants.value.splice(index, 1, updated);
        feedback.value = t('access.tenant.availabilityChanged');
    } catch { feedback.value = t('access.tenant.availabilityFailed'); }
    finally { changingTenantId.value = null; }
}
function requestAvailabilityChange(tenant: TenantSummary) {
    if (tenant.state === 'ACTIVE') { tenantPendingDisable.value = tenant; return; }
    void changeAvailability(tenant);
}
async function confirmDisable() {
    const tenant = tenantPendingDisable.value;
    tenantPendingDisable.value = null;
    if (tenant) await changeAvailability(tenant);
}
function markOnline() { online.value = true; }
function markOffline() { online.value = false; }
watch(open, (visible) => { if (visible) void refresh(); else window.clearTimeout(refreshTimer); }, { immediate: true });
onMounted(() => { window.addEventListener('online', markOnline); window.addEventListener('offline', markOffline); });
onBeforeUnmount(() => { window.clearTimeout(refreshTimer); window.removeEventListener('online', markOnline); window.removeEventListener('offline', markOffline); });
</script>

<template>
    <UiDialog v-model="open" :title="t('access.tenant.managementTitle')">
        <form class="tenant-management__form" novalidate @submit.prevent="create">
            <UiField id="tenant-display-name" :label="t('access.tenant.name')" :error="fieldError" required v-slot="field">
                <input id="tenant-display-name" v-model="displayName" maxlength="120" autocomplete="organization" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required>
            </UiField>
            <UiButton type="submit" :loading="creating" :disabled="!online">{{ t('access.tenant.create') }}</UiButton>
        </form>
        <UiAlert v-if="feedback" :tone="feedback === t('access.tenant.creationAccepted') || feedback === t('access.tenant.availabilityChanged') ? 'success' : 'error'">{{ feedback }}</UiAlert>
        <section class="tenant-management__list" :aria-label="t('access.tenant.managementTitle')">
            <p v-if="loading">{{ t('access.tenant.loading') }}</p>
            <p v-else-if="!tenants.length">{{ t('access.tenant.empty') }}</p>
            <article v-for="tenant in tenants" v-else :key="tenant.id" class="tenant-management__item">
                <div><strong>{{ tenant.displayName }}</strong><TenantStatusBadge :state="tenant.state" /></div>
                <UiButton v-if="tenant.canManageAvailability && (tenant.state === 'ACTIVE' || tenant.state === 'INACTIVE')" variant="secondary" :loading="changingTenantId === tenant.id" @click="requestAvailabilityChange(tenant)">{{ tenant.state === 'ACTIVE' ? t('access.tenant.disable') : t('access.tenant.enable') }}</UiButton>
            </article>
        </section>
    </UiDialog>
    <UiDialog v-model="disableConfirmationOpen" :title="t('access.tenant.disableConfirmationTitle')" destructive>
        <p class="dialog-description">{{ t('access.tenant.disableConfirmationDescription', { name: tenantPendingDisable?.displayName ?? '' }) }}</p>
        <div class="dialog-actions"><UiButton variant="secondary" @click="tenantPendingDisable = null">{{ t('access.actions.cancel') }}</UiButton><UiButton variant="destructive" @click="confirmDisable">{{ t('access.tenant.disable') }}</UiButton></div>
    </UiDialog>
</template>
