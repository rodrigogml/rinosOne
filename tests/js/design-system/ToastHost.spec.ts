import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ToastHost from '../../../resources/js/design-system/toast/ToastHost.vue';
import { toast } from '../../../resources/js/design-system/toast/toastService';

describe('ToastHost', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('dismisses the current toast immediately when the toast itself is clicked', async () => {
        vi.useFakeTimers();
        const wrapper = mount(ToastHost, { attachTo: document.body, props: { minimumVisibleMs: 10_000 } });

        toast.info('A importação terminou.');
        await nextTick();
        await wrapper.get('.toast-host__toast--info').trigger('click');
        await nextTick();

        expect(wrapper.find('.toast-host__toast--info').exists()).toBe(false);
        wrapper.unmount();
    });

    it('mounts the queued toast with its complete content before it is promoted', async () => {
        const wrapper = mount(ToastHost, { attachTo: document.body });

        toast.success('Cadastro salvo com sucesso.');
        toast.success('Permissões atualizadas com sucesso.');
        await nextTick();

        const peek = wrapper.get('.toast-host__peek--success');
        expect(peek.text()).toContain('Permissões atualizadas com sucesso.');
        expect(peek.find('.toast-host__icon').exists()).toBe(true);
        wrapper.unmount();
    });
});
