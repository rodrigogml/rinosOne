import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PersonDuplicationActions from '../../../resources/js/people/PersonDuplicationActions.vue';
import { i18n } from '../../../resources/js/i18n';

vi.mock('axios', () => ({ default: { post: vi.fn() } }));

const person = { id: 2, version: 3, status: 'ACTIVE' as const, personType: 'PF' as const, displayName: 'Ana', document: '123', contactCount: 0, name: 'Ana', alias: null, cpf: '123', cnpj: null, rg: null, rgIssuer: null, pisNis: null, passportNumber: null, foreignDocumentNumber: null, birthDate: null, foundationDate: null, notes: null, addresses: [], contacts: [], bankAccounts: [], pixKeys: [], relationships: [] };

describe('PersonDuplicationActions', () => {
    beforeEach(() => vi.clearAllMocks());

    it('duplicates only the explicitly selected collections and excludes identity from the request', async () => {
        vi.mocked(axios.post).mockResolvedValue({ data: { person: { ...person, id: 3, document: null } } });
        const wrapper = mount(PersonDuplicationActions, { props: { tenantId: 18, person, capabilities: { canDuplicatePeople: true } }, global: { plugins: [i18n] } });

        await wrapper.get('button').trigger('click');
        const checks = wrapper.findAll('input[type="checkbox"]');
        await checks[0].setValue(true);
        await checks[2].setValue(true);
        await wrapper.get('[role="dialog"] footer button:last-child').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/v1/tenants/18/people/2/duplicate', {
            copyAddresses: true,
            copyContacts: false,
            copyBankAccounts: true,
            copyPixKeys: false,
            version: 3,
        }, expect.objectContaining({ headers: expect.objectContaining({ 'Idempotency-Key': expect.any(String) }) }));
        expect(wrapper.emitted('duplicated')?.[0][0]).toMatchObject({ id: 3, document: null });
    });

    it('returns focus to the trigger after cancellation', async () => {
        const wrapper = mount(PersonDuplicationActions, { attachTo: document.body, props: { tenantId: 18, person, capabilities: { canDuplicatePeople: true } }, global: { plugins: [i18n] } });
        const trigger = wrapper.get('button');
        await trigger.trigger('click');
        await wrapper.get('[role="dialog"] footer button').trigger('click');
        await flushPromises();

        expect(document.activeElement).toBe(trigger.element);
        wrapper.unmount();
    });

    it('keeps the dialog open and reports a safe error when duplication fails', async () => {
        vi.mocked(axios.post).mockRejectedValue(new Error('network detail'));
        const wrapper = mount(PersonDuplicationActions, { props: { tenantId: 18, person, capabilities: { canDuplicatePeople: true } }, global: { plugins: [i18n] } });
        await wrapper.get('button').trigger('click');
        await wrapper.get('[role="dialog"] footer button:last-child').trigger('click');
        await flushPromises();
        expect(wrapper.get('[role="dialog"] [role="alert"]').text()).toBe('Não foi possível duplicar a Pessoa agora.');
        expect(wrapper.emitted('duplicated')).toBeUndefined();
    });
});
