<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { tenantApi } from '../tenant/tenantApi';
import { useTenantContextStore } from '../tenant/tenantContextStore';
import type { TenantSummary } from '../tenant/tenantTypes';
import TenantAvatar from './TenantAvatar.vue';

const emit = defineEmits<{ create: []; manage: []; changed: [displayName: string | null] }>();
const { t } = useI18n();
const store = useTenantContextStore();
const root = ref<HTMLElement | null>(null);
const opener = ref<HTMLButtonElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const open = ref(false);
const loading = ref(false);
const error = ref('');
const tenants = ref<TenantSummary[]>([]);
const mobile = ref(false);
const status = ref('');
let returnFocus: HTMLElement | null = null;
const focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const activeName = computed(() => store.context?.tenant.displayName ?? null);
const avatarLabel = computed(() => activeName.value ? t('access.tenant.currentAvatar', { name: activeName.value }) : t('access.tenant.selectAvatar'));
const selectableTenants = computed(() => tenants.value.filter((tenant) => tenant.selectable));
const currentLabel = computed(() => activeName.value ?? t('access.tenant.noneSelected'));

function updateViewport() { mobile.value = typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 639px)').matches; }
function close() { open.value = false; }
async function load() {
    if (!navigator.onLine) { error.value = t('access.tenant.offline'); return; }
    loading.value = true; error.value = '';
    try { tenants.value = await tenantApi.list(); }
    catch { error.value = t('access.tenant.loadFailed'); }
    finally { loading.value = false; }
}
async function toggle() {
    if (open.value) { close(); return; }
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    open.value = true;
    await nextTick();
    panel.value?.querySelector<HTMLElement>('button:not(:disabled)')?.focus();
    void load();
}
async function select(tenant: TenantSummary) {
    error.value = '';
    try {
        const context = await store.select(tenant.id);
        status.value = t('access.tenant.selected', { name: context.tenant.displayName });
        emit('changed', context.tenant.displayName);
        close();
    } catch (reason) {
        const statusCode = typeof reason === 'object' && reason !== null && 'response' in reason
            ? (reason as { response?: { status?: unknown } }).response?.status
            : undefined;

        if (statusCode === 404) {
            tenants.value = tenants.value.filter((candidate) => candidate.id !== tenant.id);
        }

        error.value = t('access.tenant.selectFailed');
    }
}
async function usePersonalSpace() {
    error.value = '';
    try { await store.end(); status.value = t('access.tenant.personalSelected'); emit('changed', null); close(); }
    catch { error.value = t('access.tenant.endFailed'); }
}
function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') { event.preventDefault(); close(); return; }
    if (!mobile.value || event.key !== 'Tab' || !panel.value) return;
    const focusable = Array.from(panel.value.querySelectorAll<HTMLElement>(focusableSelector));
    const first = focusable[0]; const last = focusable.at(-1);
    if (!first || !last) { event.preventDefault(); panel.value.focus(); return; }
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}
function handlePointerDown(event: PointerEvent) { if (open.value && !mobile.value && root.value && !root.value.contains(event.target as Node)) close(); }
watch(open, (visible) => { if (!visible) { returnFocus?.focus(); returnFocus = null; } });
onMounted(() => { updateViewport(); window.addEventListener('resize', updateViewport); document.addEventListener('pointerdown', handlePointerDown); });
onBeforeUnmount(() => { window.removeEventListener('resize', updateViewport); document.removeEventListener('pointerdown', handlePointerDown); });
</script>

<template>
    <div ref="root" class="tenant-selector">
        <span class="sr-only" aria-live="polite">{{ status }}</span>
        <button ref="opener" class="tenant-selector__trigger" type="button" :aria-label="avatarLabel" :aria-expanded="open" aria-haspopup="dialog" @click="toggle">
            <TenantAvatar :display-name="activeName" :label="avatarLabel" />
        </button>
        <div v-if="open && mobile" class="tenant-selector__backdrop" @mousedown.self="close">
            <section ref="panel" class="tenant-selector__panel tenant-selector__panel--sheet" role="dialog" aria-modal="true" :aria-label="t('access.tenant.title')" tabindex="-1" @keydown="handleKeydown">
                <button class="tenant-selector__close" type="button" :aria-label="t('access.tenant.close')" @click="close">×</button>
                <div class="tenant-selector__content"><h2>{{ t('access.tenant.title') }}</h2><p class="tenant-selector__current">{{ t('access.tenant.current') }} <strong>{{ currentLabel }}</strong></p><p v-if="loading">{{ t('access.tenant.loading') }}</p><p v-else-if="error" class="tenant-selector__error" role="status">{{ error }}</p><template v-else><button v-if="store.hasContext" class="tenant-selector__item" type="button" @click="usePersonalSpace">{{ t('access.tenant.personalSpace') }}</button><p v-if="!selectableTenants.length" class="tenant-selector__empty">{{ t('access.tenant.empty') }}</p><button v-for="tenant in selectableTenants" :key="tenant.id" class="tenant-selector__item" type="button" :disabled="store.isChanging" :aria-current="store.context?.tenant.id === tenant.id ? 'true' : undefined" @click="select(tenant)">{{ tenant.displayName }}</button></template><div class="tenant-selector__actions"><button type="button" class="tenant-selector__action" @click="emit('create')">{{ t('access.tenant.create') }}</button><button type="button" class="tenant-selector__action" @click="emit('manage')">{{ t('access.tenant.manage') }}</button></div></div>
            </section>
        </div>
        <section v-else-if="open" ref="panel" class="tenant-selector__panel" role="dialog" :aria-label="t('access.tenant.title')" tabindex="-1" @keydown="handleKeydown">
            <div class="tenant-selector__content"><h2>{{ t('access.tenant.title') }}</h2><p class="tenant-selector__current">{{ t('access.tenant.current') }} <strong>{{ currentLabel }}</strong></p><p v-if="loading">{{ t('access.tenant.loading') }}</p><p v-else-if="error" class="tenant-selector__error" role="status">{{ error }}</p><template v-else><button v-if="store.hasContext" class="tenant-selector__item" type="button" @click="usePersonalSpace">{{ t('access.tenant.personalSpace') }}</button><p v-if="!selectableTenants.length" class="tenant-selector__empty">{{ t('access.tenant.empty') }}</p><button v-for="tenant in selectableTenants" :key="tenant.id" class="tenant-selector__item" type="button" :disabled="store.isChanging" :aria-current="store.context?.tenant.id === tenant.id ? 'true' : undefined" @click="select(tenant)">{{ tenant.displayName }}</button></template><div class="tenant-selector__actions"><button type="button" class="tenant-selector__action" @click="emit('create')">{{ t('access.tenant.create') }}</button><button type="button" class="tenant-selector__action" @click="emit('manage')">{{ t('access.tenant.manage') }}</button></div></div>
        </section>
    </div>
</template>
