import axios from 'axios';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { TenantResponseShapeError } from '../../../resources/js/tenant/tenantApi';
import { useTenantContextStore } from '../../../resources/js/tenant/tenantContextStore';
import { useWorkspaceStore } from '../../../resources/js/workspace/workspaceStore';
import type { WorkspaceDestination } from '../../../resources/js/workspace/workspaceTypes';

vi.mock('axios', () => ({ default: { delete: vi.fn(), get: vi.fn(), post: vi.fn() } }));

const http = axios as unknown as {
    delete: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
};

const tenantContext = {
    tenant: { id: 1, displayName: 'Oficina Rubi' },
    membership: { id: 1 },
    capabilities: { canManageAvailability: true },
    availableModules: [],
};

function destination(scope: WorkspaceDestination['scope']): WorkspaceDestination {
    return {
        id: `${scope}.destination`,
        scope,
        category: scope,
        titleKey: `workspace.destination.${scope}`,
        icon: scope,
        createSurface: () => ({ titleKey: `workspace.surface.${scope}`, icon: scope }),
    };
}

describe('tenant context store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.resetAllMocks();
    });

    it('selects and ends a context only in the current store instance', async () => {
        const store = useTenantContextStore();
        http.post.mockResolvedValueOnce({ data: { context: tenantContext } });

        await store.select(tenantContext.tenant.id);

        expect(store.context).toEqual(tenantContext);
        expect(http.post).toHaveBeenCalledWith('/api/v1/tenants/1/contexts');

        http.delete.mockResolvedValueOnce({ status: 204 });
        await store.end();

        expect(store.context).toBeNull();
        expect(http.delete).toHaveBeenCalledWith('/api/v1/tenants/1/contexts');
    });

    it('does not alter the current context when the remote response has an invalid shape', async () => {
        const store = useTenantContextStore();
        http.post.mockResolvedValueOnce({ data: { context: tenantContext } });
        await store.select(tenantContext.tenant.id);
        http.post.mockResolvedValueOnce({ data: { context: { tenant: {} } } });

        await expect(store.select(2)).rejects.toBeInstanceOf(TenantResponseShapeError);
        expect(store.context).toEqual(tenantContext);
        expect(store.pendingTenantId).toBeNull();
    });

    it('rejects a context that omits its authorization capabilities', async () => {
        const store = useTenantContextStore();
        http.post.mockResolvedValueOnce({ data: { context: { tenant: tenantContext.tenant, membership: tenantContext.membership, availableModules: [] } } });

        await expect(store.select(tenantContext.tenant.id)).rejects.toBeInstanceOf(TenantResponseShapeError);
        expect(store.context).toBeNull();
    });

    it('clears only contextual workspace surfaces after a successful context change', async () => {
        const store = useTenantContextStore();
        const workspace = useWorkspaceStore();
        const nextContext = { ...tenantContext, tenant: { id: 2, displayName: 'Nova Oficina' } };
        http.post.mockResolvedValueOnce({ data: { context: tenantContext } }).mockResolvedValueOnce({ data: { context: nextContext } });

        await store.select(tenantContext.tenant.id);
        const personal = workspace.openDestination(destination('personal'), { tenantId: null });
        workspace.openDestination(destination('tenant'), { tenantId: tenantContext.tenant.id });

        await store.select(nextContext.tenant.id);

        expect(workspace.surfaces.map((surface) => surface.id)).toEqual([personal?.id]);
        expect(workspace.activeSurfaceId).toBe(personal?.id);
        expect(store.context).toEqual(nextContext);
    });

    it('discards pending and selected contextual data without storage or restoration', async () => {
        const firstTab = useTenantContextStore();
        http.post.mockResolvedValueOnce({ data: { context: tenantContext } });
        await firstTab.select(tenantContext.tenant.id);
        firstTab.discard();

        expect(firstTab.context).toBeNull();
        expect(firstTab.pendingTenantId).toBeNull();
        expect(window.localStorage.getItem('tenant-context')).toBeNull();

        setActivePinia(createPinia());
        const reloadedTab = useTenantContextStore();
        expect(reloadedTab.context).toBeNull();
        expect(reloadedTab.hasContext).toBe(false);
    });

    it('does not reapply an older selection after the context is discarded', async () => {
        const store = useTenantContextStore();
        let completeSelection: ((value: unknown) => void) | undefined;
        http.post.mockReturnValueOnce(new Promise((resolve) => { completeSelection = resolve; }));

        const selection = store.select(tenantContext.tenant.id);
        store.discard();
        completeSelection?.({ data: { context: tenantContext } });
        await selection;

        expect(store.context).toBeNull();
        expect(store.pendingTenantId).toBeNull();
    });
});
