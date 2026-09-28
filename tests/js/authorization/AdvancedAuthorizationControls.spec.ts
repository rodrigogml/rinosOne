import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdvancedAuthorizationControls from '../../../resources/js/authorization/AdvancedAuthorizationControls.vue';
import { i18n } from '../../../resources/js/i18n';

vi.mock('axios', () => ({ default: { post: vi.fn() } }));

const surface = { id: 'authorization-1', destinationId: 'tenant.authorization-administration', scope: 'tenant' as const, tenantId: 18, titleKey: 'access.authorization.title', label: 'Segurança', icon: 'settings', dirty: false, status: 'active' as const };

describe('AdvancedAuthorizationControls', () => {
    beforeEach(() => { vi.clearAllMocks(); i18n.global.locale.value = 'pt-BR'; });

    it('publishes a closed amount policy and binding through the tenant-scoped API', async () => {
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { policy: { id: 41 } } }).mockResolvedValueOnce({ data: {} });
        const wrapper = mount(AdvancedAuthorizationControls, { props: { surface }, global: { plugins: [i18n] } });

        await wrapper.get('#advanced-policy-key').setValue('invoice-amount-limit');
        await wrapper.get('#advanced-policy-permission').setValue('12');
        await wrapper.get('#advanced-policy-maximum').setValue('100');
        await wrapper.findAll('form')[0]!.trigger('submit');
        await flushPromises();

        expect(axios.post).toHaveBeenNthCalledWith(1, '/api/v1/tenants/18/authorization/advanced/policies', { key: 'invoice-amount-limit', definition: { all: [{ type: 'AMOUNT_MAXIMUM', maximum: 100 }] } });
        expect(axios.post).toHaveBeenNthCalledWith(2, '/api/v1/tenants/18/authorization/advanced/policies/41/bindings', { permissionId: 12 });
        expect(wrapper.text()).toContain('Política publicada e vinculada.');
        wrapper.unmount();
    });

    it('shows a newly issued API key only after the credential endpoint returns it', async () => {
        vi.mocked(axios.post).mockResolvedValue({ data: { apiKey: 'rinos_public_secret' } });
        const wrapper = mount(AdvancedAuthorizationControls, { props: { surface }, global: { plugins: [i18n] } });

        await wrapper.get('#advanced-identity-id').setValue('7');
        await wrapper.get('#advanced-credential-name').setValue('importador');
        await wrapper.findAll('form')[4]!.trigger('submit');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/v1/tenants/18/authorization/advanced/service-identities/7/credentials', { displayName: 'importador' });
        expect(wrapper.text()).toContain('rinos_public_secret');
        expect(wrapper.text()).toContain('ela não será exibida novamente');
        wrapper.unmount();
    });
});
