import type { WorkspaceContext, WorkspaceDestination, WorkspaceNavigationCategory } from './workspaceTypes';

/**
 * Estrutura mínima da navegação, independente de módulos de negócio.
 *
 * Ela permite que a Área de trabalho informe honestamente que ainda não há
 * destinos liberados, sem antecipar um catálogo de produtos.
 */
export const workspaceNavigationCategories: readonly WorkspaceNavigationCategory[] = [
    { id: 'workspace', titleKey: 'access.workspace.navigation.title', icon: 'workspace' },
];

/**
 * Catálogo declarativo de destinos aprovados para a Área de trabalho.
 *
 * A primeira entrega não registra módulos de negócio. Cada módulo futuro deve
 * registrar explicitamente seu escopo e sua fábrica de superfície aqui, ou em
 * uma extensão com o mesmo contrato.
 */
export const workspaceDestinations: readonly WorkspaceDestination[] = [];

export function availableWorkspaceDestinations(
    destinations: readonly WorkspaceDestination[],
    context: WorkspaceContext,
): WorkspaceDestination[] {
    return destinations.filter((destination) => destination.scope === 'personal' || context.tenantId !== null);
}
