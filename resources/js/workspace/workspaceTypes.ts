export type WorkspaceDestinationScope = 'personal' | 'tenant';
export type WorkspaceInstancePolicy = 'single' | 'multiple';
export type WorkspaceSurfaceStatus = 'open' | 'active' | 'closing' | 'unavailable';
export type WorkspaceDialogKind = 'information' | 'warning' | 'error' | 'confirmation';
export type WorkspaceDialogClosePolicy = 'dismissible' | 'explicit';
export type WorkspaceNotificationKind = 'information' | 'success' | 'warning' | 'error';

export interface WorkspaceContext {
    tenantId: string | null;
}

export interface WorkspaceSurfaceDefinition {
    titleKey: string;
    label?: string;
    icon: string;
    dirty?: boolean;
}

/**
 * Identifica uma categoria estável da navegação da Área de trabalho.
 *
 * Categorias descrevem a estrutura da interface, enquanto os destinos
 * continuam sendo declarados pelos módulos somente quando forem aprovados.
 */
export interface WorkspaceNavigationCategory {
    id: string;
    titleKey: string;
    label?: string;
    icon: string;
}

export interface WorkspaceDestination {
    id: string;
    scope: WorkspaceDestinationScope;
    category: string;
    groupKey?: string;
    groupLabel?: string;
    titleKey: string;
    label?: string;
    icon: string;
    instancePolicy?: WorkspaceInstancePolicy;
    createSurface: (input: { id: string; tenantId: string | null }) => WorkspaceSurfaceDefinition;
}

export interface WorkspaceSurface {
    id: string;
    destinationId: string;
    scope: WorkspaceDestinationScope;
    tenantId: string | null;
    titleKey: string;
    label?: string;
    icon: string;
    dirty: boolean;
    status: WorkspaceSurfaceStatus;
}

export interface WorkspaceDialogAction {
    type: 'discard-surface';
    surfaceId: string;
}

export interface WorkspaceDialog {
    id: string;
    kind: WorkspaceDialogKind;
    closePolicy: WorkspaceDialogClosePolicy;
    originSurfaceId: string | null;
    action: WorkspaceDialogAction | null;
}

/** Diálogo efêmero, pertencente exclusivamente a uma instância de janela. */
export interface WorkspaceWindowDialog {
    id: string;
    title: string;
    description: string;
}

export interface WorkspaceNotification {
    id: string;
    kind: WorkspaceNotificationKind;
    messageKey: string;
    persistent: boolean;
}

export interface WorkspaceState {
    menuCollapsed: boolean;
    surfaces: WorkspaceSurface[];
    activeSurfaceId: string | null;
    dialogStack: WorkspaceDialog[];
    notificationQueue: WorkspaceNotification[];
}
