import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AuthorizationAdministrationSurface from '../../../resources/js/authorization/AuthorizationAdministrationSurface.vue';
import { i18n } from '../../../resources/js/i18n';

vi.mock('axios', () => ({ default: { delete: vi.fn(), get: vi.fn(), post: vi.fn(), isAxiosError: vi.fn(() => false) } }));

const surface = { id: 'authorization-1', destinationId: 'tenant.authorization-administration', scope: 'tenant' as const, tenantId: 18, titleKey: 'access.authorization.title', label: 'Segurança', icon: 'settings', dirty: false, status: 'active' as const };

describe('AuthorizationAdministrationSurface', () => {
    beforeEach(() => { vi.clearAllMocks(); vi.mocked(axios.get).mockResolvedValue({ data: { events: [] } }); i18n.global.locale.value = 'pt-BR'; });

    it('loads the authorized audit and creates a role through the tenant-scoped API', async () => {
        const wrapper = mount(AuthorizationAdministrationSurface, { props: { surface }, global: { plugins: [i18n] } });
        await flushPromises();
        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/18/authorization/audit-events?perPage=25&page=1');

        await wrapper.get('#authorization-role-key').setValue('tenant.billing.viewer');
        await wrapper.get('#authorization-role-name').setValue('Billing viewer');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/api/v1/tenants/18/authorization/roles', { key: 'tenant.billing.viewer', displayName: 'Billing viewer', description: '' });
        expect(wrapper.text()).toContain('Role criada com sucesso.');
        wrapper.unmount();
    });

    it('uses accessible tabs and queries effective access without treating the client as authority', async () => {
        const wrapper = mount(AuthorizationAdministrationSurface, { props: { surface }, global: { plugins: [i18n] } });
        await flushPromises();
        await wrapper.findAll('[role="tab"]')[2]!.trigger('click');
        await wrapper.get('#authorization-subject').setValue('44');
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { effectiveAccess: { membership: { id: 2, state: 'ACTIVE' } } } });
        await wrapper.get('.authorization-administration__form .ui-button').trigger('click');
        await flushPromises();
        expect(axios.get).toHaveBeenLastCalledWith('/api/v1/tenants/18/authorization/effective-access/users/44');
        expect(wrapper.text()).toContain('ACTIVE');
        wrapper.unmount();
    });

    it('confirms a membership removal and retains the form when the last administrator invariant refuses it', async () => {
        const wrapper = mount(AuthorizationAdministrationSurface, { props: { surface }, global: { plugins: [i18n] } });
        await flushPromises();
        await wrapper.findAll('[role="tab"]')[2]!.trigger('click');
        await wrapper.get('#authorization-membership').setValue('91');
        await wrapper.get('.authorization-administration__form form').trigger('submit');
        expect(wrapper.get('[role="dialog"]').text()).toContain('Confirme para continuar');

        const failure = { response: { data: { error: { code: 'AUTHORIZATION_ADMINISTRATION_CONFLICT' } } } };
        vi.mocked(axios.isAxiosError).mockImplementation((reason: unknown) => reason === failure);
        vi.mocked(axios.delete).mockRejectedValue(failure);
        await wrapper.get('[role="dialog"] .ui-button--destructive').trigger('click');
        await flushPromises();
        expect(axios.delete).toHaveBeenCalledWith('/api/v1/tenants/18/authorization/memberships/91');
        expect(wrapper.text()).toContain('o tenant precisa manter ao menos um administrador');
        expect((wrapper.get('#authorization-membership').element as HTMLInputElement).value).toBe('91');
        wrapper.unmount();
    });
});
