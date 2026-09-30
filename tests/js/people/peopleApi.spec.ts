import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { changePersonStatus, deletePerson, deletionUsage, duplicatePerson, loadBrazilMunicipalities, loadBrazilStates, loadCountries, loadFinancialInstitutions, lookupPostalReferences } from '../../../resources/js/people/peopleApi';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));

describe('peopleApi mutations', () => {
    beforeEach(() => vi.clearAllMocks());
    it('uses one idempotency key for each duplicate and lifecycle mutation', async () => {
        vi.mocked(axios.post).mockResolvedValue({ data: { person: { id: 3 } } });
        await duplicatePerson(18, 2, 4, { copyAddresses: true, copyContacts: false, copyBankAccounts: false, copyPixKeys: false });
        await changePersonStatus(18, 2, 4, 'inactivate');
        expect(axios.post).toHaveBeenCalledWith('/api/v1/tenants/18/people/2/duplicate', expect.objectContaining({ version: 4 }), expect.objectContaining({ headers: expect.objectContaining({ 'Idempotency-Key': expect.any(String) }) }));
        expect(axios.post).toHaveBeenCalledWith('/api/v1/tenants/18/people/2/inactivate', { version: 4 }, expect.objectContaining({ headers: expect.objectContaining({ 'Idempotency-Key': expect.any(String) }) }));
    });
    it('uses the safe diagnostic before a versioned physical deletion', async () => {
        vi.mocked(axios.get).mockResolvedValue({ data: { usages: [{ module: 'finance', description: 'Lançamento aberto' }] } }); vi.mocked(axios.delete).mockResolvedValue({ data: null });
        await expect(deletionUsage(18, 2)).resolves.toEqual([{ module: 'finance', description: 'Lançamento aberto' }]);
        await deletePerson(18, 2, 4);
        expect(axios.delete).toHaveBeenCalledWith('/api/v1/tenants/18/people/2', expect.objectContaining({ data: { version: 4 }, headers: expect.objectContaining({ 'Idempotency-Key': expect.any(String) }) }));
    });
    it('reads core references through the authorized People tenant routes', async () => {
        vi.mocked(axios.get).mockResolvedValue({ data: { countries: [{ id: 1 }], states: [{ id: 2 }], municipalities: [{ id: 3 }], financialInstitutions: [{ id: 4 }] } });
        await loadCountries(18); await loadBrazilStates(18, 1); await loadBrazilMunicipalities(18, 2); await loadFinancialInstitutions(18, 'banco');
        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/18/people/references/countries');
        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/18/people/references/countries/1/brazil-states');
        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/18/people/references/brazil-states/2/municipalities');
        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/18/people/references/financial-institutions', { params: { search: 'banco' } });
    });
    it('requests optional locality references with the selected country and postal code', async () => {
        vi.mocked(axios.post).mockResolvedValue({ data: { candidates: [{ id: 14, streetName: 'Rua das Flores' }] } });
        await expect(lookupPostalReferences('BR', '01001-000')).resolves.toEqual([{ id: 14, streetName: 'Rua das Flores' }]);
        expect(axios.post).toHaveBeenCalledWith('/api/v1/localities/postal-references/lookup', { countryCode: 'BR', postalCode: '01001-000' });
    });
});
