import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { tenantApi } from './tenantApi';
import type { TenantContext } from './tenantTypes';

/**
 * Mantém o contexto selecionado somente na memória desta aba. Nenhuma seleção
 * é restaurada após uma recarga, nem é compartilhada com outras abas.
 */
export const useTenantContextStore = defineStore('tenant-context', () => {
    const current = ref<TenantContext | null>(null);
    const pendingTenantId = ref<string | null>(null);
    let requestVersion = 0;

    const context = computed(() => current.value);
    const hasContext = computed(() => current.value !== null);
    const isChanging = computed(() => pendingTenantId.value !== null);

    async function select(tenantId: string): Promise<TenantContext> {
        const version = ++requestVersion;
        pendingTenantId.value = tenantId;

        try {
            const nextContext = await tenantApi.startContext(tenantId);

            if (version !== requestVersion) {
                return nextContext;
            }

            current.value = nextContext;

            return nextContext;
        } finally {
            if (version === requestVersion) {
                pendingTenantId.value = null;
            }
        }
    }

    async function end(): Promise<void> {
        const activeContext = current.value;

        if (!activeContext) return;

        const version = ++requestVersion;
        pendingTenantId.value = activeContext.tenant.id;

        try {
            await tenantApi.endContext(activeContext.tenant.id);

            if (version === requestVersion) {
                current.value = null;
            }
        } finally {
            if (version === requestVersion) {
                pendingTenantId.value = null;
            }
        }
    }

    function discard(): void {
        requestVersion += 1;
        current.value = null;
        pendingTenantId.value = null;
    }

    return { context, hasContext, pendingTenantId, isChanging, select, end, discard };
});
