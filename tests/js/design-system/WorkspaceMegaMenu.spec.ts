import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WorkspaceMegaMenu from '../../../resources/js/design-system/WorkspaceMegaMenu.vue';
import type { WorkspaceDestination, WorkspaceNavigationCategory } from '../../../resources/js/workspace/workspaceTypes';
import { i18n } from '../../../resources/js/i18n';

const category: WorkspaceNavigationCategory = { id: 'workspace', titleKey: 'access.workspace.navigation.title', icon: 'workspace' };
const destination: WorkspaceDestination = {
    id: 'personal.sample',
    scope: 'personal',
    category: 'workspace',
    titleKey: 'access.workspace.navigation.title',
    icon: 'sample',
    createSurface: () => ({ titleKey: 'access.workspace.title', icon: 'sample' }),
};

describe('WorkspaceMegaMenu', () => {
    it('states honestly when the selected category has no destination', () => {
        const wrapper = mount(WorkspaceMegaMenu, {
            props: { category, destinations: [], emptyLabel: 'Nenhuma área disponível.' },
            global: { plugins: [i18n] },
        });

        expect(wrapper.get('#workspace-mega-menu').attributes('aria-label')).toBe('Navegação');
        expect(wrapper.text()).toContain('Nenhuma área disponível.');
    });

    it('emits the selected destination without owning runtime state', async () => {
        const wrapper = mount(WorkspaceMegaMenu, {
            props: { category, destinations: [destination], emptyLabel: 'Nenhuma área disponível.' },
            global: { plugins: [i18n] },
        });

        await wrapper.get('.workspace-mega-menu__destination').trigger('click');

        expect(wrapper.emitted('openDestination')).toEqual([[destination]]);
    });
});
