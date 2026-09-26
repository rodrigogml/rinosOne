import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { tenantApi } from './tenantApi';
import type { TenantContext } from './tenantTypes';
import { useWorkspaceStore } from '../workspace/workspaceStore';

/**
 * Mantém o contexto selecionado somente na memória desta aba. Nenhuma seleção
 * é restaurada após uma recarga, nem é compartilhada com outras abas.
 */
export const useTenantContextStore = defineStore('tenant-context', () => {
    const current = ref<TenantContext | null>(null);
    const pendingTenantId = ref<number | null>(null);
    let requestVersion = 0;

    const context = computed(() => current.value);
    const hasContext = computed(() => current.value !== null);
    const isChanging = computed(() => pendingTenantId.value !== null);

    async function select(tenantId: number): Promise<TenantContext> {
        const version = ++requestVersion;
        pendingTenantId.value = tenantId;

        try {
            const nextContext = await tenantApi.startContext(tenantId);

            if (version !== requestVersion) {
                return nextContext;
            }

            if (current.value?.tenant.id !== nextContext.tenant.id) {
                useWorkspaceStore().clearTenantSurfaces();
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
                useWorkspaceStore().clearTenantSurfaces();
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
