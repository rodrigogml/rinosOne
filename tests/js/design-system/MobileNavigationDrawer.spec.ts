import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import MobileNavigationDrawer from '../../../resources/js/design-system/MobileNavigationDrawer.vue';

function mountDrawer(modelValue = true) {
    return mount(MobileNavigationDrawer, { attachTo: document.body, props: { modelValue, brandLabel: 'Rinos One', title: 'Navegação', closeLabel: 'Fechar navegação', emptyLabel: 'Nenhuma área adicional está disponível.' } });
}

describe('mobile navigation drawer', () => {
    it('shows an honest empty navigation state without product links', () => {
        const wrapper = mountDrawer();

        expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Navegação');
        expect(wrapper.get('.mobile-navigation-drawer__brand').attributes('src')).toBe('/assets/brand/logo-768.png');
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
});
