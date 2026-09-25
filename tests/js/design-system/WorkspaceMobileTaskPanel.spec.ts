import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import WorkspaceMobileTaskPanel from '../../../resources/js/design-system/WorkspaceMobileTaskPanel.vue';
import type { WorkspaceSurface } from '../../../resources/js/workspace/workspaceTypes';
import { i18n } from '../../../resources/js/i18n';

const surfaces: WorkspaceSurface[] = [
    { id: 'one', destinationId: 'one', scope: 'personal', tenantId: null, titleKey: 'access.workspace.title', icon: 'one', dirty: false, status: 'active' },
    { id: 'two', destinationId: 'two', scope: 'personal', tenantId: null, titleKey: 'access.workspace.navigation.title', icon: 'two', dirty: true, status: 'open' },
];

describe('WorkspaceMobileTaskPanel', () => {
    beforeEach(() => { i18n.global.locale.value = 'pt-BR'; });

    it('uses a focusable modal list to activate or request close for a surface', async () => {
        const wrapper = mount(WorkspaceMobileTaskPanel, {
            attachTo: document.body,
            props: { modelValue: true, surfaces, activeSurfaceId: 'one', title: 'Superfícies abertas', closeLabel: 'Fechar', emptyLabel: 'Vazia', closeSurfaceLabel: 'Fechar superfície', dirtyLabel: 'Alterações não salvas' },
            global: { plugins: [i18n] },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[role="dialog"]').attributes('aria-modal')).toBe('true');
        expect(wrapper.get('[aria-current="page"]').text()).toContain('Área de trabalho');
        await wrapper.get('[aria-label="Navegação"]').trigger('click');
        await wrapper.get('[aria-label="Fechar superfície: Área de trabalho"]').trigger('click');

        expect(wrapper.emitted('activate')).toEqual([['two']]);
        expect(wrapper.emitted('update:modelValue')).toContainEqual([false]);
        expect(wrapper.emitted('requestClose')).toEqual([['one']]);
        wrapper.unmount();
    });
});
