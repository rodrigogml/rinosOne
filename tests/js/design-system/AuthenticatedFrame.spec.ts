import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AuthenticatedFrame from '../../../resources/js/design-system/AuthenticatedFrame.vue';
import { i18n } from '../../../resources/js/i18n';

vi.mock('axios');

describe('authenticated frame profile bootstrap', () => {
    beforeEach(() => { vi.resetAllMocks(); setActivePinia(createPinia()); });

    it('loads the profile as the authenticated frame mounts so the topbar receives the avatar', async () => {
        vi.mocked(axios.get).mockResolvedValue({ data: { user: { displayName: 'Ana Souza' }, avatar: { available: true, url: '/api/v1/profile/avatar', updatedAt: '2026-10-02T00:00:00Z' } } });
        const pinia = createPinia(); setActivePinia(pinia);
        const wrapper = mount(AuthenticatedFrame, { props: { displayName: 'Ana' }, global: { plugins: [pinia, i18n], stubs: { ApplicationTopBar: { props: ['avatarUrl'], template: '<div :data-avatar-url="avatarUrl"><slot /></div>' }, AppShell: { template: '<main><slot /></main>' }, WorkspaceShell: { template: '<div />' } } } });

        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/profile');
        expect(wrapper.get('[data-avatar-url]').attributes('data-avatar-url')).toBe('/api/v1/profile/avatar?v=2026-10-02T00%3A00%3A00Z');
        wrapper.unmount();
    });
});
