import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { createPinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import App from '../../resources/js/App.vue';
import { i18n } from '../../resources/js/i18n';

vi.mock('axios', () => ({ default: { delete: vi.fn(), get: vi.fn(), isAxiosError: vi.fn(() => false), post: vi.fn(), put: vi.fn() }, isAxiosError: vi.fn(() => false) }));

const http = axios as unknown as {
    delete: ReturnType<typeof vi.fn>;
    get: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
};
const mountedAccesses: ReturnType<typeof mount>[] = [];

function mountAuthenticated(passwordDefined = false, persistentAuthentication = false) {
    http.get.mockResolvedValue({ data: { persistentAuthentication, user: { displayName: 'Person', passwordDefined } } });
    const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia(), i18n] } });
    mountedAccesses.push(wrapper);

    return wrapper;
}

type ButtonWrapper = { attributes: (key: string) => string | undefined; element: Element; text: () => string; trigger: (event: string) => Promise<unknown> };

function buttonWithText<T extends { findAll: (selector: string) => ButtonWrapper[] }>(wrapper: T, text: string) {
    const button = wrapper.findAll('button').find((candidate) => candidate.text() === text);
    if (!button) throw new Error(`Button not found: ${text}`);

    return button;
}

describe('authenticated security area', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        i18n.global.locale.value = 'pt-BR';
        window.localStorage.clear();
        window.history.replaceState({}, '', '/');
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
    });

    afterEach(() => {
        mountedAccesses.splice(0).forEach((wrapper) => wrapper.unmount());
        document.body.replaceChildren();
    });

    it('presents semantic security groups, reduced brand, and global presentation controls', async () => {
        const wrapper = mountAuthenticated(false, true);
        await flushPromises();

        expect(wrapper.get('#security-title').text()).toBe('Segurança de acesso');
        expect(wrapper.get('img[alt="Rinos One"]').attributes('src')).toBe('/assets/brand/icon-192.png');
        expect(wrapper.get('#password-title').text()).toBe('Senha');
        expect(wrapper.get('#sessions-title').text()).toBe('Sessões');
        expect(wrapper.findAll('.presentation-control')).toHaveLength(2);
        expect(wrapper.text()).toContain('Você permanecerá conectado neste navegador.');
    });

    it('returns to the public entry when the current session is no longer valid', async () => {
        http.get.mockRejectedValue(new Error('No authenticated session'));
        const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia(), i18n] } });
        mountedAccesses.push(wrapper);
        await flushPromises();

        expect(wrapper.get('h1').text()).toBe('Acesse sua conta');
        expect(wrapper.find('#security-title').exists()).toBe(false);
    });

    it('validates and submits password definition through the unchanged contract', async () => {
        const wrapper = mountAuthenticated();
        await flushPromises();
        await buttonWithText(wrapper, 'Definir senha').trigger('click');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.text()).toContain('Informe uma senha para continuar.');
        expect(http.put).not.toHaveBeenCalled();

        http.put.mockResolvedValue({ data: null });
        await wrapper.get('#new-password').setValue('Senha#1');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.put).toHaveBeenCalledWith('/api/v1/auth/password', { password: 'Senha#1' });
    });

    it('uses the reusable destructive dialog, traps its interaction, and restores focus on cancellation', async () => {
        const wrapper = mountAuthenticated(true);
        await flushPromises();
        const opener = buttonWithText(wrapper, 'Invalidar outras sessões');
        (opener.element as HTMLElement).focus();
        await opener.trigger('click');
        await wrapper.vm.$nextTick();

        const dialog = wrapper.get('[role="alertdialog"]');
        expect(dialog.text()).toContain('Invalidar outras sessões?');
        expect(document.activeElement).toBe(buttonWithText(dialog, 'Cancelar').element);

        await buttonWithText(dialog, 'Cancelar').trigger('click');
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[role="alertdialog"]').exists()).toBe(false);
        expect(document.activeElement).toBe(opener.element);
    });

    it('invalidates other sessions and ends only the current session through their existing endpoints', async () => {
        http.delete.mockResolvedValue({ data: null });
        const wrapper = mountAuthenticated(true);
        await flushPromises();

        await buttonWithText(wrapper, 'Invalidar outras sessões').trigger('click');
        await buttonWithText(wrapper.get('[role="alertdialog"]'), 'Invalidar sessões').trigger('click');
        await flushPromises();
        expect(http.delete).toHaveBeenCalledWith('/api/v1/auth/other-sessions');

        await buttonWithText(wrapper, 'Encerrar esta sessão').trigger('click');
        expect(http.delete).toHaveBeenCalledWith('/api/v1/auth/session');
        expect(wrapper.get('h1').text()).toBe('Acesse sua conta');
    });

    it('keeps the authenticated session intact while language changes and security actions are offline-disabled', async () => {
        const wrapper = mountAuthenticated(true);
        await flushPromises();
        const sessionRequests = http.get.mock.calls.length;

        await wrapper.get('button[aria-haspopup="listbox"]').trigger('click');
        await wrapper.get('#language-option-en').trigger('click');
        expect(wrapper.get('#security-title').text()).toBe('Access security');
        expect(http.get).toHaveBeenCalledTimes(sessionRequests);

        window.dispatchEvent(new Event('offline'));
        await wrapper.vm.$nextTick();
        expect(buttonWithText(wrapper, 'Invalidate other sessions').attributes('disabled')).toBeDefined();
        expect(buttonWithText(wrapper, 'End this session').attributes('disabled')).toBeDefined();
    });
});
