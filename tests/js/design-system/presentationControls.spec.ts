import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '../../../resources/js/i18n';
import LanguageSelector from '../../../resources/js/design-system/LanguageSelector.vue';
import VisualPreferencesPopover from '../../../resources/js/design-system/VisualPreferencesPopover.vue';
import { VISUAL_PREFERENCES_STORAGE_KEY, useVisualPreferencesStore } from '../../../resources/js/preferences/visualPreferences';

function mountWithStore(component: typeof VisualPreferencesPopover | typeof LanguageSelector) {
    const pinia = createPinia();
    setActivePinia(pinia);
    const store = useVisualPreferencesStore();
    store.restore();

    return { store, wrapper: mount(component, { global: { plugins: [pinia, i18n] }, attachTo: document.body }) };
}

describe('presentation controls', () => {
    beforeEach(() => {
        window.localStorage.clear();
        i18n.global.locale.value = 'pt-BR';
        document.documentElement.lang = 'pt-BR';
        document.documentElement.dataset.theme = 'system';
        document.documentElement.dataset.fontScale = 'default';
        document.documentElement.dataset.spacingScale = 'default';
        document.documentElement.dataset.componentScale = 'default';
    });

    it('shows exactly four visual preference groups and applies each independent choice immediately', async () => {
        const { wrapper } = mountWithStore(VisualPreferencesPopover);

        await wrapper.get('button[aria-label="Preferências visuais"]').trigger('click');
        expect(wrapper.findAll('fieldset')).toHaveLength(4);
        await wrapper.get('#theme-choice-dark').trigger('click');
        await wrapper.get('#font-scale-choice-comfortable').trigger('click');
        await wrapper.get('#spacing-scale-choice-compact').trigger('click');
        await wrapper.get('#component-scale-choice-comfortable').trigger('click');

        expect(document.documentElement.dataset).toMatchObject({ theme: 'dark', fontScale: 'comfortable', spacingScale: 'compact', componentScale: 'comfortable' });
        expect(JSON.parse(window.localStorage.getItem(VISUAL_PREFERENCES_STORAGE_KEY) ?? '{}')).toMatchObject({ theme: 'dark', fontScale: 'comfortable', spacingScale: 'compact', componentScale: 'comfortable' });
        wrapper.unmount();
    });

    it('renders the approved raster icon for visual preferences', () => {
        const { wrapper } = mountWithStore(VisualPreferencesPopover);
        const icon = wrapper.get('button[aria-label="Preferências visuais"] img');

        expect(icon.attributes()).toMatchObject({ src: '/assets/icons/theme_32.png', alt: '', 'aria-hidden': 'true' });
        wrapper.unmount();
    });

    it('uses the four approved icon families while preserving accessible choice labels', async () => {
        const { wrapper } = mountWithStore(VisualPreferencesPopover);

        await wrapper.get('button[aria-label="Preferências visuais"]').trigger('click');

        expect(wrapper.get('#theme-choice-light').attributes('aria-label')).toBe('Claro');
        expect(wrapper.get('#theme-choice-light img').attributes('src')).toBe('/assets/icons/lamp-on_24.png');
        expect(wrapper.get('#theme-choice-dark img').attributes('src')).toBe('/assets/icons/lamp-off_24.png');
        expect(wrapper.get('#font-scale-choice-comfortable img').attributes('src')).toBe('/assets/icons/themeTextBig_24.png');
        expect(wrapper.get('#spacing-scale-choice-compact img').attributes('src')).toBe('/assets/icons/padding-s_24.png');
        expect(wrapper.get('#component-scale-choice-default img').attributes('src')).toBe('/assets/icons/object-size-m_24.png');
        expect(wrapper.get('#font-scale-choice-comfortable').text()).toBe('');
        expect(wrapper.get('#font-scale-choice-label + div').classes()).toContain('segmented-choice__options--icons');
        expect(wrapper.get('#theme-choice-label + div').classes()).toEqual(expect.arrayContaining(['segmented-choice__options--icons', 'segmented-choice__options--pair']));
        wrapper.unmount();
    });

    it('supports keyboard choices, external dismissal, Escape and opener focus restoration', async () => {
        const { wrapper } = mountWithStore(VisualPreferencesPopover);
        const opener = wrapper.get('button');

        opener.element.focus();
        await opener.trigger('click');
        const themeChoice = wrapper.get('#theme-choice-light');
        await themeChoice.trigger('keydown', { key: 'ArrowRight' });
        expect(document.documentElement.dataset.theme).toBe('dark');
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(document.activeElement).toBe(opener.element);

        await opener.trigger('click');
        document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('selects a language by its textual option while preserving local browser-only state', async () => {
        const fetchSpy = vi.spyOn(globalThis, 'fetch');
        const { wrapper } = mountWithStore(LanguageSelector);
        const opener = wrapper.get('button');

        await opener.trigger('click');
        expect(wrapper.get('[role="listbox"]').text()).toContain('English');
        await wrapper.get('#language-option-en').trigger('click');

        expect(i18n.global.locale.value).toBe('en');
        expect(document.documentElement.lang).toBe('en');
        expect(JSON.parse(window.localStorage.getItem(VISUAL_PREFERENCES_STORAGE_KEY) ?? '{}').locale).toBe('en');
        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
        expect(document.activeElement).toBe(opener.element);
        expect(fetchSpy).not.toHaveBeenCalled();
        fetchSpy.mockRestore();
        wrapper.unmount();
    });

    it('moves through language options by keyboard and closes the menu with Escape', async () => {
        const { wrapper } = mountWithStore(LanguageSelector);
        const opener = wrapper.get('button');

        await opener.trigger('click');
        const menu = wrapper.get('[role="listbox"]');
        await menu.trigger('keydown', { key: 'ArrowDown' });
        expect(document.activeElement?.id).toBe('language-option-en');
        await menu.trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
        expect(document.activeElement).toBe(opener.element);
        wrapper.unmount();
    });
});
