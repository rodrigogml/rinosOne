import type { WorkspaceContext, WorkspaceDestination, WorkspaceNavigationCategory } from './workspaceTypes';

export const workspaceNavigationCategories: readonly WorkspaceNavigationCategory[] = [
    { id: 'personal-library', scope: 'personal', scopeLabel: 'Pessoal', titleKey: 'access.workspace.navigation.title', label: 'Biblioteca', icon: 'documents' },
    { id: 'tenant-workspace', scope: 'tenant', scopeLabel: 'Organização', titleKey: 'access.workspace.navigation.title', label: 'Workspace', icon: 'drive' },
    { id: 'tenant-security', scope: 'tenant', scopeLabel: 'Organização', titleKey: 'access.workspace.navigation.title', label: 'Segurança', icon: 'settings' },
    { id: 'domain-governance', scope: 'domain', scopeLabel: 'Domínio', titleKey: 'access.workspace.navigation.title', label: 'Administração', icon: 'settings' },
];

export const workspaceDestinations: readonly WorkspaceDestination[] = [
    { id: 'personal.drive', scope: 'personal', category: 'personal-library', groupKey: 'access.workspace.navigation.title', groupLabel: 'Rinos Drive', titleKey: 'access.workspace.title', label: 'Rinos Drive Pessoal', navigationLabel: 'Arquivos', icon: 'drive', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Rinos Drive Pessoal', icon: 'drive' }) },
    { id: 'tenant.drive', scope: 'tenant', category: 'tenant-workspace', groupKey: 'access.workspace.navigation.title', groupLabel: 'Rinos Drive', titleKey: 'access.workspace.title', label: 'Rinos Drive Work', navigationLabel: 'Arquivos', icon: 'drive', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Rinos Drive Work', icon: 'drive' }) },
    { id: 'tenant.authorization-administration', scope: 'tenant', category: 'tenant-security', groupLabel: 'Administração', titleKey: 'access.workspace.title', label: 'Usuários e acessos', icon: 'settings', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Usuários e acessos', icon: 'settings' }) },
    { id: 'platform.maintenance', scope: 'domain', category: 'domain-governance', groupLabel: 'Administração', titleKey: 'access.workspace.title', label: 'Manutenções', icon: 'maintenance', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Central de Manutenções', icon: 'maintenance' }) },
];

/** Superfície pessoal permanente, aberta pelo menu do perfil e não pelo catálogo de módulos. */
export const personalSettingsDestination: WorkspaceDestination = {
    id: 'personal.settings',
    scope: 'personal',
    category: 'personal-library',
    titleKey: 'access.shell.userSettings',
    icon: 'rinoUser-tweek',
    instancePolicy: 'single',
    createSurface: () => ({ titleKey: 'access.shell.userSettings', icon: 'rinoUser-tweek' }),
};

export function availableWorkspaceDestinations(
    destinations: readonly WorkspaceDestination[],
    context: WorkspaceContext,
): WorkspaceDestination[] {
    return destinations.filter((destination) => destination.scope === 'personal'
        || (destination.scope === 'tenant' && context.tenantId !== null)
        || (destination.scope === 'domain' && context.domainAccess === true));
}

export function availableWorkspaceNavigationCategories(
    categories: readonly WorkspaceNavigationCategory[],
    context: WorkspaceContext,
): WorkspaceNavigationCategory[] {
    return categories.filter((category) => category.scope === 'personal'
        || (category.scope === 'tenant' && context.tenantId !== null)
        || (category.scope === 'domain' && context.domainAccess === true));
}
