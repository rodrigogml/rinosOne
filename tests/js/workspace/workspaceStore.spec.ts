import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { availableWorkspaceDestinations, availableWorkspaceNavigationCategories, workspaceDestinations, workspaceNavigationCategories } from '../../../resources/js/workspace/workspaceCatalog';
import { useWorkspaceStore } from '../../../resources/js/workspace/workspaceStore';
import type { WorkspaceDestination } from '../../../resources/js/workspace/workspaceTypes';

function destination(overrides: Partial<WorkspaceDestination> = {}): WorkspaceDestination {
    return {
        id: 'personal.notes',
        scope: 'personal',
        category: 'personal',
        titleKey: 'workspace.destination.notes',
        icon: 'note',
        createSurface: () => ({ titleKey: 'workspace.surface.notes', icon: 'note' }),
        ...overrides,
    };
}

describe('workspace runtime store', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('filters tenant destinations without a tenant context', () => {
        const personal = destination();
        const tenant = destination({ id: 'tenant.orders', scope: 'tenant', category: 'tenant' });
        const domain = destination({ id: 'domain.audit', scope: 'domain', category: 'domain' });

        expect(availableWorkspaceDestinations([personal, tenant, domain], { tenantId: null })).toEqual([personal]);
        expect(availableWorkspaceDestinations([personal, tenant, domain], { tenantId: 1 })).toEqual([personal, tenant]);
        expect(availableWorkspaceDestinations([personal, tenant, domain], { tenantId: 1, domainAccess: true })).toEqual([personal, tenant, domain]);
    });

    it('keeps navigation contexts aligned with destination visibility', () => {
        const categories = [
            { id: 'personal', scope: 'personal' as const, scopeLabel: 'Pessoal', titleKey: 'workspace', icon: 'overview' },
            { id: 'tenant', scope: 'tenant' as const, scopeLabel: 'Organização', titleKey: 'workspace', icon: 'contacts' },
            { id: 'domain', scope: 'domain' as const, scopeLabel: 'Domínio', titleKey: 'workspace', icon: 'settings' },
        ];

        expect(availableWorkspaceNavigationCategories(categories, { tenantId: null }).map((category) => category.id)).toEqual(['personal']);
        expect(availableWorkspaceNavigationCategories(categories, { tenantId: 4 }).map((category) => category.id)).toEqual(['personal', 'tenant']);
        expect(availableWorkspaceNavigationCategories(categories, { tenantId: 4, domainAccess: true }).map((category) => category.id)).toEqual(['personal', 'tenant', 'domain']);
    });

    it('publishes only workspace destinations backed by an implemented surface', () => {
        expect(workspaceNavigationCategories.map((category) => category.id)).toEqual([
            'personal-library',
            'tenant-security',
            'domain-governance',
        ]);
        expect(workspaceDestinations.map((candidate) => candidate.id)).toEqual([
            'personal.workspace-folders',
            'tenant.authorization-administration',
            'platform.maintenance',
        ]);
    });

    it('focuses the existing single instance and creates distinguishable multiple instances', () => {
        const store = useWorkspaceStore();
        const single = destination();
        const multiple = destination({ id: 'personal.search', instancePolicy: 'multiple' });

        const firstSingle = store.openDestination(single, { tenantId: null });
        const secondSingle = store.openDestination(single, { tenantId: null });
        const firstMultiple = store.openDestination(multiple, { tenantId: null });
        const secondMultiple = store.openDestination(multiple, { tenantId: null });

        expect(firstSingle?.id).toBe(secondSingle?.id);
        expect(firstMultiple?.id).not.toBe(secondMultiple?.id);
        expect(store.surfaces).toHaveLength(3);
        expect(store.activeSurfaceId).toBe(secondMultiple?.id);
    });

    it('rejects tenant destinations without context and removes only contextual surfaces on context change', () => {
        const store = useWorkspaceStore();
        const personal = store.openDestination(destination(), { tenantId: null });
        const tenant = destination({ id: 'tenant.orders', scope: 'tenant', category: 'tenant' });

        expect(store.openDestination(tenant, { tenantId: null })).toBeNull();

        const contextual = store.openDestination(tenant, { tenantId: 1 });
        store.clearTenantSurfaces();

        expect(store.surfaces.map((surface) => surface.id)).toEqual([personal?.id]);
        expect(store.activeSurfaceId).toBe(personal?.id);
        expect(store.surfaces.find((surface) => surface.id === contextual?.id)).toBeUndefined();
    });

    it('preserves a dirty surface when discard is cancelled and closes it after confirmation', () => {
        const store = useWorkspaceStore();
        const surface = store.openDestination(destination(), { tenantId: null });

        store.setSurfaceDirty(surface!.id, true);

        expect(store.requestCloseSurface(surface!.id)).toBe('confirmation-required');
        const dialog = store.dialogStack[0];
        expect(dialog?.closePolicy).toBe('explicit');

        store.resolveDialog(dialog!.id, false);
        expect(store.surfaces).toHaveLength(1);

        const confirmation = store.requestCloseSurface(surface!.id);
        const discardDialog = store.dialogStack[0];
        store.resolveDialog(discardDialog!.id, true);

        expect(confirmation).toBe('confirmation-required');
        expect(store.surfaces).toHaveLength(0);
        expect(store.activeSurfaceId).toBeNull();
    });

    it('keeps notifications ordered and discards all ephemeral state on session end', () => {
        const store = useWorkspaceStore();
        const surface = store.openDestination(destination(), { tenantId: null });
        const first = store.enqueueNotification({ kind: 'information', messageKey: 'workspace.notice.first' });
        const second = store.enqueueNotification({ kind: 'success', messageKey: 'workspace.notice.second' });

        store.dismissNotification(first.id);
        expect(store.notificationQueue.map((notification) => notification.id)).toEqual([second.id]);

        store.discard();

        expect(store.surfaces).toEqual([]);
        expect(store.notificationQueue).toEqual([]);
        expect(surface).not.toBeNull();
    });
});
