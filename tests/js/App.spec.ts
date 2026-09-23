import { mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import App from '../../resources/js/App.vue';

vi.mock('axios', () => ({
    default: { delete: vi.fn(), get: vi.fn(), isAxiosError: vi.fn(() => false), post: vi.fn(), put: vi.fn() },
    isAxiosError: vi.fn(() => false),
}));

const http = axios as unknown as { delete: ReturnType<typeof vi.fn>; get: ReturnType<typeof vi.fn>; isAxiosError: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn>; put: ReturnType<typeof vi.fn> };

describe('application access', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        http.isAxiosError.mockReturnValue(false);
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
        window.history.replaceState({}, '', '/');
    });

    it('mounts the public registration mode', () => {
        const wrapper = mount(App);

        expect(wrapper.get('h1').text()).toBe('Acesse sua conta');
        expect(wrapper.get('button[type="submit"]').text()).toBe('Criar conta');
        expect(wrapper.get('input[type="checkbox"]')).toBeDefined();
    });

    it('restores the authenticated interface from the current session on page load', async () => {
        http.get.mockResolvedValue({ data: { persistentAuthentication: true, user: { displayName: 'Pessoa', passwordDefined: false } } });
        const wrapper = mount(App);

        await vi.waitFor(() => expect(wrapper.get('h1').text()).toBe('Segurança de acesso'));

        expect(wrapper.text()).toContain('Você permanecerá conectado neste navegador.');
    });

    it('allows an authenticated user without a password to define one', async () => {
        http.get.mockResolvedValue({ data: { persistentAuthentication: false, user: { displayName: 'Pessoa', passwordDefined: false } } });
        http.put.mockResolvedValue({ data: null });
        const wrapper = mount(App);

        await vi.waitFor(() => expect(wrapper.get('h1').text()).toBe('Segurança de acesso'));
        await wrapper.get('button').trigger('click');
        await wrapper.get('#new-password').setValue('Senha#1');
        await wrapper.get('form').trigger('submit');
        await vi.waitFor(() => expect(http.put).toHaveBeenCalledWith('/api/v1/auth/password', { password: 'Senha#1' }));

        expect(wrapper.text()).toContain('Senha definida.');
    });

    it('ends the current session and returns to access entry', async () => {
        http.get.mockResolvedValue({ data: { persistentAuthentication: false, user: { displayName: 'Pessoa', passwordDefined: true } } });
        http.delete.mockResolvedValue({ data: null });
        const wrapper = mount(App);

        await vi.waitFor(() => expect(wrapper.get('h1').text()).toBe('Segurança de acesso'));
        await wrapper.get('button:last-child').trigger('click');
        await vi.waitFor(() => expect(http.delete).toHaveBeenCalledWith('/api/v1/auth/session'));

        expect(wrapper.get('h1').text()).toBe('Acesse sua conta');
    });

    it('requires explicit confirmation before invalidating other sessions', async () => {
        http.get.mockResolvedValue({ data: { persistentAuthentication: true, user: { displayName: 'Pessoa', passwordDefined: true } } });
        http.delete.mockResolvedValue({ data: null });
        const wrapper = mount(App);

        await vi.waitFor(() => expect(wrapper.get('h1').text()).toBe('Segurança de acesso'));
        await wrapper.get('button:nth-last-child(2)').trigger('click');
        expect(wrapper.get('[role="alertdialog"]').text()).toContain('Invalidar outras sessões?');
        await wrapper.get('[role="alertdialog"] button').trigger('click');
        expect(wrapper.find('[role="alertdialog"]').exists()).toBe(false);

        await wrapper.get('button:nth-last-child(2)').trigger('click');
        await wrapper.get('[role="alertdialog"] button:last-child').trigger('click');
        await vi.waitFor(() => expect(http.delete).toHaveBeenCalledWith('/api/v1/auth/other-sessions'));
        expect(wrapper.text()).toContain('Outras sessões foram invalidadas.');
    });

    it('moves between access modes with the keyboard and keeps a single tabbable tab', async () => {
        const wrapper = mount(App, { attachTo: document.body });
        const tabs = wrapper.findAll('[role="tab"]');

        await vi.waitFor(() => expect(document.activeElement).toBe(wrapper.get('#email').element));
        await tabs[0].trigger('keydown', { key: 'ArrowRight' });

        expect(tabs[1].attributes('aria-selected')).toBe('true');
        expect(tabs[0].attributes('tabindex')).toBe('-1');
        expect(document.activeElement).toBe(tabs[1].element);
        wrapper.unmount();
    });

    it('shows client validation without sending a request', async () => {
        const wrapper = mount(App);

        await wrapper.get('form').trigger('submit');

        expect(wrapper.text()).toContain('Informe um e-mail válido.');
        expect(http.post).not.toHaveBeenCalled();
    });

    it('submits registration with the real API payload and presents confirmation', async () => {
        http.post.mockResolvedValue({ data: { challengeId: 'challenge-1', message: 'Verifique seu e-mail.' } });
        const wrapper = mount(App);

        await wrapper.get('#email').setValue('pessoa@example.test');
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('form').trigger('submit');
        await vi.waitFor(() => expect(http.post).toHaveBeenCalled());

        expect(http.post).toHaveBeenCalledWith('/api/v1/auth/registrations', { email: 'pessoa@example.test', rememberMe: true });
        expect(wrapper.text()).toContain('Confirme seu e-mail');
        expect(wrapper.text()).toContain('Verifique seu e-mail.');
    });

    it('confirms a registration code with the required display name and enters the security state', async () => {
        http.post
            .mockResolvedValueOnce({ data: { challengeId: 'challenge-1', message: 'Verifique seu e-mail.' } })
            .mockResolvedValueOnce({ data: { user: { id: 'user-1' } } });
        http.get.mockRejectedValueOnce(new Error('not authenticated')).mockResolvedValue({ data: { persistentAuthentication: true, user: { displayName: 'Pessoa', passwordDefined: false } } });
        const wrapper = mount(App);

        await wrapper.get('#email').setValue('pessoa@example.test');
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('form').trigger('submit');
        await vi.waitFor(() => expect(wrapper.find('#code').exists()).toBe(true));
        expect(wrapper.get('#code').attributes('inputmode')).toBe('numeric');
        expect(wrapper.get('#code').attributes('maxlength')).toBe('6');
        await wrapper.get('#code').setValue('012345');
        await wrapper.get('#display-name').setValue('Pessoa');
        await wrapper.get('form').trigger('submit');
        await vi.waitFor(() => expect(http.get).toHaveBeenCalledWith('/api/v1/auth/session'));

        expect(http.post).toHaveBeenLastCalledWith('/api/v1/auth/email-verifications', { challengeId: 'challenge-1', code: '012345', displayName: 'Pessoa' });
        expect(wrapper.get('h1').text()).toBe('Segurança de acesso');
        expect(wrapper.text()).toContain('Você permanecerá conectado neste navegador.');
    });

    it('keeps confirmation data available and explains an invalid or expired code safely', async () => {
        http.post
            .mockResolvedValueOnce({ data: { challengeId: 'challenge-1', message: 'Verifique seu e-mail.' } })
            .mockRejectedValueOnce({ response: { status: 400 } });
        http.isAxiosError.mockReturnValue(true);
        const wrapper = mount(App, { attachTo: document.body });

        await wrapper.get('#email').setValue('pessoa@example.test');
        await wrapper.get('form').trigger('submit');
        await vi.waitFor(() => expect(wrapper.find('#code').exists()).toBe(true));
        await wrapper.get('#code').setValue('012345');
        await wrapper.get('#display-name').setValue('Pessoa');
        await wrapper.get('form').trigger('submit');
        await vi.waitFor(() => expect(wrapper.text()).toContain('Este código ou link não é mais válido.'));

        expect(wrapper.get('#display-name').element).toBe(document.activeElement);
        wrapper.unmount();
    });

    it('switches to passwordless access and uses its endpoint', async () => {
        http.post.mockResolvedValue({ data: { challengeId: 'challenge-2', message: 'Confira sua caixa de entrada.' } });
        const wrapper = mount(App);

        await wrapper.findAll('[role="tab"]')[2].trigger('click');
        await wrapper.get('#email').setValue('pessoa@example.test');
        await wrapper.get('form').trigger('submit');
        await vi.waitFor(() => expect(http.post).toHaveBeenCalled());

        expect(http.post).toHaveBeenCalledWith('/api/v1/auth/passwordless-sessions', { email: 'pessoa@example.test', rememberMe: false });
    });

    it('submits password access and loads the authenticated state', async () => {
        http.post.mockResolvedValue({ data: { user: { id: 'user-1' } } });
        http.get.mockRejectedValueOnce(new Error('not authenticated')).mockResolvedValue({ data: { persistentAuthentication: false, user: { displayName: 'Pessoa', passwordDefined: true } } });
        const wrapper = mount(App);

        await wrapper.findAll('[role="tab"]')[1].trigger('click');
        await wrapper.get('#email').setValue('pessoa@example.test');
        await wrapper.get('#password').setValue('Senha#1');
        await wrapper.get('form').trigger('submit');
        await vi.waitFor(() => expect(http.get).toHaveBeenCalledWith('/api/v1/auth/session'));

        expect(http.post).toHaveBeenCalledWith('/api/v1/auth/password-sessions', { email: 'pessoa@example.test', password: 'Senha#1', rememberMe: false });
        expect(wrapper.get('h1').text()).toBe('Segurança de acesso');
    });

    it('reports offline state and does not send a request', async () => {
        const wrapper = mount(App);
        window.dispatchEvent(new Event('offline'));
        await wrapper.get('#email').setValue('pessoa@example.test');

        expect(wrapper.text()).toContain('Você está sem conexão.');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
        expect(http.post).not.toHaveBeenCalled();
    });

    it('consumes a passwordless link opened in another tab and removes its secret from the address bar', async () => {
        window.history.replaceState({}, '', '/access/passwordless?challengeId=challenge-3&token=secret-token');
        http.post.mockResolvedValue({ data: { user: { id: 'user-1' } } });
        http.get.mockRejectedValueOnce(new Error('not authenticated')).mockResolvedValue({ data: { persistentAuthentication: true, user: { displayName: 'Pessoa', passwordDefined: false } } });

        const wrapper = mount(App);

        await vi.waitFor(() => expect(http.post).toHaveBeenCalledWith('/api/v1/auth/passwordless-sessions/link-confirmations', { challengeId: 'challenge-3', token: 'secret-token' }));
        expect(window.location.search).toBe('');
        await vi.waitFor(() => expect(wrapper.get('h1').text()).toBe('Segurança de acesso'));
        expect(wrapper.text()).toContain('Você permanecerá conectado neste navegador.');
    });
});
