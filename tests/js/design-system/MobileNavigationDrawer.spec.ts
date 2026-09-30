import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import MobileNavigationDrawer from '../../../resources/js/design-system/MobileNavigationDrawer.vue';
import { i18n } from '../../../resources/js/i18n';
import type { WorkspaceDestination, WorkspaceNavigationCategory } from '../../../resources/js/workspace/workspaceTypes';

function mountDrawer(modelValue = true) {
    return mount(MobileNavigationDrawer, { attachTo: document.body, props: { modelValue, brandLabel: 'Rinos One', title: 'Navegação', closeLabel: 'Fechar navegação', emptyLabel: 'Nenhuma área adicional está disponível.' }, global: { plugins: [i18n] } });
}

describe('mobile navigation drawer', () => {
    it('shows an honest empty navigation state without product links', () => {
        const wrapper = mountDrawer();

        expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Navegação');
        expect(wrapper.get('.mobile-navigation-drawer__brand').attributes('src')).toBe('/assets/brand/logo-768.png?v=20260930');
        expect(wrapper.find('.mobile-navigation-drawer__title').exists()).toBe(false);
        expect(wrapper.text()).toContain('Nenhuma área adicional está disponível.');
        expect(wrapper.findAll('a')).toHaveLength(0);
        wrapper.unmount();
    });

    it('closes through its button, Escape and backdrop while restoring opener focus', async () => {
        const opener = document.createElement('button');
        document.body.appendChild(opener);
        opener.focus();
        const wrapper = mountDrawer();
        await wrapper.vm.$nextTick();
        const drawer = wrapper.get('[role="dialog"]');

        expect(document.activeElement).toBe(wrapper.get('button[aria-label="Fechar navegação"]').element);
        await drawer.trigger('keydown', { key: 'Escape' });
        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
        await wrapper.setProps({ modelValue: false });
        expect(document.activeElement).toBe(opener);

        await wrapper.setProps({ modelValue: true });
        await wrapper.find('.application-overlay').trigger('mousedown');
        expect(wrapper.emitted('update:modelValue')).toContainEqual([false]);
        wrapper.unmount();
        opener.remove();
    });

    it('filters destinations under its selected category and emits only the selected destination', async () => {
        const categories: WorkspaceNavigationCategory[] = [{ id: 'workspace', scope: 'personal', scopeLabel: 'Pessoal', titleKey: 'access.workspace.navigation.title', icon: 'workspace' }];
        const destination: WorkspaceDestination = { id: 'personal.sample', scope: 'personal', category: 'workspace', titleKey: 'access.workspace.title', icon: 'sample', createSurface: () => ({ titleKey: 'access.workspace.title', icon: 'sample' }) };
        const wrapper = mount(MobileNavigationDrawer, {
            props: { modelValue: true, brandLabel: 'Rinos One', title: 'Navegação', closeLabel: 'Fechar', emptyLabel: 'Vazia', categories, destinations: [destination] },
            global: { plugins: [i18n] },
        });

        await wrapper.get('.mobile-navigation-drawer__category-trigger').trigger('click');
        expect(wrapper.get('.mobile-navigation-drawer__destination .workspace-surface-icon').attributes('viewBox')).toBe('0 0 48 48');
        await wrapper.get('.mobile-navigation-drawer__destination').trigger('click');
        expect(wrapper.emitted('openDestination')).toEqual([[destination]]);
    });

    it('groups mobile navigation by context and collapses a complete context without closing the drawer', async () => {
        const categories: WorkspaceNavigationCategory[] = [
            { id: 'personal', scope: 'personal', scopeLabel: 'Pessoal', titleKey: 'access.workspace.navigation.title', icon: 'overview' },
            { id: 'tenant', scope: 'tenant', scopeLabel: 'Organização', titleKey: 'access.workspace.navigation.title', icon: 'contacts' },
        ];
        const wrapper = mount(MobileNavigationDrawer, {
            props: { modelValue: true, brandLabel: 'Rinos One', title: 'Navegação', closeLabel: 'Fechar', emptyLabel: 'Vazia', categories, destinations: [], scopeLabels: { personal: 'Ana Souza', tenant: 'Organização Alfa' } },
            global: { plugins: [i18n] },
        });

        expect(wrapper.findAll('.mobile-navigation-drawer__scope')).toHaveLength(2);
        expect(wrapper.text()).toContain('Ana Souza');
        expect(wrapper.text()).toContain('Organização Alfa');

        await wrapper.get('[aria-label="Recolher Organização Alfa"]').trigger('click');
        expect(wrapper.get('[aria-label="Expandir Organização Alfa"]').attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
    });
});
