import axios from 'axios';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TenantManagementDialog from '../../../resources/js/design-system/TenantManagementDialog.vue';
import { i18n } from '../../../resources/js/i18n';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const http = axios as unknown as { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };
const activeTenant = { id: 1, displayName: 'Oficina Rubi', state: 'ACTIVE', selectable: true, role: 'OWNER' };

function mountDialog() {
    return mount(TenantManagementDialog, {
        props: { modelValue: true, 'onUpdate:modelValue': vi.fn() },
        global: { plugins: [createPinia(), i18n] },
        attachTo: document.body,
    });
}

describe('tenant management dialog', () => {
    beforeEach(() => { vi.resetAllMocks(); i18n.global.locale.value = 'pt-BR'; document.body.replaceChildren(); });

    it('creates a single intent with a client-generated UUID and presents its preparation state', async () => {
        http.get.mockResolvedValue({ data: { tenants: [] } });
        http.post.mockResolvedValue({ data: { tenant: { id: 1, displayName: 'Oficina Rubi', state: 'PROVISIONING', selectable: false }, provisioning: { id: 1, state: 'QUEUED' } } });
        const wrapper = mountDialog();
        await flushPromises();
        await wrapper.get('#tenant-display-name').setValue('Oficina Rubi');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/api/v1/tenants', { displayName: 'Oficina Rubi' }, { headers: { 'Idempotency-Key': expect.stringMatching(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i) } });
        expect(wrapper.text()).toContain('A organização está sendo preparada.');
        expect(wrapper.text()).toContain('Preparando');
        wrapper.unmount();
    });

    it('shows owner-only availability actions and applies the returned state', async () => {
        http.get.mockResolvedValue({ data: { tenants: [activeTenant] } });
        http.post.mockResolvedValue({ data: { tenant: { ...activeTenant, state: 'INACTIVE', selectable: false } } });
        const wrapper = mountDialog();
        await flushPromises();
        await wrapper.get('button.ui-button--secondary').trigger('click');
        expect(wrapper.get('[role="alertdialog"]').text()).toContain('Desabilitar organização?');
        await wrapper.get('[role="alertdialog"] button.ui-button--destructive').trigger('click');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/api/v1/tenants/1/availability', { state: 'INACTIVE' });
        expect(wrapper.text()).toContain('Desabilitada');
        wrapper.unmount();
    });

    it('keeps form input visible for validation and disables creation while offline', async () => {
        http.get.mockResolvedValue({ data: { tenants: [] } });
        const wrapper = mountDialog();
        await flushPromises();
        await wrapper.get('form').trigger('submit');
        expect(wrapper.text()).toContain('Informe o nome da organização.');

        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: false });
        window.dispatchEvent(new Event('offline'));
        await wrapper.vm.$nextTick();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
        expect(http.post).not.toHaveBeenCalled();
        wrapper.unmount();
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
    });
});
