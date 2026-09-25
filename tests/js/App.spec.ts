import { config, mount } from '@vue/test-utils';
import axios from 'axios';
import { createPinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import App from '../../resources/js/App.vue';
import { i18n } from '../../resources/js/i18n';

vi.mock('axios', () => ({ default: { delete: vi.fn(), get: vi.fn(), isAxiosError: vi.fn(() => false), post: vi.fn(), put: vi.fn() }, isAxiosError: vi.fn(() => false) }));

const http = axios as unknown as { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };
config.global.plugins = [createPinia(), i18n];

describe('access entry and account creation', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        i18n.global.locale.value = 'pt-BR';
        window.localStorage.clear();
        window.history.replaceState({}, '', '/');
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
    });

    it('renders the centered brand, unified login fields and presentation controls', () => {
        const wrapper = mount(App);

        expect(wrapper.get('img[alt="Rinos One"]').attributes('src')).toBe('/assets/brand/logo-768.png');
        expect(wrapper.get('h1').text()).toBe('Acesse sua conta');
        expect(wrapper.find('#email').exists()).toBe(true);
        expect(wrapper.find('#password').exists()).toBe(true);
        expect(wrapper.get('button[type="submit"]').text()).toBe('Entrar sem senha');
        expect(wrapper.findAll('.presentation-control')).toHaveLength(2);
    });

    it('uses passwordless access when password is empty and password access when it is filled', async () => {
        http.post.mockResolvedValue({ data: { challengeId: 'challenge-1' } });
        const wrapper = mount(App);

        await wrapper.get('#email').setValue('person@example.test');
        await wrapper.get('form').trigger('submit');
        expect(http.post).toHaveBeenLastCalledWith('/api/v1/auth/passwordless-sessions', { email: 'person@example.test', rememberMe: false });

        window.history.replaceState({}, '', '/');
        const secondWrapper = mount(App);
        await secondWrapper.get('#email').setValue('person@example.test');
        await secondWrapper.get('#password').setValue('Secret#1');
        expect(secondWrapper.get('button[type="submit"]').text()).toBe('Entrar');
        http.post.mockResolvedValue({ data: { user: { id: 'user-1' } } });
        http.get.mockResolvedValue({ data: { persistentAuthentication: false, user: { displayName: 'Person', passwordDefined: true } } });
        await secondWrapper.get('form').trigger('submit');
        expect(http.post).toHaveBeenLastCalledWith('/api/v1/auth/password-sessions', { email: 'person@example.test', password: 'Secret#1', rememberMe: false });
    });

    it('opens a separate registration journey without transporting login email or password', async () => {
        const wrapper = mount(App);

        await wrapper.get('#email').setValue('login@example.test');
        await wrapper.get('#password').setValue('Secret#1');
        await wrapper.get('button.access-link').trigger('click');

        expect(window.location.pathname).toBe('/access/register');
        expect(wrapper.get('h1').text()).toBe('Criar conta');
        expect((wrapper.get('#email').element as HTMLInputElement).value).toBe('');
        expect(wrapper.find('#password').exists()).toBe(false);
        expect(wrapper.find('#display-name').exists()).toBe(true);
    });

    it('persists the display name with the registration request and asks only for the code afterwards', async () => {
        const wrapper = mount(App);
        await wrapper.get('button.access-link').trigger('click');
        http.post.mockResolvedValue({ data: { challengeId: 'challenge-1' } });

        await wrapper.get('#display-name').setValue('Person');
        await wrapper.get('#email').setValue('person@example.test');
        await wrapper.get('form').trigger('submit');

        expect(http.post).toHaveBeenCalledWith('/api/v1/auth/registrations', { email: 'person@example.test', displayName: 'Person', rememberMe: false });
        expect(wrapper.find('#display-name').exists()).toBe(false);
        expect(wrapper.get('#code').attributes('inputmode')).toBe('numeric');
    });

    it('validates display name and email before emitting registration', async () => {
        const wrapper = mount(App);
        await wrapper.get('button.access-link').trigger('click');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.text()).toContain('Informe um nome de exibição.');
        expect(http.post).not.toHaveBeenCalled();
    });
});
