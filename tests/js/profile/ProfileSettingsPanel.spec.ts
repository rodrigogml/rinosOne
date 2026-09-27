import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ProfileSettingsPanel from '../../../resources/js/profile/ProfileSettingsPanel.vue';
import { i18n } from '../../../resources/js/i18n';

describe('ProfileSettingsPanel', () => {
    it('presents the profile identity structure and enables only changed name and avatar removal', async () => {
        const wrapper = mount(ProfileSettingsPanel, {
            global: { plugins: [i18n] },
            props: {
                profile: {
                    user: { displayName: 'Ana Souza' },
                    avatar: { available: true, url: '/api/v1/profile/avatar', updatedAt: '2026-09-26T12:00:00Z' },
                },
            },
        });

        expect(wrapper.get('h3').text()).toBe('Perfil');
        expect(wrapper.get('.user-avatar img').attributes('src')).toBe('/api/v1/profile/avatar');
        expect(wrapper.get('input').element.value).toBe('Ana Souza');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('button.ui-button--destructive').attributes('disabled')).toBeUndefined();

        await wrapper.get('input').setValue('Ana Lima');
        await wrapper.get('form').trigger('submit');
        await wrapper.get('button.ui-button--destructive').trigger('click');

        expect(wrapper.emitted('saveName')).toEqual([['Ana Lima']]);
        expect(wrapper.emitted('requestRemoveAvatar')).toHaveLength(1);
    });

    it('announces the loading state before the profile representation is available', () => {
        const wrapper = mount(ProfileSettingsPanel, { props: { loading: true }, global: { plugins: [i18n] } });

        expect(wrapper.get('[aria-live="polite"]').text()).toContain('Carregando perfil');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('keeps an emptied name visible and associates its validation message with the field', async () => {
        const wrapper = mount(ProfileSettingsPanel, {
            props: { profile: { user: { displayName: 'Ana Souza' }, avatar: { available: false, url: null, updatedAt: null } } }, global: { plugins: [i18n] },
        });

        await wrapper.get('input').setValue('');

        expect(wrapper.get('#profile-display-name-error').text()).toBe('Informe um nome.');
        expect(wrapper.get('input').attributes('aria-describedby')).toBe('profile-display-name-error');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
    });
});
