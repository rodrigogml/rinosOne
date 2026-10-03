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
        expect(wrapper.get('#buttons-toggle-title').text()).toBe('Botões de alternância');
        expect(wrapper.findAll('.developer-guide-table .ui-button').find((button) => button.text().includes('Cancelar'))?.classes()).toContain('ui-button--secondary');
        expect(wrapper.get('#buttons-screen-commands-title').text()).toBe('Comandos padrão das telas');
        expect(wrapper.get('.developer-guide-table').text()).toContain('Alterar');
        expect(wrapper.get('.developer-guide-table-wrap + .developer-guide-table-wrap').text()).toContain('Manter seleção');
        expect(wrapper.get('.developer-guide-table-wrap + .developer-guide-table-wrap').text()).toContain('Incluir selecionados ocultos');

        await wrapper.get('.developer-guide__subtopics button').trigger('click');
        expect(scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
        wrapper.unmount();
    });

    it('exposes the persistent state of compact and text toggle buttons', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(DeveloperGuideApp, { global: { plugins: [pinia, i18n] } });

        const compactToggle = wrapper.get('.developer-guide-toggle-demo [aria-label="Manter seleção"]');
        const textToggle = wrapper.get('.developer-guide-toggle-demo .ui-button');

        expect(compactToggle.attributes('aria-pressed')).toBe('false');
        expect(textToggle.attributes('aria-pressed')).toBe('false');

        await compactToggle.trigger('click');
        await textToggle.trigger('click');

        expect(compactToggle.attributes('aria-pressed')).toBe('true');
        expect(textToggle.attributes('aria-pressed')).toBe('true');
        expect(wrapper.text()).toContain('Manter seleção: ligado.');
        expect(wrapper.text()).toContain('Exibir selecionados: ligado.');
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

    it('opens Toast messages and emits its live examples through the central host', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(DeveloperGuideApp, { attachTo: document.body, global: { plugins: [pinia, i18n] } });

        await wrapper.get('.developer-guide__topic:nth-of-type(3)').trigger('click');
        await wrapper.get('.developer-guide-showcase .ui-button').trigger('click');

        expect(wrapper.get('h1').text()).toBe('Toast messages');
        expect(wrapper.get('.toast-host__toast--success').text()).toContain('Pessoa salva com sucesso.');
        wrapper.unmount();
    });

    it('opens the Dialogs guide and presents its visual models through the central host', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(DeveloperGuideApp, { attachTo: document.body, global: { plugins: [pinia, i18n] } });

        await wrapper.get('.developer-guide__topic:nth-of-type(4)').trigger('click');
        await wrapper.get('.developer-guide-table .ui-button').trigger('click');

        expect(wrapper.get('h1').text()).toBe('Caixas de diálogo');
        expect(wrapper.get('.dialog-host__message-region').text()).toContain('alterações pendentes');
        wrapper.unmount();
    });
});
