export type RinoButtonVariant = 'primary' | 'secondary' | 'destructive';
export interface RinoButtonDefinition { label: string | null; accessibleLabel: string; icon: string; variant: RinoButtonVariant; toggle: boolean; scope: string }

/** Presets únicos: o contexto pode sobrepor propriedades explicitamente. */
export const rinoButtonCommands = {
    insert: { label: "rinoButtons.commands.insert", accessibleLabel: "rinoButtons.commands.insert", icon: 'dataInsert', variant: 'secondary', toggle: false, scope: "Abre a inclusão de um novo registro ou objeto, sem confirmar a gravação." },
    duplicate: { label: "rinoButtons.commands.duplicate", accessibleLabel: "rinoButtons.commands.duplicate", icon: 'dataDuplicate', variant: 'secondary', toggle: false, scope: "Inicia um novo registro a partir de uma única seleção existente." },
    alter: { label: "rinoButtons.commands.alter", accessibleLabel: "rinoButtons.commands.alter", icon: 'dataEdit', variant: 'secondary', toggle: false, scope: "Abre a alteração de um único registro existente." },
    view: { label: "rinoButtons.commands.view", accessibleLabel: "rinoButtons.commands.view", icon: 'dataView', variant: 'secondary', toggle: false, scope: "Abre a consulta de um único registro sem iniciar edição." },
    delete: { label: "rinoButtons.commands.delete", accessibleLabel: "rinoButtons.commands.delete", icon: 'dataDelete', variant: 'destructive', toggle: false, scope: "Confirma e executa a exclusão de um registro ou objeto." },
    save: { label: "rinoButtons.commands.save", accessibleLabel: "rinoButtons.commands.save", icon: 'floppyDisk', variant: 'primary', toggle: false, scope: "Persiste a criação ou alteração preenchida em um formulário." },
    cancel: { label: "rinoButtons.commands.cancel", accessibleLabel: "rinoButtons.commands.cancel", icon: 'btCancel', variant: 'secondary', toggle: false, scope: "Abandona o fluxo local sem confirmar ou persistir a alteração atual." },
    confirm: { label: "rinoButtons.commands.confirm", accessibleLabel: "rinoButtons.commands.confirm", icon: 'btConfirm', variant: 'primary', toggle: false, scope: "Confirma uma consequência já descrita no diálogo; exclusões mantêm o rótulo Excluir." },
    search: { label: null, accessibleLabel: "rinoButtons.commands.search", icon: 'search', variant: 'secondary', toggle: false, scope: "Executa a busca preenchida no campo adjacente." },
    filters: { label: null, accessibleLabel: "rinoButtons.commands.filters", icon: 'funnel', variant: 'secondary', toggle: false, scope: "Abre ou alterna os filtros da listagem." },
    keepSelection: { label: null, accessibleLabel: "rinoButtons.commands.keepSelection", icon: 'tableLockSelection', variant: 'secondary', toggle: true, scope: "Alterna a preservação dos itens selecionados entre novas buscas." },
    showSelected: { label: null, accessibleLabel: "rinoButtons.commands.showSelected", icon: 'tableShowSelected', variant: 'secondary', toggle: true, scope: "Alterna a lista para mostrar somente os itens selecionados." },
    includeHidden: { label: null, accessibleLabel: "rinoButtons.commands.includeHidden", icon: 'tableShowHiddenSelected', variant: 'secondary', toggle: true, scope: "Alterna a inclusão de itens selecionados fora dos critérios atuais." },
    clearSelection: { label: null, accessibleLabel: "rinoButtons.commands.clearSelection", icon: 'tableCleanSelection', variant: 'secondary', toggle: false, scope: "Remove a seleção mantida na listagem." },
    columns: { label: null, accessibleLabel: "rinoButtons.commands.columns", icon: 'tableColumns', variant: 'secondary', toggle: false, scope: "Abre a configuração de colunas da listagem." },
} as const satisfies Record<string, RinoButtonDefinition>;
export type RinoButtonCommand = keyof typeof rinoButtonCommands;
