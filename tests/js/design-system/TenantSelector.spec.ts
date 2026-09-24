import axios from 'axios';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TenantSelector from '../../../resources/js/design-system/TenantSelector.vue';
import { i18n } from '../../../resources/js/i18n';
import { useTenantContextStore } from '../../../resources/js/tenant/tenantContextStore';

vi.mock('axios', () => ({ default: { delete: vi.fn(), get: vi.fn(), post: vi.fn() } }));

const http = axios as unknown as { delete: ReturnType<typeof vi.fn>; get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };
const tenant = { id: '01J00000000000000000000000', displayName: 'Oficina Rubi', state: 'ACTIVE', selectable: true, role: 'OWNER' };
const context = { tenant: { id: tenant.id, displayName: tenant.displayName }, membership: { id: '01J00000000000000000000001', role: 'OWNER' }, availableModules: [] };

function mountSelector() {
    const pinia = createPinia();
    setActivePinia(pinia);

    return mount(TenantSelector, { global: { plugins: [pinia, i18n] }, attachTo: document.body });
}

describe('tenant selector', () => {
    beforeEach(() => { vi.resetAllMocks(); i18n.global.locale.value = 'pt-BR'; document.body.replaceChildren(); });

    it('presents a neutral tenant avatar and loads only selectable organizations when opened', async () => {
        http.get.mockResolvedValue({ data: { tenants: [tenant, { ...tenant, id: '01J00000000000000000000002', state: 'INACTIVE', selectable: false }] } });
        const wrapper = mountSelector();

        expect(wrapper.get('button[aria-label="Selecionar organização"] [role="img"]').text()).toBe('?');
        await wrapper.get('button[aria-label="Selecionar organização"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenCalledWith('/api/v1/tenants');
        expect(wrapper.text()).toContain('Oficina Rubi');
        expect(wrapper.text()).not.toContain('INACTIVE');
        wrapper.unmount();
    });

    it('keeps the prior context when selection is denied and announces a safe failure', async () => {
        const wrapper = mountSelector();
        useTenantContextStore().$patch({});
        http.get.mockResolvedValue({ data: { tenants: [tenant] } });
        await wrapper.get('button[aria-label="Selecionar organização"]').trigger('click');
        await flushPromises();
        http.post.mockRejectedValue(new Error('not available'));

        await wrapper.get('.tenant-selector__item').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Não foi possível selecionar esta organização.');
        expect(useTenantContextStore().context).toBeNull();
        wrapper.unmount();
    });

    it('changes and ends the current tab context through the validated endpoints', async () => {
        http.get.mockResolvedValue({ data: { tenants: [tenant] } });
        http.post.mockResolvedValue({ data: { context } });
        const wrapper = mountSelector();
        await wrapper.get('button[aria-label="Selecionar organização"]').trigger('click');
        await flushPromises();
        await wrapper.get('.tenant-selector__item').trigger('click');
        await flushPromises();

        expect(useTenantContextStore().context?.tenant.displayName).toBe('Oficina Rubi');
        expect(wrapper.emitted('changed')).toEqual([['Oficina Rubi']]);

        http.delete.mockResolvedValue({ status: 204 });
        await wrapper.get('button[aria-label="Organização atual: Oficina Rubi"]').trigger('click');
        await flushPromises();
        await wrapper.get('.tenant-selector__item').trigger('click');
        await flushPromises();

        expect(useTenantContextStore().context).toBeNull();
        expect(wrapper.emitted('changed')).toEqual([['Oficina Rubi'], [null]]);
        wrapper.unmount();
    });

    it('uses a focus-trapped modal sheet on a phone-sized viewport', async () => {
        Object.defineProperty(window, 'matchMedia', { configurable: true, value: vi.fn(() => ({ matches: true })) });
        http.get.mockResolvedValue({ data: { tenants: [] } });
        const wrapper = mountSelector();
        const opener = wrapper.get('button[aria-label="Selecionar organização"]');

        (opener.element as HTMLButtonElement).focus();
        await opener.trigger('click');
        await flushPromises();

        const dialog = wrapper.get('[role="dialog"]');
        expect(dialog.attributes('aria-modal')).toBe('true');
        await dialog.trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(document.activeElement).toBe(opener.element);
        wrapper.unmount();
        Object.defineProperty(window, 'matchMedia', { configurable: true, value: undefined });
    });
});
