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
};
const mountedApplications: ReturnType<typeof mount>[] = [];

function mountAuthenticated() {
    http.get.mockResolvedValue({ data: { persistentAuthentication: true, user: { displayName: 'Person', passwordDefined: false } } });
    const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia(), i18n] } });
    mountedApplications.push(wrapper);

    return wrapper;
}

describe('authenticated workspace area', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        i18n.global.locale.value = 'pt-BR';
        window.localStorage.clear();
        window.history.replaceState({}, '', '/');
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
    });

    afterEach(() => {
        mountedApplications.splice(0).forEach((wrapper) => wrapper.unmount());
        document.body.replaceChildren();
    });

    it('presents the neutral workspace below the global top bar without security demonstration content', async () => {
        const wrapper = mountAuthenticated();
        await flushPromises();

        expect(wrapper.get('#workspace-title').text()).toBe('Área de trabalho');
        expect(wrapper.get('.workspace-stage--empty').text()).toContain('Sua área de trabalho está pronta');
        expect(wrapper.get('.application-top-bar__desktop-brand').attributes('src')).toBe('/assets/brand/logo-768.png');
        expect(wrapper.get('button[aria-label="Menu pessoal de Person"]').text()).toContain('Pe');
        expect(wrapper.find('#security-title').exists()).toBe(false);
        expect(wrapper.find('.security-card').exists()).toBe(false);
        expect(wrapper.find('#new-password').exists()).toBe(false);
    });

    it('returns to public access when the current session is no longer valid', async () => {
        http.get.mockRejectedValue(new Error('No authenticated session'));
        const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia(), i18n] } });
        mountedApplications.push(wrapper);
        await flushPromises();

        expect(wrapper.get('h1').text()).toBe('Acesse sua conta');
        expect(wrapper.find('#workspace-title').exists()).toBe(false);
    });

    it('keeps the authenticated workspace available after a language change without another session request', async () => {
        const wrapper = mountAuthenticated();
        await flushPromises();
        const sessionRequests = http.get.mock.calls.length;

        await wrapper.get('button[aria-label="Menu pessoal de Person"]').trigger('click');
        await wrapper.get('button[aria-haspopup="listbox"]').trigger('click');
        await wrapper.get('#language-option-en').trigger('click');

        expect(wrapper.get('#workspace-title').text()).toBe('Workspace');
        expect(http.get).toHaveBeenCalledTimes(sessionRequests);
    });

    it('uses the shared top bar to close the mobile drawer and end the authenticated session', async () => {
        http.delete.mockResolvedValue({ data: null });
        const wrapper = mountAuthenticated();
        await flushPromises();

        await wrapper.get('button[aria-label="Abrir navegação"]').trigger('click');
        expect(wrapper.get('[role="dialog"][aria-label="Navegação"]').attributes('aria-modal')).toBe('true');

        await wrapper.get('button[aria-label="Menu pessoal de Person"]').trigger('click');
        expect(wrapper.findAll('[role="dialog"][aria-label="Navegação"]')).toHaveLength(0);

        await wrapper.get('button[aria-label="Sair"]').trigger('click');
        await flushPromises();

        expect(http.delete).toHaveBeenCalledWith('/api/v1/auth/session');
        expect(wrapper.get('h1').text()).toBe('Acesse sua conta');
    });
});
