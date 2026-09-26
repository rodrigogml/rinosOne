import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { createPinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import App from '../../resources/js/App.vue';
import { i18n } from '../../resources/js/i18n';
import { VISUAL_PREFERENCES_STORAGE_KEY } from '../../resources/js/preferences/visualPreferences';

vi.mock('axios', () => ({ default: { delete: vi.fn(), get: vi.fn(), isAxiosError: vi.fn(() => false), post: vi.fn(), put: vi.fn() }, isAxiosError: vi.fn(() => false) }));

const http = axios as unknown as {
    get: ReturnType<typeof vi.fn>;
    isAxiosError: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
};

const mountedAccesses: ReturnType<typeof mount>[] = [];

function mountAccess() {
    const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia(), i18n] } });
    mountedAccesses.push(wrapper);

    return wrapper;
}

describe('email confirmation', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        http.get.mockRejectedValue(new Error('No authenticated session'));
        i18n.global.locale.value = 'pt-BR';
        window.localStorage.clear();
        window.history.replaceState({}, '', '/access/register');
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
    });

    afterEach(() => {
        mountedAccesses.splice(0).forEach((wrapper) => wrapper.unmount());
        document.body.replaceChildren();
        vi.useRealTimers();
    });

    it('confirms registration with a six-digit numeric code and the existing API contract', async () => {
        http.post.mockResolvedValueOnce({ data: { challengeId: 1 } }).mockResolvedValueOnce({ data: { user: { id: 1 } } });
        const wrapper = mountAccess();
        await flushPromises();

        await wrapper.get('#display-name').setValue('Person');
        await wrapper.get('#email').setValue('person@example.test');
        await wrapper.get('form').trigger('submit');
        await wrapper.get('#code').setValue('a12-345678');

        expect((wrapper.get('#code').element as HTMLInputElement).value).toBe('123456');
        expect(wrapper.get('#code').attributes('inputmode')).toBe('numeric');
        expect(wrapper.get('#code').attributes('autocomplete')).toBe('one-time-code');

        await wrapper.get('form').trigger('submit');

        expect(http.post).toHaveBeenLastCalledWith('/api/v1/auth/email-verifications', {
            challengeId: 1, code: '123456',
        });
    });

    it('shows a neutral invalid-confirmation error and returns focus to the numeric input', async () => {
        http.post.mockResolvedValueOnce({ data: { challengeId: 1 } });
        http.post.mockRejectedValueOnce({ response: { status: 400 } });
        http.isAxiosError.mockReturnValue(true);
        const wrapper = mountAccess();
        await flushPromises();

        await wrapper.get('#display-name').setValue('Person');
        await wrapper.get('#email').setValue('person@example.test');
        await wrapper.get('form').trigger('submit');
        await wrapper.get('#code').setValue('123456');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Este código ou link não é mais válido.');
        expect(document.activeElement).toBe(wrapper.get('#code').element);
    });

    it('expires an unconsumed code after the configured ten-minute confirmation window', async () => {
        vi.useFakeTimers();
        http.post.mockResolvedValue({ data: { challengeId: 1 } });
        const wrapper = mountAccess();
        await flushPromises();

        await wrapper.get('#display-name').setValue('Person');
        await wrapper.get('#email').setValue('person@example.test');
        await wrapper.get('form').trigger('submit');
        await vi.advanceTimersByTimeAsync(600_000);

        expect(wrapper.text()).toContain('Este código expirou.');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
    });

    it('removes passwordless link secrets from the address before using the existing confirmation endpoint', async () => {
        window.history.replaceState({}, '', '/access/passwordless?challengeId=2&token=secret-token');
        http.post.mockResolvedValue({ data: { user: { id: 'user-1' } } });
        http.get.mockRejectedValueOnce(new Error('No authenticated session')).mockResolvedValueOnce({ data: { persistentAuthentication: false, user: { displayName: 'Person', passwordDefined: false } } });

        mountAccess();
        await flushPromises();

        expect(window.location.search).toBe('');
        expect(http.post).toHaveBeenCalledWith('/api/v1/auth/passwordless-sessions/link-confirmations', {
            challengeId: 2, token: 'secret-token',
        });
    });

    it('keeps an active confirmation on language changes without persisting its code or emitting another message', async () => {
        http.post.mockResolvedValue({ data: { challengeId: 1 } });
        const wrapper = mountAccess();
        await flushPromises();

        await wrapper.get('#display-name').setValue('Person');
        await wrapper.get('#email').setValue('person@example.test');
        await wrapper.get('form').trigger('submit');
        await wrapper.get('#code').setValue('123456');
        const callsBeforeLanguageChange = http.post.mock.calls.length;

        await wrapper.get('button[aria-haspopup="listbox"]').trigger('click');
        await wrapper.get('#language-option-en').trigger('click');

        expect((wrapper.get('#code').element as HTMLInputElement).value).toBe('123456');
        expect(http.post).toHaveBeenCalledTimes(callsBeforeLanguageChange);
        expect(window.localStorage.getItem(VISUAL_PREFERENCES_STORAGE_KEY)).not.toContain('123456');
        expect(window.localStorage.getItem(VISUAL_PREFERENCES_STORAGE_KEY)).not.toContain('challenge-1');
    });

    it('presents an offline state and disables confirmation without issuing a request', async () => {
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: false });
        window.history.replaceState({}, '', '/access/passwordless?challengeId=2&token=secret-token');
        const wrapper = mountAccess();
        await flushPromises();

        expect(wrapper.text()).toContain('Você está sem conexão.');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
        expect(http.post).not.toHaveBeenCalled();
    });
});
