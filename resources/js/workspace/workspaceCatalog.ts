import type { WorkspaceContext, WorkspaceDestination, WorkspaceNavigationCategory } from './workspaceTypes';

export const workspaceNavigationCategories: readonly WorkspaceNavigationCategory[] = [
    { id: 'overview', titleKey: 'access.workspace.navigation.title', label: 'Visão geral', icon: 'overview' },
    { id: 'documents', titleKey: 'access.workspace.navigation.title', label: 'Documentos', icon: 'documents' },
    { id: 'security', titleKey: 'access.workspace.navigation.title', label: 'Segurança', icon: 'settings' },
];

export const workspaceDestinations: readonly WorkspaceDestination[] = [
    { id: 'personal.workspace-folders', scope: 'personal', category: 'documents', groupKey: 'access.workspace.navigation.title', groupLabel: 'Consulta', titleKey: 'access.workspace.title', label: 'Arquivos e anexos', icon: 'attachments', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Arquivos e anexos', icon: 'attachments' }) },
    { id: 'platform.maintenance', scope: 'personal', category: 'overview', groupKey: 'access.workspace.navigation.title', groupLabel: 'Administração', titleKey: 'access.workspace.title', label: 'Manutenções', icon: 'settings', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Central de Manutenções', icon: 'settings' }) },
    { id: 'tenant.authorization-administration', scope: 'tenant', category: 'security', groupKey: 'access.workspace.navigation.title', groupLabel: 'Administração', titleKey: 'access.authorization.title', label: 'Segurança', icon: 'settings', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.authorization.title', label: 'Segurança', icon: 'settings' }) },
];

/** Superfície pessoal permanente, aberta pelo menu do perfil e não pelo catálogo de módulos. */
export const personalSettingsDestination: WorkspaceDestination = {
    id: 'personal.settings',
    scope: 'personal',
    category: 'overview',
    titleKey: 'access.shell.userSettings',
    icon: 'rinoUser-tweek',
    instancePolicy: 'single',
    createSurface: () => ({ titleKey: 'access.shell.userSettings', icon: 'rinoUser-tweek' }),
};

export function availableWorkspaceDestinations(
    destinations: readonly WorkspaceDestination[],
    context: WorkspaceContext,
): WorkspaceDestination[] {
    return destinations.filter((destination) => destination.scope === 'personal' || context.tenantId !== null);
}
