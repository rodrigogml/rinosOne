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
        expect(wrapper.get('.ui-button--primary').text()).toBe('Salvar');
        expect(wrapper.get('.developer-guide-article .ui-button--secondary').text()).toBe('Inserir');
        expect(wrapper.get('.ui-button--destructive').text()).toBe('Excluir');
        expect(wrapper.get('#buttons-norms-title').text()).toBe('Normas obrigatórias');
        expect(wrapper.get('#buttons-centralizer-title').text()).toBe('Centralizador de comandos');
        expect(wrapper.text()).toContain('UIRinoButton');
        expect(wrapper.get('.developer-guide-note--mandatory').text()).toContain('Obrigatório.');
        expect(wrapper.get('.developer-guide-note--prohibited').text()).toContain('Proibido.');
        expect(wrapper.get('.developer-guide-note--exception').text()).toContain('Exceção.');
        expect(wrapper.get('#buttons-toggle-title').text()).toBe('Botões de alternância');
        expect(wrapper.findAll('.developer-guide-table .ui-button').find((button) => button.text().includes('Cancelar'))?.classes()).toContain('ui-button--secondary');
        expect(wrapper.get('#buttons-screen-commands-title').text()).toBe('Comandos padrão das telas');
        expect(wrapper.get('.developer-guide-table').text()).toContain('Alterar');
        const commandTables = wrapper.findAll('.developer-guide-table-wrap');
        expect(commandTables).toHaveLength(1);
        const table = commandTables[0]!;
        expect(table.findAll('thead th').map(header => header.text())).toEqual(['Modelo', 'command', 'variant', 'toggle', 'Escopo, sentido e restrições']);
        expect(table.findAll('tbody tr')).toHaveLength(15);
        expect(table.text()).toContain('keepSelection');
        expect(table.text()).toContain('includeHidden');
        const save = table.findAll('tbody tr').find(row => row.findAll('td')[1]?.text() === 'save')!;
        expect(save.findAll('td')[2]!.text()).toBe('primary');
        expect(save.findAll('td')[3]!.text()).toBe('—');

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
        expect(wrapper.get('#associations-norms-title').text()).toBe('Contrato obrigatório');
        expect(wrapper.get('.developer-guide-note--mandatory').text()).toContain('Obrigatório.');
        expect(wrapper.get('.developer-guide-note--prohibited').text()).toContain('Proibido.');
        expect(wrapper.get('.developer-guide-note--exception').text()).toContain('Exceção única.');
        expect(wrapper.get('[aria-label="Busca de pessoas"] input[type="search"]').attributes('placeholder')).toBe('Buscar...');
        expect(wrapper.get('[aria-label="Controles de seleção"]')).toBeTruthy();
        const keepSelection = wrapper.get('[aria-label="Manter seleção"]');
        expect(keepSelection.attributes('aria-pressed')).toBe('false');
        await keepSelection.trigger('click');
        expect(keepSelection.attributes('aria-pressed')).toBe('true');
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

    it('documents fields through the shared UiField contract', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(DeveloperGuideApp, { global: { plugins: [pinia, i18n] } });

        await wrapper.get('.developer-guide__topic:nth-of-type(5)').trigger('click');

        expect(wrapper.get('h1').text()).toBe('Campos');
        expect(wrapper.get('#fields-norms-title').text()).toBe('Normas obrigatórias');
        expect(wrapper.get('.developer-guide-note--mandatory').text()).toContain('alinhar-se pelo topo');
        expect(wrapper.get('.developer-guide-note--prohibited').text()).toContain('align-items: stretch');
        expect(wrapper.get('.developer-guide-note--exception').text()).toContain('componente compartilhado');
        expect(wrapper.get('#fields-date').attributes('type')).toBe('date');
        expect(wrapper.find('.ui-field__help').exists()).toBe(false);

        await wrapper.get('.ui-field__help-trigger').trigger('click');

        expect(wrapper.get('[role="tooltip"]').text()).toContain('Como a pessoa deve ser identificada');

        const cpf = wrapper.get('#fields-cpf');
        await cpf.setValue('52998224725');
        await cpf.trigger('blur');
        expect((cpf.element as HTMLInputElement).value).toBe('529.982.247-25');
        expect(cpf.attributes('aria-invalid')).toBe('false');

        await cpf.setValue('12345678900');
        await cpf.trigger('blur');
        expect(cpf.attributes('aria-invalid')).toBe('true');
        await wrapper.get('.ui-field__validation-trigger').trigger('click');
        expect(wrapper.get('.ui-field__validation-popover').text()).toContain('CPF inválido');
        wrapper.unmount();
    });
});
