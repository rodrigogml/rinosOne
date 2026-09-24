import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import UserAvatar from '../../../resources/js/design-system/UserAvatar.vue';
import { deriveAvatarFallback } from '../../../resources/js/design-system/avatarPresentation';

describe('avatar presentation', () => {
    it.each([
        ['Rodrigo Leitão', { kind: 'initials', label: 'RL' }],
        ['Álvaro', { kind: 'initials', label: 'Ál' }],
        ['R', { kind: 'initials', label: 'R' }],
        ['  Ana   de   Souza  ', { kind: 'initials', label: 'AS' }],
        ['李 小龍', { kind: 'initials', label: '李小' }],
        ['', { kind: 'unknown', label: '?' }],
        [undefined, { kind: 'unknown', label: '?' }],
    ])('derives the expected fallback for %s', (displayName, expected) => {
        expect(deriveAvatarFallback(displayName)).toEqual(expected);
    });

    it('exposes an accessible identity and renders a future image when supplied', () => {
        const wrapper = mount(UserAvatar, { props: { displayName: 'Rodrigo Leitão', imageSrc: '/avatar.png', label: 'Perfil de Rodrigo Leitão' } });

        expect(wrapper.get('[role="img"]').attributes('aria-label')).toBe('Perfil de Rodrigo Leitão');
        expect(wrapper.get('img').attributes('src')).toBe('/avatar.png');
        expect(wrapper.get('img').attributes('alt')).toBe('');
    });

    it('falls back to derived initials when an image fails to load', async () => {
        const wrapper = mount(UserAvatar, { props: { displayName: 'Rodrigo Leitão', imageSrc: '/avatar.png', label: 'Perfil de Rodrigo Leitão' } });

        await wrapper.get('img').trigger('error');

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.get('.user-avatar__fallback').text()).toBe('RL');
    });
});
