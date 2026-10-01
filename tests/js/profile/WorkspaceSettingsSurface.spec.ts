import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { createPinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import WorkspaceSettingsSurface from '../../../resources/js/design-system/WorkspaceSettingsSurface.vue';
import { i18n } from '../../../resources/js/i18n';

vi.mock('axios', () => ({ default: { delete: vi.fn(), get: vi.fn(), isAxiosError: vi.fn(() => false), patch: vi.fn(), put: vi.fn() }, isAxiosError: vi.fn(() => false) }));

const http = axios as unknown as {
    delete: ReturnType<typeof vi.fn>;
    get: ReturnType<typeof vi.fn>;
    patch: ReturnType<typeof vi.fn>;
};

const profile = {
    user: { displayName: 'Ana Souza' },
    avatar: { available: true, url: '/api/v1/profile/avatar', updatedAt: '2026-09-26T12:00:00Z' },
};

function mountSurface() {
    return mount(WorkspaceSettingsSurface, {
        props: { surface: { id: 'settings-1', destinationId: 'personal.settings', scope: 'personal', tenantId: null, titleKey: 'settings', label: 'Configurações', icon: 'settings', dirty: false, status: 'active' } },
        global: { plugins: [createPinia(), i18n] },
    });
}

describe('WorkspaceSettingsSurface profile integration', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        i18n.global.locale.value = 'pt-BR';
        http.get.mockResolvedValueOnce({ data: { user: { passwordDefined: false } } });
        http.get.mockResolvedValueOnce({ data: profile });
    });

    it('loads and saves the profile representation, then publishes the updated identity', async () => {
        const wrapper = mountSurface();
        await flushPromises();

        expect(http.get).toHaveBeenCalledWith('/api/v1/profile');
        await wrapper.get('#profile-display-name').setValue('Ana Lima');
        http.patch.mockResolvedValueOnce({ data: { ...profile, user: { displayName: 'Ana Lima' } } });
        await wrapper.get('.profile-settings-panel__name').trigger('submit');
        await flushPromises();

        expect(http.patch).toHaveBeenCalledWith('/api/v1/profile', { displayName: 'Ana Lima' });
        expect(wrapper.emitted('profileUpdated')?.at(-1)).toEqual([{ user: { displayName: 'Ana Lima' }, avatar: profile.avatar }]);
    });

    it('confirms before deleting the avatar and publishes the fallback representation', async () => {
        const wrapper = mountSurface();
        await flushPromises();

        await wrapper.get('.profile-settings-panel .ui-button--destructive').trigger('click');
        expect(wrapper.get('.ui-dialog__title').text()).toBe('Remover imagem de perfil?');
        http.delete.mockResolvedValueOnce({ status: 204 });
        await wrapper.get('.ui-dialog .ui-button--destructive').trigger('click');
        await flushPromises();

        expect(http.delete).toHaveBeenCalledWith('/api/v1/profile/avatar');
        expect(wrapper.emitted('profileUpdated')?.at(-1)).toEqual([{ user: profile.user, avatar: { available: false, url: null, updatedAt: null } }]);
    });

    it('opens the contained avatar editor from the enabled profile action', async () => {
        const wrapper = mountSurface();
        await flushPromises();

        await wrapper.get('.profile-settings-panel .workspace-settings__setting-actions .ui-button--secondary').trigger('click');

        expect(wrapper.find('.avatar-crop-dialog').exists()).toBe(true);
        expect(wrapper.get('.avatar-crop-dialog input[type="file"]').attributes('accept')).toBe('image/jpeg,image/png,image/webp');
    });

    it('preserves an edited name after a remote failure and retries the same safe intent', async () => {
        const wrapper = mountSurface();
        await flushPromises();
        await wrapper.get('#profile-display-name').setValue('Ana Lima');
        http.patch.mockRejectedValueOnce(new Error('unavailable'));
        await wrapper.get('.profile-settings-panel__name').trigger('submit');
        await flushPromises();

        expect((wrapper.get('#profile-display-name').element as HTMLInputElement).value).toBe('Ana Lima');
        expect(wrapper.text()).toContain('Não foi possível salvar o nome agora.');
        http.patch.mockResolvedValueOnce({ data: { ...profile, user: { displayName: 'Ana Lima' } } });
        await wrapper.get('.profile-settings-panel__name-actions .ui-button--secondary').trigger('click');
        await flushPromises();

        expect(http.patch).toHaveBeenLastCalledWith('/api/v1/profile', { displayName: 'Ana Lima' });
    });

    it('confirms before changing section when the profile name has unsaved changes', async () => {
        const wrapper = mountSurface();
        await flushPromises();
        await wrapper.get('#profile-display-name').setValue('Ana Lima');
        await wrapper.get('.workspace-settings__nav-list button:nth-child(2)').trigger('click');

        expect(wrapper.get('.ui-dialog__title').text()).toBe('Descartar alterações no perfil?');
        await wrapper.get('.ui-dialog .ui-button--destructive').trigger('click');

        expect(wrapper.text()).toContain('Família cromática');
    });

    it('translates the profile section without discarding the name currently being edited', async () => {
        const wrapper = mountSurface();
        await flushPromises();
        await wrapper.get('#profile-display-name').setValue('Ana Lima');

        i18n.global.locale.value = 'en';
        await wrapper.vm.$nextTick();

        expect(wrapper.get('.profile-settings-panel__header h3').text()).toBe('Profile');
        expect(wrapper.text()).toContain('Save changes');
        expect((wrapper.get('#profile-display-name').element as HTMLInputElement).value).toBe('Ana Lima');
    });

    it('provides the profile catalog in every supported locale', () => {
        for (const locale of ['pt-BR', 'en', 'es', 'fr'] as const) {
            i18n.global.locale.value = locale;
            expect(i18n.global.t('access.profile.section')).not.toBe('access.profile.section');
            expect(i18n.global.t('access.profile.removeTitle')).not.toBe('access.profile.removeTitle');
        }
    });

    it('uses the approved visual-preference icons without changing selection controls', async () => {
        const wrapper = mountSurface();
        await flushPromises();
        await wrapper.get('.workspace-settings__nav-list button:nth-child(2)').trigger('click');

        expect(wrapper.get('.workspace-settings__mode[aria-label="Claro"] img').attributes('src')).toBe('/assets/icons/lamp-on_24.png');
        expect(wrapper.get('.workspace-settings__mode[aria-label="Escuro"] img').attributes('src')).toBe('/assets/icons/lamp-off_24.png');
        expect(wrapper.get('.workspace-settings__choice[aria-label="Confortável"] img').attributes('src')).toBe('/assets/icons/themeTextBig_24.png');
        expect(wrapper.findAll('.workspace-settings__choice[aria-label="Compacta"]')[1].get('img').attributes('src')).toBe('/assets/icons/padding-s_24.png');
        expect(wrapper.findAll('.workspace-settings__choice[aria-label="Padrão"]')[2].get('img').attributes('src')).toBe('/assets/icons/object-size-m_24.png');

        await wrapper.get('.workspace-settings__mode[aria-label="Escuro"]').trigger('click');
        expect(wrapper.get('.workspace-settings__mode[aria-label="Escuro"]').classes()).toContain('workspace-settings__mode--selected');
        expect(wrapper.get('.workspace-settings__mode[aria-label="Claro"]').text()).toBe('');
    });
});
