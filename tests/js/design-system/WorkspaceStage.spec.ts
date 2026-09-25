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

    it('renders the discovery state when no surface is active', () => {
        const wrapper = mount(WorkspaceStage, {
            props: { surface: null, emptyTitle: 'Pronta', emptyDescription: 'Abra a navegação.' },
            global: { plugins: [i18n] },
        });

        expect(wrapper.get('.workspace-stage--empty').text()).toContain('Pronta');
        expect(wrapper.find('.workspace-stage--active').exists()).toBe(false);
    });

    it('renders only the supplied active surface and exposes an unavailable state', async () => {
        const wrapper = mount(WorkspaceStage, {
            props: { surface: activeSurface, emptyTitle: 'Pronta', emptyDescription: 'Abra a navegação.' },
            global: { plugins: [i18n] },
        });

        expect(wrapper.get('.workspace-stage--active').text()).toContain('Área de trabalho');
        await wrapper.setProps({ surface: { ...activeSurface, status: 'unavailable' } });
        expect(wrapper.get('.workspace-stage--unavailable').text()).toContain('não está mais disponível');
    });
});
