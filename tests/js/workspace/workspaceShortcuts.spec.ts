import { describe, expect, it } from 'vitest';
import { workspaceShortcutAction } from '../../../resources/js/workspace/workspaceShortcuts';

function shortcutEvent(key: string, target: EventTarget | null = document.body): KeyboardEvent {
    return new KeyboardEvent('keydown', { key, altKey: true, shiftKey: true, bubbles: true });
}

describe('workspace shortcuts', () => {
    it('maps only the documented navigation and close combinations', () => {
        expect(workspaceShortcutAction(shortcutEvent('ArrowLeft'))).toBe('previous-surface');
        expect(workspaceShortcutAction(shortcutEvent('ArrowRight'))).toBe('next-surface');
        expect(workspaceShortcutAction(shortcutEvent('w'))).toBe('close-active-surface');
        expect(workspaceShortcutAction(new KeyboardEvent('keydown', { key: 'ArrowRight', altKey: true }))).toBeNull();
    });

    it('does not intercept editable controls or browser/assistive modifier combinations', () => {
        const input = document.createElement('input');
        document.body.append(input);
        const editableEvent = new KeyboardEvent('keydown', { key: 'ArrowRight', altKey: true, shiftKey: true, bubbles: true });
        input.dispatchEvent(editableEvent);

        expect(workspaceShortcutAction(editableEvent)).toBeNull();
        expect(workspaceShortcutAction(new KeyboardEvent('keydown', { key: 'ArrowRight', altKey: true, shiftKey: true, ctrlKey: true }))).toBeNull();
        input.remove();
    });
});
