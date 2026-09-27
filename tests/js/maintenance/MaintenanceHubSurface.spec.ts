import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MaintenanceHubSurface from '../../../resources/js/maintenance/MaintenanceHubSurface.vue';
import { i18n } from '../../../resources/js/i18n';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), isAxiosError: vi.fn(() => false) } }));

const routine = {
    routineKey: 'financial-institution-catalog', title: 'Instituições financeiras', description: 'Catálogo oficial.', state: 'READY', scheduleDescription: 'Diariamente', capabilities: { canSynchronize: true },
    lastExecution: { state: 'SUCCEEDED', triggerType: 'SCHEDULED', startedAt: '2026-09-26T10:00:00Z', completedAt: '2026-09-26T10:01:00Z', summary: 'Concluída.', createdCount: 1 as number | null, updatedCount: 2 as number | null },
    executionHistory: [{ state: 'SUCCEEDED', triggerType: 'SCHEDULED', startedAt: '2026-09-26T10:00:00Z', completedAt: '2026-09-26T10:01:00Z', summary: 'Concluída.', createdCount: 1 as number | null, updatedCount: 2 as number | null }],
    administrativeAudits: [{ performedByUserId: 1, action: 'SYNCHRONIZE', outcome: 'ACCEPTED', occurredAt: '2026-09-26T10:00:00Z' }],
};

describe('MaintenanceHubSurface', () => {
    beforeEach(() => { vi.clearAllMocks(); i18n.global.locale.value = 'pt-BR'; });

    function mountSurface(data = routine) {
        vi.mocked(axios.get).mockImplementation((url: string) => Promise.resolve({ data: url.endsWith('/routines') ? { routines: [data] } : { routine: data } }));
        return mount(MaintenanceHubSurface, { attachTo: document.body, global: { plugins: [i18n] } });
    }

    it('loads the authorized routine, its history and administrative audit from the API', async () => {
        const wrapper = mountSurface();
        await flushPromises();

        expect(wrapper.text()).toContain('Instituições financeiras');
        expect(wrapper.text()).toContain('Histórico técnico');
        expect(wrapper.text()).toContain('SYNCHRONIZE');
        expect(axios.get).toHaveBeenCalledWith('/api/v1/platform/maintenance/routines');
        expect(wrapper.get('nav button').attributes('aria-current')).toBe('page');
        wrapper.unmount();
    });

    it('requires confirmation before requesting a manual update and refreshes the routine afterwards', async () => {
        const wrapper = mountSurface();
        await flushPromises();
        vi.mocked(axios.post).mockResolvedValue({ data: { execution: { state: 'SUCCEEDED', createdCount: 1, updatedCount: 2 } } });

        await wrapper.get('[data-testid="maintenance-synchronize"]').trigger('click');
        expect(axios.post).not.toHaveBeenCalled();
        expect(wrapper.get('[role="dialog"]').text()).toContain('Banco Central');

        await wrapper.get('[role="dialog"] .ui-button:not(.ui-button--secondary)').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/api/v1/platform/maintenance/routines/financial-institution-catalog/actions/SYNCHRONIZE');
        expect(wrapper.text()).toContain('Atualização concluída: 1 criadas; 2 atualizadas.');
        wrapper.unmount();
    });

    it('localizes a failed technical synchronization without exposing its infrastructure error', async () => {
        const failedRoutine = {
            ...routine,
            lastExecution: { ...routine.lastExecution, state: 'FAILED', summary: 'The official source could not be synchronized.', createdCount: 0, updatedCount: 0 },
            executionHistory: [{ ...routine.executionHistory[0], state: 'FAILED', summary: 'The official source could not be synchronized.', createdCount: 0, updatedCount: 0 }],
        };
        const wrapper = mountSurface(failedRoutine);
        await flushPromises();

        expect(wrapper.text()).toContain('Não foi possível sincronizar a fonte oficial do Banco Central.');
        expect(wrapper.text()).not.toContain('The official source could not be synchronized.');
        wrapper.unmount();
    });

    it('preserves loaded data and identifies it as stale when a later reload fails', async () => {
        const wrapper = mountSurface();
        await flushPromises();
        vi.mocked(axios.get).mockRejectedValueOnce(new Error('unavailable'));

        await wrapper.get('.maintenance-hub__header .ui-button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Os dados exibidos podem estar desatualizados.');
        expect(wrapper.text()).toContain('Instituições financeiras');
        wrapper.unmount();
    });
});
