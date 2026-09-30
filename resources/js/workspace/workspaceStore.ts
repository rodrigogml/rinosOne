import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import type {
    WorkspaceContext,
    WorkspaceDestination,
    WorkspaceDialog,
    WorkspaceDialogClosePolicy,
    WorkspaceDialogKind,
    WorkspaceNotification,
    WorkspaceNotificationKind,
    WorkspaceSurface,
} from './workspaceTypes';

function nextId(prefix: string, sequence: number): string {
    return `${prefix}-${sequence}`;
}

/**
 * Mantém o ciclo de vida efêmero das superfícies abertas em uma única aba.
 *
 * O runtime não persiste telas ou contexto e centraliza foco, descarte,
 * diálogos e notificações para que módulos futuros não controlem camadas
 * globais por conta própria.
 */
export const useWorkspaceStore = defineStore('workspace', () => {
    const menuCollapsed = ref(false);
    const mobileTaskPanelOpen = ref(false);
    const surfaces = ref<WorkspaceSurface[]>([]);
    const activeSurfaceId = ref<string | null>(null);
    const dialogStack = ref<WorkspaceDialog[]>([]);
    const notificationQueue = ref<WorkspaceNotification[]>([]);
    let surfaceSequence = 0;
    let dialogSequence = 0;
    let notificationSequence = 0;

    const activeSurface = computed(() => surfaces.value.find((surface) => surface.id === activeSurfaceId.value) ?? null);

    function activateSurface(surfaceId: string): boolean {
        const target = surfaces.value.find((surface) => surface.id === surfaceId);

        if (!target || target.status === 'unavailable') return false;

        surfaces.value = surfaces.value.map((surface) => ({
            ...surface,
            status: surface.id === surfaceId ? 'active' : surface.status === 'active' ? 'open' : surface.status,
        }));
        activeSurfaceId.value = surfaceId;

        return true;
    }

    function openDestination(destination: WorkspaceDestination, context: WorkspaceContext): WorkspaceSurface | null {
        if (destination.scope === 'tenant' && context.tenantId === null) return null;
        if (destination.scope === 'domain' && context.domainAccess !== true) return null;
        if (destination.isAvailable?.(context) === false) return null;

        const policy = destination.instancePolicy ?? 'single';
        const existing = policy === 'single'
            ? surfaces.value.find((surface) => surface.destinationId === destination.id && surface.tenantId === context.tenantId)
            : undefined;

        if (existing) {
            activateSurface(existing.id);

            return existing;
        }

        const id = nextId('workspace-surface', ++surfaceSequence);
        const definition = destination.createSurface({ id, tenantId: context.tenantId });
        const surface: WorkspaceSurface = {
            id,
            destinationId: destination.id,
            scope: destination.scope,
            tenantId: destination.scope === 'tenant' ? context.tenantId : null,
            titleKey: definition.titleKey,
            label: definition.label,
            icon: definition.icon,
            dirty: definition.dirty ?? false,
            status: 'open',
        };

        surfaces.value = [...surfaces.value, surface];
        activateSurface(surface.id);

        return surface;
    }

    function setSurfaceDirty(surfaceId: string, dirty: boolean): void {
        surfaces.value = surfaces.value.map((surface) => surface.id === surfaceId ? { ...surface, dirty } : surface);
    }

    function selectNextActiveSurface(closedSurfaceId: string): void {
        const closedIndex = surfaces.value.findIndex((surface) => surface.id === closedSurfaceId);
        const remaining = surfaces.value.filter((surface) => surface.id !== closedSurfaceId);

        surfaces.value = remaining;

        if (!remaining.length) {
            activeSurfaceId.value = null;

            return;
        }

        const next = remaining[Math.min(closedIndex, remaining.length - 1)] ?? remaining[remaining.length - 1];
        activateSurface(next.id);
    }

    function closeSurface(surfaceId: string): boolean {
        if (!surfaces.value.some((surface) => surface.id === surfaceId)) return false;

        selectNextActiveSurface(surfaceId);

        return true;
    }

    function openDialog(input: {
        kind: WorkspaceDialogKind;
        closePolicy: WorkspaceDialogClosePolicy;
        originSurfaceId?: string | null;
        action?: WorkspaceDialog['action'];
    }): WorkspaceDialog {
        const dialog: WorkspaceDialog = {
            id: nextId('workspace-dialog', ++dialogSequence),
            kind: input.kind,
            closePolicy: input.closePolicy,
            originSurfaceId: input.originSurfaceId ?? null,
            action: input.action ?? null,
        };

        dialogStack.value = [...dialogStack.value, dialog];

        return dialog;
    }

    function requestCloseSurface(surfaceId: string): 'closed' | 'confirmation-required' | 'not-found' {
        const surface = surfaces.value.find((candidate) => candidate.id === surfaceId);

        if (!surface) return 'not-found';
        if (!surface.dirty) return closeSurface(surfaceId) ? 'closed' : 'not-found';

        openDialog({
            kind: 'confirmation',
            closePolicy: 'explicit',
            originSurfaceId: surfaceId,
            action: { type: 'discard-surface', surfaceId },
        });

        return 'confirmation-required';
    }

    function resolveDialog(dialogId: string, confirmed: boolean): boolean {
        const dialog = dialogStack.value.find((candidate) => candidate.id === dialogId);

        if (!dialog) return false;

        dialogStack.value = dialogStack.value.filter((candidate) => candidate.id !== dialogId);

        if (confirmed && dialog.action?.type === 'discard-surface') {
            closeSurface(dialog.action.surfaceId);
        }

        return true;
    }

    function enqueueNotification(input: { kind: WorkspaceNotificationKind; messageKey: string; persistent?: boolean }): WorkspaceNotification {
        const notification: WorkspaceNotification = {
            id: nextId('workspace-notification', ++notificationSequence),
            kind: input.kind,
            messageKey: input.messageKey,
            persistent: input.persistent ?? false,
        };

        notificationQueue.value = [...notificationQueue.value, notification];

        return notification;
    }

    function dismissNotification(notificationId: string): boolean {
        if (!notificationQueue.value.some((notification) => notification.id === notificationId)) return false;

        notificationQueue.value = notificationQueue.value.filter((notification) => notification.id !== notificationId);

        return true;
    }

    function clearTenantSurfaces(): void {
        const contextualSurfaceIds = new Set(surfaces.value.filter((surface) => surface.scope === 'tenant').map((surface) => surface.id));

        if (!contextualSurfaceIds.size) return;

        const activeWasContextual = activeSurfaceId.value !== null && contextualSurfaceIds.has(activeSurfaceId.value);
        surfaces.value = surfaces.value.filter((surface) => !contextualSurfaceIds.has(surface.id));
        dialogStack.value = dialogStack.value.filter((dialog) => !dialog.originSurfaceId || !contextualSurfaceIds.has(dialog.originSurfaceId));
        enqueueNotification({ kind: 'information', messageKey: 'access.workspace.notification.contextChanged' });

        if (activeWasContextual) {
            const next = surfaces.value.at(-1) ?? null;
            activeSurfaceId.value = null;
            if (next) activateSurface(next.id);
        }
    }

    function discard(): void {
        menuCollapsed.value = false;
        mobileTaskPanelOpen.value = false;
        surfaces.value = [];
        activeSurfaceId.value = null;
        dialogStack.value = [];
        notificationQueue.value = [];
        surfaceSequence = 0;
        dialogSequence = 0;
        notificationSequence = 0;
    }

    return {
        menuCollapsed,
        mobileTaskPanelOpen,
        surfaces,
        activeSurfaceId,
        activeSurface,
        dialogStack,
        notificationQueue,
        activateSurface,
        openDestination,
        setSurfaceDirty,
        requestCloseSurface,
        resolveDialog,
        openDialog,
        enqueueNotification,
        dismissNotification,
        clearTenantSurfaces,
        discard,
    };
});
