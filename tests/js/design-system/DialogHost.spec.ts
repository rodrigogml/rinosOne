import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import DialogHost from '../../../resources/js/design-system/dialog/DialogHost.vue';
import { dialog } from '../../../resources/js/design-system/dialog/dialogService';

describe('DialogHost', () => {
    it('renders the requested model, focuses the configured button and resolves Escape through its action', async () => {
        const wrapper = mount(DialogHost, { attachTo: document.body });
        const result = dialog.open({ model: 'question', title: 'Excluir pessoa?', message: 'Esta ação não pode ser desfeita.\nDeseja continuar?', buttons: [{ id: 'cancel', label: 'Cancelar', variant: 'secondary' }, { id: 'delete', label: 'Excluir', variant: 'destructive' }], initialFocusActionId: 'cancel', escapeActionId: 'cancel' });
        await nextTick();
        await nextTick();

        expect(wrapper.get('.ui-dialog--tone-question .ui-dialog__window-header img').attributes('src')).toBe('/assets/icons/question_48.png');
        expect(wrapper.get('.dialog-host__message-region').text()).toContain('Esta ação não pode ser desfeita.\nDeseja continuar?');
        expect(document.activeElement?.getAttribute('data-dialog-action')).toBe('cancel');
        await wrapper.get('.ui-dialog').trigger('keydown', { key: 'Escape' });

        await expect(result).resolves.toEqual({ actionId: 'cancel' });
        wrapper.unmount();
    });

    it('keeps Escape blocked when no action was assigned to it', async () => {
        const wrapper = mount(DialogHost, { attachTo: document.body });
        const result = dialog.open({ model: 'error', title: 'Não foi possível concluir', message: 'Tente novamente.', buttons: [{ id: 'acknowledge', label: 'Entendi' }], initialFocusActionId: 'acknowledge' });
        await nextTick();

        await wrapper.get('.ui-dialog').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('.ui-dialog').exists()).toBe(true);
        await wrapper.get('[data-dialog-action="acknowledge"]').trigger('click');
        await expect(result).resolves.toEqual({ actionId: 'acknowledge' });
        wrapper.unmount();
    });

    it('presents queued dialogs one at a time and reapplies the next initial focus', async () => {
        const wrapper = mount(DialogHost, { attachTo: document.body });
        const first = dialog.open({ model: 'warning', title: 'Primeiro diálogo', message: 'Primeira mensagem.', buttons: [{ id: 'cancel', label: 'Cancelar', variant: 'secondary' }, { id: 'continue', label: 'Continuar' }], initialFocusActionId: 'cancel', escapeActionId: 'cancel' });
        const second = dialog.open({ model: 'error', title: 'Segundo diálogo', message: 'Segunda mensagem.', buttons: [{ id: 'acknowledge', label: 'Entendi' }], initialFocusActionId: 'acknowledge', escapeActionId: 'acknowledge' });
        await nextTick();

        expect(wrapper.get('.ui-dialog__title').text()).toBe('Primeiro diálogo');
        await wrapper.get('[data-dialog-action="continue"]').trigger('click');
        await expect(first).resolves.toEqual({ actionId: 'continue' });
        await nextTick();
        await nextTick();

        expect(wrapper.get('.ui-dialog__title').text()).toBe('Segundo diálogo');
        expect(document.activeElement?.getAttribute('data-dialog-action')).toBe('acknowledge');
        await wrapper.get('[data-dialog-action="acknowledge"]').trigger('click');
        await expect(second).resolves.toEqual({ actionId: 'acknowledge' });
        wrapper.unmount();
    });

    it('uses the prescribed title icons, selecting one of the bug icons for every request', async () => {
        const wrapper = mount(DialogHost, { attachTo: document.body });
        const result = dialog.open({ model: 'bug', title: 'Falha inesperada', message: 'Registre o contexto.', buttons: [{ id: 'acknowledge', label: 'Entendi' }], initialFocusActionId: 'acknowledge', escapeActionId: 'acknowledge' });
        await nextTick();

        const iconSource = wrapper.get('.ui-dialog__window-header img').attributes('src');
        expect(['ant', 'beetle_1', 'beetle', 'cricket', 'ladybug'].map((icon) => `/assets/icons/${icon}_48.png`)).toContain(iconSource);
        await wrapper.get('[data-dialog-action="acknowledge"]').trigger('click');
        await expect(result).resolves.toEqual({ actionId: 'acknowledge' });
        wrapper.unmount();
    });
});
