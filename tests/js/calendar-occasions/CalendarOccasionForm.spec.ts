import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CalendarOccasionForm from '../../../resources/js/calendar-occasions/CalendarOccasionForm.vue';

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), isAxiosError: vi.fn(() => false) },
}));

describe('CalendarOccasionForm', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(axios.get).mockResolvedValue({ data: { countries: [{ id: 1, name: 'Brasil' }] } });
    });

    async function fillAnnualFixedDate(wrapper: ReturnType<typeof mount>): Promise<void> {
        await wrapper.get('input').setValue('Natal');
        await wrapper.findAll('select')[1].setValue('COUNTRY');
        await flushPromises();
        const selects = wrapper.findAll('select');
        await selects[2].setValue('1');
        await flushPromises();
        await selects[4].setValue('25');
        await selects[5].setValue('12');
        await flushPromises();
    }

    it('does not submit residual recurrence parameters for an annual fixed date', async () => {
        vi.mocked(axios.post).mockResolvedValue({ data: { calendarOccasion: { id: 1 } } });
        const wrapper = mount(CalendarOccasionForm, { props: { occasionId: null } });
        await flushPromises();
        await fillAnnualFixedDate(wrapper);
        wrapper.findAll('[required]').forEach((field) => field.element.removeAttribute('required'));
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith(
            '/api/v1/platform/calendar-occasions',
            expect.objectContaining({
                recurrence: expect.objectContaining({
                    type: 'ANNUAL_FIXED_DATE',
                    oneTimeDate: null,
                    weekMonth: null,
                    weekOrdinal: null,
                    weekDay: null,
                    easterOffsetDays: null,
                }),
            }),
        );
    });

    it('identifies a server validation error by the affected field name', async () => {
        vi.mocked(axios.post).mockRejectedValue({
            response: { data: { error: { message: 'Há campos a corrigir.', fields: { 'recurrence.fixedDay': ['O dia informado não existe no mês selecionado.'] } } } },
        });
        vi.mocked(axios.isAxiosError).mockReturnValue(true);
        const wrapper = mount(CalendarOccasionForm, { props: { occasionId: null } });
        await flushPromises();
        await fillAnnualFixedDate(wrapper);
        wrapper.findAll('[required]').forEach((field) => field.element.removeAttribute('required'));
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Dia da data fixa:');
        expect(wrapper.text()).toContain('O dia informado não existe no mês selecionado.');
    });
});
