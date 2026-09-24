import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const styles = readFileSync('resources/css/design-system/components.css', 'utf8');

describe('application shell design system styles', () => {
    it('derives shell dimensions and colors from existing semantic tokens', () => {
        expect(styles).toContain('--component-top-bar-min-height: var(--control-height-lg)');
        expect(styles).toContain('--component-avatar-size: var(--control-height-lg)');
        expect(styles).toContain('--component-top-bar-background: var(--color-surface-raised)');
        expect(styles).toContain('--component-overlay-background: color-mix');
    });

    it('provides responsive, accessible structural styles for the shell components', () => {
        expect(styles).toContain('.application-top-bar');
        expect(styles).toContain('--component-top-bar-brand-max-width: min(var(--size-brand-top-bar-max), 40vw)');
        expect(styles).toContain('.user-avatar');
        expect(styles).toContain('.user-menu__utilities');
        expect(styles).toContain('.mobile-navigation-drawer');
        expect(styles).toContain('env(safe-area-inset-top)');
        expect(styles).toContain('.application-top-bar__mobile-trigger { display: inline-flex; }');
        expect(styles).toContain('.application-top-bar__desktop-brand { display: none; }');
        expect(styles).toContain('.mobile-navigation-drawer__close');
        expect(styles).not.toContain('.presentation-popover { position: fixed');
    });
});
