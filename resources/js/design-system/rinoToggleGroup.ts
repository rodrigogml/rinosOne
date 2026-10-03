import { markRaw, nextTick } from 'vue';

export interface RinoToggleGroupMember {
    isSelected: () => boolean;
    isAvailable: () => boolean;
    setSelected: (selected: boolean) => void;
}

/** Grupo exclusivo por instância, com seleção obrigatória por padrão. */
export interface RinoToggleGroup {
    readonly required: boolean;
    members: Set<RinoToggleGroupMember>;
}

export function createRinoToggleGroup(options: { required?: boolean } = {}): RinoToggleGroup {
    return markRaw({ required: options.required ?? true, members: new Set<RinoToggleGroupMember>() });
}

/** Aguarda o v-model e a montagem dos irmãos antes de reparar um grupo vazio. */
const pendingRepairs = new WeakSet<RinoToggleGroup>();

export function ensureRinoToggleGroupSelection(group: RinoToggleGroup | null | undefined): void {
    if (!group?.required || pendingRepairs.has(group)) return;
    pendingRepairs.add(group);
    void nextTick(() => {
        pendingRepairs.delete(group);
        if ([...group.members].some(member => member.isSelected())) return;
        [...group.members].find(member => member.isAvailable())?.setSelected(true);
    });
}
