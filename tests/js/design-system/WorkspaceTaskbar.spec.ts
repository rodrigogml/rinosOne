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

    it('identifies the active surface semantically with icon-only items and emits activation', async () => {
        const wrapper = mount(WorkspaceTaskbar, {
            props: { surfaces, activeSurfaceId: 'surface-1', label: 'Superfícies abertas', dirtyLabel: 'Alterações não salvas' },
            global: { plugins: [i18n] },
        });

        const tabs = wrapper.findAll('[role="tab"]');
        expect(tabs[0]!.attributes('aria-selected')).toBe('true');
        expect(tabs[1]!.attributes('aria-selected')).toBe('false');
        expect(wrapper.get('.workspace-taskbar__dirty').attributes('aria-label')).toBe('Alterações não salvas');
        expect(wrapper.findAll('.workspace-surface-icon')).toHaveLength(2);
        expect(wrapper.findAll('.workspace-taskbar__active-pill')).toHaveLength(1);
        expect(wrapper.find('.workspace-taskbar__title').exists()).toBe(false);

        await tabs[1]!.trigger('click');

        expect(wrapper.emitted('activate')).toEqual([['surface-2']]);
    });

    it('uses the user-settings raster in the taskbar when that surface is open', () => {
        const wrapper = mount(WorkspaceTaskbar, {
            props: {
                surfaces: [{ ...surfaces[0]!, icon: 'rinoUser-tweek', destinationId: 'personal.settings' }],
                activeSurfaceId: 'surface-1', label: 'Superfícies abertas', dirtyLabel: 'Alterações não salvas',
            },
            global: { plugins: [i18n] },
        });

        expect(wrapper.get('.workspace-taskbar__activate img').attributes('src')).toBe('/assets/icons/rinoUser-tweek_48.png');
    });
});
