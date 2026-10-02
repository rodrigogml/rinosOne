import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { describe, expect, it, vi } from 'vitest';
import DeveloperGuideApp from '../../../resources/js/developer-guide/DeveloperGuideApp.vue';
import { i18n } from '../../../resources/js/i18n';

describe('developer guide application', () => {
    it('keeps Buttons as the first guide topic and navigates to its live sections', async () => {
        const scrollIntoView = vi.fn();
        Element.prototype.scrollIntoView = scrollIntoView;
        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(DeveloperGuideApp, { attachTo: document.body, global: { plugins: [pinia, i18n] } });

        const topic = wrapper.get('.developer-guide__topic');
        expect(topic.text()).toBe('Botões');
        expect(topic.attributes('aria-current')).toBe('page');
        expect(wrapper.get('h1').text()).toBe('Botões');
        expect(wrapper.get('.ui-button--primary').text()).toBe('Salvar alterações');
        expect(wrapper.get('.ui-button--secondary').text()).toBe('Cancelar');
        expect(wrapper.get('.ui-button--destructive').text()).toBe('Excluir registro');
        expect(wrapper.findAll('.developer-guide-table .ui-button').find((button) => button.text().includes('Cancelar'))?.classes()).toContain('ui-button--secondary');
        expect(wrapper.get('#buttons-screen-commands-title').text()).toBe('Comandos padrão das telas');
        expect(wrapper.get('.developer-guide-table').text()).toContain('Alterar');
        expect(wrapper.get('.developer-guide-table-wrap + .developer-guide-table-wrap').text()).toContain('Manter seleção');
        expect(wrapper.get('.developer-guide-table-wrap + .developer-guide-table-wrap').text()).toContain('Incluir selecionados ocultos');

        await wrapper.get('.developer-guide__subtopics button').trigger('click');
        expect(scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
        wrapper.unmount();
    });

    it('opens the Associations guide with live compound controls', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(DeveloperGuideApp, { global: { plugins: [pinia, i18n] } });

        await wrapper.get('.developer-guide__topic:nth-of-type(2)').trigger('click');

        expect(wrapper.get('h1').text()).toBe('Associações');
        expect(wrapper.find('[aria-label="Busca de pessoas"] input[type="search"]').exists()).toBe(true);
        expect(wrapper.get('[aria-label="Controles de seleção"]')).toBeTruthy();
        expect(wrapper.get('[aria-label="Manter seleção"]')).toBeTruthy();
        expect(wrapper.get('select').text()).toContain('Nome');
    });
});
