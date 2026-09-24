import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { describe, expect, it } from 'vitest';
import ApplicationTopBar from '../../../resources/js/design-system/ApplicationTopBar.vue';
import { i18n } from '../../../resources/js/i18n';

function mountTopBar() {
    const pinia = createPinia();
    setActivePinia(pinia);

    return mount(ApplicationTopBar, {
        props: {
            displayName: 'Rodrigo Leitão', brandLabel: 'Rinos One', mobileNavigationLabel: 'Abrir navegação', avatarLabel: 'Menu pessoal de Rodrigo Leitão', menuLabel: 'Menu pessoal', settingsLabel: 'Configurações do usuário', settingsUnavailableLabel: 'Disponível em breve', signOutLabel: 'Sair',
        },
        global: { plugins: [pinia, i18n] },
        attachTo: document.body,
    });
}

describe('application top bar', () => {
    it('renders the derived brand and user identity', () => {
        const wrapper = mountTopBar();

        expect(wrapper.get('.application-top-bar__desktop-brand').attributes('src')).toBe('/assets/brand/logo-768.png');
        expect(wrapper.get('.application-top-bar__mobile-trigger img').attributes('src')).toBe('/assets/brand/logo-768.png');
        expect(wrapper.get('[role="img"]').text()).toBe('RL');
        expect(wrapper.get('button[aria-label="Abrir navegação"]')).toBeTruthy();
        wrapper.unmount();
    });

    it('opens the personal dialog, restores focus and emits sign-out intent', async () => {
        const wrapper = mountTopBar();
        const opener = wrapper.get('button[aria-label="Menu pessoal de Rodrigo Leitão"]');

        (opener.element as HTMLButtonElement).focus();
        await opener.trigger('click');
        expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Menu pessoal');
        expect(wrapper.get('button:disabled').text()).toBe('Configurações do usuário');
        await wrapper.get('button[aria-label="Preferências visuais"]').trigger('click');
        await wrapper.get('[role="dialog"][aria-label="Preferências visuais"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.get('[role="dialog"][aria-label="Menu pessoal"]')).toBeTruthy();
        await wrapper.get('button[aria-label="Sair"]').trigger('click');
        expect(wrapper.emitted('signOut')).toEqual([[]]);
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(document.activeElement).toBe(opener.element as HTMLButtonElement);
        wrapper.unmount();
    });

    it('emits the mobile navigation intent without deciding the destination', async () => {
        const wrapper = mountTopBar();

        await wrapper.get('button[aria-label="Abrir navegação"]').trigger('click');

        expect(wrapper.emitted('openMobileNavigation')).toEqual([[]]);
        wrapper.unmount();
    });
});
