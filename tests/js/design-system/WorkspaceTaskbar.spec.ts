import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import WorkspaceTaskbar from '../../../resources/js/design-system/WorkspaceTaskbar.vue';
import type { WorkspaceSurface } from '../../../resources/js/workspace/workspaceTypes';
import { i18n } from '../../../resources/js/i18n';

const surfaces: WorkspaceSurface[] = [
    { id: 'surface-1', destinationId: 'personal.first', scope: 'personal', tenantId: null, titleKey: 'access.workspace.title', icon: 'first', dirty: false, status: 'active' },
    { id: 'surface-2', destinationId: 'personal.second', scope: 'personal', tenantId: null, titleKey: 'access.workspace.navigation.title', icon: 'second', dirty: true, status: 'open' },
];

describe('WorkspaceTaskbar', () => {
    beforeEach(() => { i18n.global.locale.value = 'pt-BR'; });

    it('identifies the active surface semantically and emits activation and explicit close requests', async () => {
        const wrapper = mount(WorkspaceTaskbar, {
            props: { surfaces, activeSurfaceId: 'surface-1', label: 'Superfícies abertas', closeLabel: 'Fechar', dirtyLabel: 'Alterações não salvas' },
            global: { plugins: [i18n] },
        });

        const tabs = wrapper.findAll('[role="tab"]');
        expect(tabs[0]!.attributes('aria-selected')).toBe('true');
        expect(tabs[1]!.attributes('aria-selected')).toBe('false');
        expect(wrapper.get('.workspace-taskbar__dirty').attributes('aria-label')).toBe('Alterações não salvas');

        await tabs[1]!.trigger('click');
        await wrapper.get('[aria-label="Fechar: Área de trabalho"]').trigger('click');

        expect(wrapper.emitted('activate')).toEqual([['surface-2']]);
        expect(wrapper.emitted('requestClose')).toEqual([['surface-1']]);
    });
});
