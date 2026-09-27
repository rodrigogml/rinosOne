import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AvatarCropDialog from '../../../resources/js/profile/AvatarCropDialog.vue';
import { i18n } from '../../../resources/js/i18n';

describe('AvatarCropDialog', () => {
    it('offers a contained file selection state and does not expose a save action before a valid image is selected', () => {
        const wrapper = mount(AvatarCropDialog, { props: { modelValue: true }, global: { plugins: [i18n] } });

        expect(wrapper.get('.ui-dialog__title').text()).toBe('Imagem de perfil');
        expect(wrapper.get('input[type="file"]').attributes('accept')).toBe('image/jpeg,image/png,image/webp');
        expect(wrapper.text()).toContain('JPEG, PNG ou WebP');
        expect(wrapper.get('.dialog-actions .ui-button:not(.ui-button--secondary)').attributes('disabled')).toBeDefined();
    });

    it('closes without retaining a local source when canceled', async () => {
        const wrapper = mount(AvatarCropDialog, { props: { modelValue: true }, global: { plugins: [i18n] } });
        await wrapper.get('.dialog-actions .ui-button--secondary').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });
});
