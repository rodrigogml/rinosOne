import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia } from 'pinia';
import WorkspaceStage from '../../../resources/js/design-system/WorkspaceStage.vue';
import AuthorizationAdministrationSurface from '../../../resources/js/authorization/AuthorizationAdministrationSurface.vue';
import CalendarOccasionWorkspace from '../../../resources/js/calendar-occasions/CalendarOccasionWorkspace.vue';
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
            global: { plugins: [createPinia(), i18n] },
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

    it('uses the user-settings raster in the settings window header', () => {
        const wrapper = mount(WorkspaceStage, {
            props: { surface: { ...activeSurface, destinationId: 'personal.settings', icon: 'rinoUser-tweek' } },
            global: { plugins: [createPinia(), i18n] },
        });

        expect(wrapper.get('.workspace-stage__header img').attributes('src')).toBe('/assets/icons/rinoUser-tweek_48.png');
    });

    it('mounts the holiday management workspace for the central-table destination', () => {
        const wrapper = mount(WorkspaceStage, {
            props: { surface: { ...activeSurface, destinationId: 'platform.calendar-occasions', label: 'Feriados', icon: 'holiday' } },
            global: { plugins: [createPinia(), i18n] },
        });

        expect(wrapper.findComponent(CalendarOccasionWorkspace).exists()).toBe(true);
        expect(wrapper.text()).toContain('Nenhuma definição encontrada.');
        expect(wrapper.get('.workspace-stage__header img').attributes('src')).toBe('/assets/icons/holiday_48.png');
    });

    it('returns safely to the workspace when the authorization surface loses access', async () => {
        const wrapper = mount(WorkspaceStage, {
            props: { surface: { ...activeSurface, destinationId: 'tenant.authorization-administration', scope: 'tenant', tenantId: 7 } },
            global: { plugins: [createPinia(), i18n] },
        });

        wrapper.findComponent(AuthorizationAdministrationSurface).vm.$emit('accessDenied');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('requestClose')).toEqual([['surface-1']]);
    });

});
