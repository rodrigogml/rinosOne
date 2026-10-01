import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DriveTransferDialog from '../../../resources/js/drive/DriveTransferDialog.vue';
import { i18n } from '../../../resources/js/i18n';

function dialog(sameDrive: boolean) {
    return mount(DriveTransferDialog, { props: { modelValue: true, sourceLabel: 'Meu Drive', destinationLabel: 'Financeiro', itemCount: 2, sameDrive }, global: { plugins: [i18n] } });
}

describe('DriveTransferDialog', () => {
    it('defaults to moving inside the same drive and emits the confirmed mode', async () => {
        const wrapper = dialog(true);
        expect((wrapper.get('input[value="MOVE"]').element as HTMLInputElement).checked).toBe(true);
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('confirm')).toEqual([['MOVE']]);
    });

    it('defaults to copying between drives and keeps cancellation local to the window', async () => {
        const wrapper = dialog(false);
        expect((wrapper.get('input[value="COPY"]').element as HTMLInputElement).checked).toBe(true);
        await wrapper.find('button').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });
});
