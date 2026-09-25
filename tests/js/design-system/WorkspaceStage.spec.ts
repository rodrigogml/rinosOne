import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import WorkspaceStage from '../../../resources/js/design-system/WorkspaceStage.vue';
import type { WorkspaceSurface } from '../../../resources/js/workspace/workspaceTypes';
import { i18n } from '../../../resources/js/i18n';

const activeSurface: WorkspaceSurface = {
    id: 'surface-1', destinationId: 'personal.sample', scope: 'personal', tenantId: null,
    titleKey: 'access.workspace.title', icon: 'sample', dirty: false, status: 'active',
};

describe('WorkspaceStage', () => {
    beforeEach(() => { i18n.global.locale.value = 'pt-BR'; });

    it('renders a neutral window area when no surface is active', () => {
        const wrapper = mount(WorkspaceStage, {
            props: { surface: null },
            global: { plugins: [i18n] },
        });

        expect(wrapper.get('.workspace-stage--empty').attributes('aria-label')).toBe('Área de janelas');
        expect(wrapper.get('.workspace-stage--empty').text()).toBe('');
        expect(wrapper.find('.workspace-stage--active').exists()).toBe(false);
    });

    it('renders only the supplied active surface and exposes an unavailable state', async () => {
        const wrapper = mount(WorkspaceStage, {
            props: { surface: activeSurface },
            global: { plugins: [i18n] },
        });

        expect(wrapper.get('.workspace-stage--active').text()).toContain('Área de trabalho');
        expect(wrapper.find('.workspace-stage__close').exists()).toBe(true);
        await wrapper.setProps({ surface: { ...activeSurface, status: 'unavailable' } });
        expect(wrapper.get('.workspace-stage--unavailable').text()).toContain('não está mais disponível');
    });

    it('requests closing from the active window header', async () => {
        const wrapper = mount(WorkspaceStage, { props: { surface: activeSurface }, global: { plugins: [i18n] } });

        await wrapper.get('[aria-label="Fechar: Área de trabalho"]').trigger('click');

        expect(wrapper.emitted('requestClose')).toEqual([['surface-1']]);
    });

    it('contains and stacks local dialogs inside the active window', async () => {
        const wrapper = mount(WorkspaceStage, {
            props: { surface: { ...activeSurface, label: 'Fluxo de caixa' } },
            global: { plugins: [i18n] },
        });

        await wrapper.get('button:nth-of-type(3)').trigger('click');

        expect(wrapper.find('.workspace-stage__surface-content .ui-dialog-backdrop--contained').exists()).toBe(true);
        expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Diálogo desta janela');
        await wrapper.get('[role="dialog"] button').trigger('click');
        expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Detalhe do diálogo');
        await wrapper.get('[role="dialog"] button:last-child').trigger('click');
        expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Diálogo desta janela');
        await wrapper.get('[role="dialog"] button:last-child').trigger('click');
        expect(wrapper.find('.workspace-stage__surface-content .ui-dialog-backdrop').exists()).toBe(false);
    });

    it('preserves local dialogs in their own window instance while another window is active', async () => {
        const contactsSurface = { ...activeSurface, id: 'surface-2', label: 'Contatos' };
        const wrapper = mount(WorkspaceStage, {
            props: { surface: { ...activeSurface, label: 'Fluxo de caixa' }, surfaces: [{ ...activeSurface, label: 'Fluxo de caixa' }, contactsSurface] },
            global: { plugins: [i18n] },
        });

        await wrapper.get('button:nth-of-type(3)').trigger('click');
        expect(wrapper.find('.ui-dialog-backdrop--contained').exists()).toBe(true);

        await wrapper.setProps({ surface: contactsSurface });

        expect(wrapper.find('.ui-dialog-backdrop--contained').exists()).toBe(true);
        expect(wrapper.findAll('.workspace-stage__surface-instance')[0]!.attributes('style')).toContain('display: none');
        expect(wrapper.get('.workspace-stage__header').text()).toContain('Contatos');

        await wrapper.setProps({ surface: { ...activeSurface, label: 'Fluxo de caixa' } });

        expect(wrapper.findAll('.workspace-stage__surface-instance')[0]!.attributes('style')).not.toContain('display: none');
        expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Diálogo desta janela');
    });
});
