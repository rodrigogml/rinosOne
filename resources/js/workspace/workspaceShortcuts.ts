export type WorkspaceShortcutAction = 'previous-surface' | 'next-surface' | 'close-active-surface';

function targetsEditableControl(target: EventTarget | null): boolean {
    return target instanceof Element && target.closest('input, textarea, select, [contenteditable="true"]') !== null;
}

/**
 * Reconhece somente atalhos do workspace que não conflitam com edição de texto
 * ou combinações que pertencem ao navegador e a tecnologias assistivas.
 */
export function workspaceShortcutAction(event: KeyboardEvent): WorkspaceShortcutAction | null {
    if (event.defaultPrevented || event.ctrlKey || event.metaKey || !event.altKey || !event.shiftKey || targetsEditableControl(event.target)) {
        return null;
    }

    if (event.key === 'ArrowLeft') return 'previous-surface';
    if (event.key === 'ArrowRight') return 'next-surface';
    if (event.key.toLowerCase() === 'w') return 'close-active-surface';

    return null;
}
