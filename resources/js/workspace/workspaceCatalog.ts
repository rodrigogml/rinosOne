import type { WorkspaceContext, WorkspaceDestination, WorkspaceNavigationCategory } from './workspaceTypes';

/**
 * Catálogo visual temporário para validar o shell com densidade próxima da
 * aplicação futura. Os destinos não possuem rota, dados, API ou regra de
 * negócio e devem ser substituídos pelos módulos aprovados.
 */
export const workspaceNavigationCategories: readonly WorkspaceNavigationCategory[] = [
    { id: 'overview', titleKey: 'access.workspace.navigation.title', label: 'Visão geral', icon: 'overview' },
    { id: 'finance', titleKey: 'access.workspace.navigation.title', label: 'Financeiro', icon: 'finance' },
    { id: 'crm', titleKey: 'access.workspace.navigation.title', label: 'CRM', icon: 'crm' },
    { id: 'catalog', titleKey: 'access.workspace.navigation.title', label: 'Produtos', icon: 'catalog' },
    { id: 'documents', titleKey: 'access.workspace.navigation.title', label: 'Documentos', icon: 'documents' },
    { id: 'accounting', titleKey: 'access.workspace.navigation.title', label: 'Contábil', icon: 'accounting' },
    { id: 'insights', titleKey: 'access.workspace.navigation.title', label: 'Relatórios', icon: 'insights' },
];

/**
 * Destinos de demonstração usados exclusivamente para validar navegação,
 * taskbar, sobreposições e composição responsiva do shell.
 */
function demoDestination(id: string, category: string, titleKey: string, groupKey: string, instancePolicy: 'single' | 'multiple' = 'single'): WorkspaceDestination {
    return {
        id: `demo.${id}`,
        scope: 'personal',
        category,
        groupKey: 'access.workspace.navigation.title',
        groupLabel: groupKey,
        titleKey: 'access.workspace.title',
        label: titleKey,
        icon: id,
        instancePolicy,
        createSurface: () => ({ titleKey: 'access.workspace.title', label: titleKey, icon: id }),
    };
}

export const workspaceDestinations: readonly WorkspaceDestination[] = [
    { id: 'platform.maintenance', scope: 'personal', category: 'overview', groupKey: 'access.workspace.navigation.title', groupLabel: 'Administração', titleKey: 'access.workspace.title', label: 'Manutenções', icon: 'settings', instancePolicy: 'single', createSurface: () => ({ titleKey: 'access.workspace.title', label: 'Central de Manutenções', icon: 'settings' }) },
    demoDestination('home', 'overview', 'Painel executivo', 'Acompanhamento'),
    demoDestination('agenda', 'overview', 'Agenda de trabalho', 'Acompanhamento', 'multiple'),
    demoDestination('cashflow', 'finance', 'Fluxo de caixa', 'Operações'),
    demoDestination('receivables', 'finance', 'Contas a receber', 'Operações'),
    demoDestination('payables', 'finance', 'Contas a pagar', 'Operações'),
    demoDestination('banking', 'finance', 'Conciliação bancária', 'Análises'),
    demoDestination('contacts', 'crm', 'Contatos', 'Relacionamento'),
    demoDestination('opportunities', 'crm', 'Oportunidades', 'Relacionamento', 'multiple'),
    demoDestination('pipeline', 'crm', 'Pipeline comercial', 'Análises'),
    demoDestination('items', 'catalog', 'Catálogo de produtos', 'Cadastro'),
    demoDestination('pricing', 'catalog', 'Tabelas de preço', 'Cadastro'),
    demoDestination('inventory', 'catalog', 'Posição de estoque', 'Operações'),
    demoDestination('invoices', 'documents', 'Notas fiscais', 'Emissão'),
    demoDestination('orders', 'documents', 'Pedidos', 'Emissão', 'multiple'),
    demoDestination('attachments', 'documents', 'Arquivos e anexos', 'Consulta'),
    demoDestination('ledger', 'accounting', 'Livro razão', 'Escrituração'),
    demoDestination('journal', 'accounting', 'Lançamentos contábeis', 'Escrituração', 'multiple'),
    demoDestination('trial-balance', 'accounting', 'Balancete', 'Consulta'),
    demoDestination('performance', 'insights', 'Indicadores', 'Gestão'),
    demoDestination('sales-report', 'insights', 'Relatório de vendas', 'Gestão'),
    demoDestination('financial-report', 'insights', 'Relatório financeiro', 'Gestão'),
];

/** Superfície pessoal permanente, aberta pelo menu do perfil e não pelo catálogo de módulos. */
export const personalSettingsDestination: WorkspaceDestination = {
    id: 'personal.settings',
    scope: 'personal',
    category: 'overview',
    titleKey: 'access.shell.userSettings',
    icon: 'settings',
    instancePolicy: 'single',
    createSurface: () => ({ titleKey: 'access.shell.userSettings', icon: 'settings' }),
};

export function availableWorkspaceDestinations(
    destinations: readonly WorkspaceDestination[],
    context: WorkspaceContext,
): WorkspaceDestination[] {
    return destinations.filter((destination) => destination.scope === 'personal' || context.tenantId !== null);
}
