import type { WorkspaceContext, WorkspaceDestination, WorkspaceNavigationCategory } from './workspaceTypes';

export const workspaceNavigationCategories: readonly WorkspaceNavigationCategory[] = [
    { id: 'personal-library', scope: 'personal', scopeLabel: 'Pessoal', titleKey: 'access.workspace.navigation.title', label: 'Biblioteca', icon: 'documents' },
    { id: 'tenant-workspace', scope: 'tenant', scopeLabel: 'Organização', titleKey: 'access.workspace.navigation.title', label: 'Workspace', icon: 'drive' },
    { id: 'tenant-security', scope: 'tenant', scopeLabel: 'Organização', titleKey: 'access.workspace.navigation.title', label: 'Segurança', icon: 'settings' },
    { id: 'domain-governance', scope: 'domain', scopeLabel: 'Domínio', titleKey: 'access.workspace.navigation.title', label: 'Administração', icon: 'settings' },
];

export const workspaceDestinations: readonly WorkspaceDestination[] = [
    { id: 'personal.authorization-administration', scope: 'personal', category: 'personal-library', groupLabel: 'Administração', titleKey: 'access.workspace.title', label: 'Usuários e acessos', icon: 'settings', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Usuários e acessos', icon: 'settings' }) },
    { id: 'personal.permissions-access', scope: 'personal', category: 'personal-library', groupLabel: 'Administração', titleKey: 'access.permissionManagement.title', label: 'Usuários, permissões e acessos', icon: 'secPermissions', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.permissionManagement.title', label: 'Usuários, permissões e acessos', icon: 'secPermissions' }) },
    { id: 'tenant.people', scope: 'tenant', category: 'tenant-workspace', groupLabel: 'Cadastros', titleKey: 'access.people.title', label: 'Pessoas', navigationLabel: 'Pessoas', icon: 'personCompany', instancePolicy: 'single', isAvailable: (context) => context.canReadPeople === true, createSurface: () => ({ titleKey: 'access.people.title', subtitleKey: 'access.people.subtitle', label: 'Pessoas', icon: 'personCompany' }) },
    { id: 'tenant.authorization-administration', scope: 'tenant', category: 'tenant-security', groupLabel: 'Administração', titleKey: 'access.workspace.title', label: 'Usuários e acessos', icon: 'settings', instancePolicy: 'single', isAvailable: (context) => context.canReadAuthorization === true, createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Usuários e acessos', icon: 'settings' }) },
    { id: 'tenant.permissions-access', scope: 'tenant', category: 'tenant-security', groupLabel: 'Administração', titleKey: 'access.permissionManagement.title', label: 'Usuários, permissões e acessos', icon: 'secPermissions', instancePolicy: 'single', isAvailable: (context) => context.canReadAuthorization === true, createSurface: () => ({ titleKey: 'access.permissionManagement.title', label: 'Usuários, permissões e acessos', icon: 'secPermissions' }) },
    { id: 'platform.maintenance', scope: 'domain', category: 'domain-governance', groupLabel: 'Administração', titleKey: 'access.workspace.title', label: 'Manutenções', icon: 'maintenance', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Central de Manutenções', icon: 'maintenance' }) },
    { id: 'platform.authorization-administration', scope: 'domain', category: 'domain-governance', groupLabel: 'Administração', titleKey: 'access.workspace.title', label: 'Usuários e acessos', icon: 'settings', instancePolicy: 'single', isAvailable: (context) => context.canReadAuthorization === true, createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Usuários e acessos', icon: 'settings' }) },
    { id: 'platform.permissions-access', scope: 'domain', category: 'domain-governance', groupLabel: 'Administração', titleKey: 'access.permissionManagement.title', label: 'Usuários, permissões e acessos', icon: 'secPermissions', instancePolicy: 'single', isAvailable: (context) => context.canReadAuthorization === true, createSurface: () => ({ titleKey: 'access.permissionManagement.title', label: 'Usuários, permissões e acessos', icon: 'secPermissions' }) },
];

/** Ferramenta global: um único navegador reúne os workspaces disponíveis ao usuário. */
export const globalDriveDestination: WorkspaceDestination = {
    id: 'global.drive', scope: 'global', category: 'global-tools', titleKey: 'access.workspace.title', label: 'Rinos Drive', icon: 'drive', instancePolicy: 'single',
    createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Rinos Drive', icon: 'drive' }),
};

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
    return destinations.filter((destination) => (destination.isAvailable?.(context) ?? true)
        && (destination.scope === 'personal'
        || (destination.scope === 'tenant' && context.tenantId !== null)
        || (destination.scope === 'domain' && context.domainAccess === true)
        || destination.scope === 'global'));
}

export function availableWorkspaceNavigationCategories(
    categories: readonly WorkspaceNavigationCategory[],
    context: WorkspaceContext,
): WorkspaceNavigationCategory[] {
    return categories.filter((category) => category.scope === 'personal'
        || (category.scope === 'tenant' && context.tenantId !== null)
        || (category.scope === 'domain' && context.domainAccess === true));
}
