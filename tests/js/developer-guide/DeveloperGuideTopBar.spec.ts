import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { describe, expect, it } from 'vitest';
import DeveloperGuideTopBar from '../../../resources/js/developer-guide/DeveloperGuideTopBar.vue';
import { i18n } from '../../../resources/js/i18n';

describe('developer guide top bar', () => {
    it('reuses the application top bar treatment without authenticated controls', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(DeveloperGuideTopBar, { global: { plugins: [pinia, i18n] } });

        expect(wrapper.get('.application-top-bar')).toBeTruthy();
        expect(wrapper.get('.developer-guide-top-bar__brand').attributes('src')).toBe('/assets/brand/logo-768.png?v=20260930');
        expect(wrapper.find('.user-menu').exists()).toBe(false);
        expect(wrapper.find('.tenant-selector').exists()).toBe(false);

        await wrapper.get('button[aria-label="Preferências visuais"]').trigger('click');
        expect(wrapper.get('[role="dialog"][aria-label="Preferências visuais"]')).toBeTruthy();
    });
});
