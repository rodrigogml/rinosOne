import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import WorkspaceShell from '../../../resources/js/design-system/WorkspaceShell.vue';
import { useWorkspaceStore } from '../../../resources/js/workspace/workspaceStore';
import type { WorkspaceDestination } from '../../../resources/js/workspace/workspaceTypes';
import { i18n } from '../../../resources/js/i18n';

describe('WorkspaceShell', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        i18n.global.locale.value = 'pt-BR';
    });

    function mountShell() {
        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(WorkspaceShell, {
            attachTo: document.body,
            global: { plugins: [pinia, i18n] },
        });

        return { wrapper, workspace: useWorkspaceStore(pinia) };
    }

    it('opens the preview menu and closes it with Escape, returning focus to its trigger', async () => {
        const { wrapper } = mountShell();
        const trigger = wrapper.get('.workspace-navigation-rail__category');

        await trigger.trigger('click');
        expect(wrapper.get('#workspace-mega-menu').text()).toContain('Painel executivo');

        await wrapper.get('.workspace-shell').trigger('keydown', { key: 'Escape' });

        expect(wrapper.find('#workspace-mega-menu').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);
        wrapper.unmount();
    });

    it('opens a category preview on hover without requiring a click', async () => {
        const { wrapper } = mountShell();
        const trigger = wrapper.findAll('.workspace-navigation-rail__category')[1]!;

        await trigger.trigger('mouseenter');

        expect(wrapper.get('#workspace-mega-menu').text()).toContain('Fluxo de caixa');
        wrapper.unmount();
    });

    it('closes navigation when the person clicks the workspace stage', async () => {
        const { wrapper } = mountShell();
        await wrapper.get('.workspace-navigation-rail__category').trigger('click');

        await wrapper.get('.workspace-stage').trigger('pointerdown');

        expect(wrapper.find('#workspace-mega-menu').exists()).toBe(false);
        wrapper.unmount();
    });

    it('keeps the mega menu inside the window area instead of the content flow', async () => {
        const { wrapper } = mountShell();
        await wrapper.get('.workspace-navigation-rail__category').trigger('click');

        expect(wrapper.find('.workspace-window-area > #workspace-mega-menu').exists()).toBe(true);
        expect(wrapper.get('.workspace-stage--empty').text()).toBe('');
        wrapper.unmount();
    });

    it('switches and closes surfaces from the taskbar and desktop shortcuts', async () => {
        const { wrapper, workspace } = mountShell();
        const destination = (id: string): WorkspaceDestination => ({
            id, scope: 'personal', category: 'workspace', titleKey: id === 'first' ? 'access.workspace.title' : 'access.workspace.navigation.title', icon: id,
            createSurface: () => ({ titleKey: id === 'first' ? 'access.workspace.title' : 'access.workspace.navigation.title', icon: id }),
        });
        const first = workspace.openDestination(destination('first'), { tenantId: null })!;
        const second = workspace.openDestination(destination('second'), { tenantId: null })!;
        await nextTick();

        expect(wrapper.findAll('[role="tab"]')).toHaveLength(2);
        await wrapper.findAll('[role="tab"]')[0]!.trigger('click');
        expect(workspace.activeSurfaceId).toBe(first.id);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', altKey: true, shiftKey: true, bubbles: true }));
        expect(workspace.activeSurfaceId).toBe(second.id);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'w', altKey: true, shiftKey: true, bubbles: true }));
        await nextTick();
        expect(workspace.surfaces.map((surface) => surface.id)).toEqual([first.id]);
        expect(workspace.activeSurfaceId).toBe(first.id);
        wrapper.unmount();
    });

    it('preserves a dirty surface after cancellation and removes only that surface after explicit discard', async () => {
        const { wrapper, workspace } = mountShell();
        const destination: WorkspaceDestination = {
            id: 'dirty', scope: 'personal', category: 'workspace', titleKey: 'access.workspace.title', icon: 'dirty',
            createSurface: () => ({ titleKey: 'access.workspace.title', icon: 'dirty', dirty: true }),
        };
        const surface = workspace.openDestination(destination, { tenantId: null })!;
        await nextTick();

        await wrapper.get('.workspace-stage__close').trigger('click');
        expect(wrapper.get('[role="alertdialog"]').text()).toContain('Alterações não salvas');

        await wrapper.get('.ui-button--secondary').trigger('click');
        expect(workspace.surfaces.map((candidate) => candidate.id)).toEqual([surface.id]);

        await wrapper.get('.workspace-stage__close').trigger('click');
        await wrapper.get('.ui-button--destructive').trigger('click');
        expect(workspace.surfaces).toEqual([]);
        wrapper.unmount();
    });

    it('keeps mobile panels mutually exclusive and closes them before a blocking confirmation', async () => {
        const { wrapper, workspace } = mountShell();
        await wrapper.setProps({ mobileNavigationOpen: true });
        expect(wrapper.find('[role="dialog"][aria-label="Navegação"]').exists()).toBe(true);

        workspace.openDestination({ id: 'dirty-mobile', scope: 'personal', category: 'workspace', titleKey: 'access.workspace.title', icon: 'dirty', createSurface: () => ({ titleKey: 'access.workspace.title', icon: 'dirty', dirty: true }) }, { tenantId: null });
        await nextTick();
        await wrapper.get('.workspace-mobile-task-trigger').trigger('click');
        expect(wrapper.findAll('[role="dialog"][aria-label="Navegação"]')).toHaveLength(0);
        expect(wrapper.find('[role="dialog"][aria-label="Superfícies abertas"]').exists()).toBe(true);

        await wrapper.get('.workspace-stage__close').trigger('click');
        expect(wrapper.findAll('[role="dialog"][aria-label="Superfícies abertas"]')).toHaveLength(0);
        expect(wrapper.find('[role="alertdialog"]').exists()).toBe(true);
        wrapper.unmount();
    });
});
