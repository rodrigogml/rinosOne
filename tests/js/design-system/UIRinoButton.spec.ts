import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, ref } from 'vue';
import { afterEach, describe, expect, it } from 'vitest';
import UIRinoButton from '../../../resources/js/design-system/UIRinoButton.vue';
import { createRinoToggleGroup } from '../../../resources/js/design-system/rinoToggleGroup';
import { rinoButtonCommands } from '../../../resources/js/design-system/rinoButtonCommands';
import { i18n } from '../../../resources/js/i18n';
import { existsSync } from 'node:fs';

const global = { plugins: [i18n] };
afterEach(() => { i18n.global.locale.value = 'pt-BR'; });

describe('UIRinoButton', () => {
    it('inherits a command and translates its label when the locale changes', async () => {
        const wrapper = mount(UIRinoButton, { global, props: { command: 'save' } });
        expect(wrapper.text()).toBe('Salvar');
        expect(wrapper.classes()).toContain('ui-button--primary');
        expect(wrapper.get('img').attributes('src')).toBe('/assets/icons/floppyDisk_48.png');
        expect(wrapper.attributes('type')).toBe('button');
        expect(wrapper.attributes('aria-pressed')).toBeUndefined();
        i18n.global.locale.value = 'en';
        await nextTick();
        expect(wrapper.text()).toBe('Save');
        wrapper.unmount();
    });

    it('resolves explicit props, null and false before presets, and defaults to secondary', async () => {
        const wrapper = mount(UIRinoButton, { global, props: { command: 'keepSelection', label: 'rinoButtons.commands.confirm', icon: null, variant: 'destructive', toggle: false } });
        expect(wrapper.text()).toBe('Confirmar');
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.classes()).toContain('ui-button--destructive');
        expect(wrapper.attributes('aria-pressed')).toBeUndefined();
        await wrapper.setProps({ command: null, variant: undefined });
        expect(wrapper.classes()).toContain('ui-button--secondary');
        wrapper.unmount();
    });

    it('removes an inherited label and retains the translated accessible name and tooltip', () => {
        const wrapper = mount(UIRinoButton, { global, props: { command: 'save', label: null } });
        expect(wrapper.classes()).toContain('ui-icon-button');
        expect(wrapper.text()).toBe('');
        expect(wrapper.attributes('aria-label')).toBe('Salvar');
        expect(wrapper.attributes('title')).toBe('Salvar');
        expect(wrapper.get('img').attributes('aria-hidden')).toBe('true');
        wrapper.unmount();
    });

    it('rejects empty, unknown and inaccessible configurations', () => {
        expect(() => mount(UIRinoButton, { global })).toThrow('label ou icon');
        expect(() => mount(UIRinoButton, { global, props: { icon: 'theme' } })).toThrow('accessibleLabel');
        expect(() => mount(UIRinoButton, { global, props: { command: 'not-a-command' as 'save' } })).toThrow('comando desconhecido');
        expect(() => mount(UIRinoButton, { global, props: { command: 'toString' as 'save' } })).toThrow('comando desconhecido');
        expect(() => mount(UIRinoButton, { global, props: { command: 'save', toggleGroup: {} as ReturnType<typeof createRinoToggleGroup> } })).toThrow('createRinoToggleGroup');
    });

    it('stores an uncontrolled toggle, emits its state and keeps the original click event', async () => {
        const wrapper = mount(UIRinoButton, { global, props: { command: 'keepSelection' } });
        expect(wrapper.attributes('aria-pressed')).toBe('false');
        const event = new MouseEvent('click', { bubbles: true });
        wrapper.element.dispatchEvent(event);
        await nextTick();
        expect(wrapper.attributes('aria-pressed')).toBe('true');
        expect(wrapper.emitted('click')?.[0]?.[0]).toBe(event);
        expect(wrapper.emitted('update:selected')).toEqual([[true]]);
        await wrapper.trigger('click');
        expect(wrapper.attributes('aria-pressed')).toBe('false');
        wrapper.unmount();
    });

    it('supports controlled state, exclusive groups, deselection, external updates and isolated instances', async () => {
        const first = ref(false), second = ref(false);
        const group = createRinoToggleGroup({ required: false }), otherGroup = createRinoToggleGroup({ required: false });
        const harness = defineComponent({ components: { UIRinoButton }, setup: () => ({ first, second, group, otherGroup }), template: '<div><UIRinoButton command="keepSelection" v-model:selected="first" :toggle-group="group" :toggle="false" /><UIRinoButton command="showSelected" v-model:selected="second" :toggle-group="group" /><UIRinoButton command="showSelected" :toggle-group="otherGroup" /></div>' });
        const wrapper = mount(harness, { global });
        const buttons = wrapper.findAll('button');
        await buttons[0]!.trigger('click');
        expect(first.value).toBe(true);
        await buttons[2]!.trigger('click');
        await buttons[1]!.trigger('click');
        expect(first.value).toBe(false);
        expect(second.value).toBe(true);
        expect(buttons[2]!.attributes('aria-pressed')).toBe('true');
        await buttons[1]!.trigger('click');
        expect(second.value).toBe(false);
        first.value = true;
        await nextTick();
        second.value = true;
        await nextTick();
        expect(first.value).toBe(false);
        wrapper.unmount();
        expect(group.members.size).toBe(0);
        expect(otherGroup.members.size).toBe(0);
    });

    it('unregisters when the group changes and resolves initially selected members', async () => {
        const firstGroup = createRinoToggleGroup(), nextGroup = createRinoToggleGroup();
        const first = mount(UIRinoButton, { global, props: { command: 'keepSelection', toggleGroup: firstGroup, selected: true } });
        const second = mount(UIRinoButton, { global, props: { command: 'showSelected', toggleGroup: firstGroup, selected: true } });
        expect(first.emitted('update:selected')).toEqual([[false]]);
        await second.setProps({ toggleGroup: nextGroup });
        expect(firstGroup.members.size).toBe(1);
        expect(nextGroup.members.size).toBe(1);
        first.unmount(); second.unmount();
    });

    it('initializes a required group and prevents deselecting its active member', async () => {
        const group = createRinoToggleGroup();
        const harness = defineComponent({ components: { UIRinoButton }, setup: () => ({ group }), template: '<div><UIRinoButton command="keepSelection" :toggle-group="group" /><UIRinoButton command="showSelected" :toggle-group="group" /></div>' });
        const wrapper = mount(harness, { global });
        await nextTick();
        await nextTick();
        const buttons = wrapper.findAll('button');
        expect(buttons.map(button => button.attributes('aria-pressed'))).toEqual(['true', 'false']);
        await buttons[0]!.trigger('click');
        expect(buttons.map(button => button.attributes('aria-pressed'))).toEqual(['true', 'false']);
        await buttons[1]!.trigger('click');
        expect(buttons.map(button => button.attributes('aria-pressed'))).toEqual(['false', 'true']);
        await buttons[1]!.trigger('click');
        expect(buttons.map(button => button.attributes('aria-pressed'))).toEqual(['false', 'true']);
        wrapper.unmount();
    });

    it('initializes controlled groups, repairs cleared selection and selects a replacement on removal', async () => {
        const first = ref(false), second = ref(false), showFirst = ref(true);
        const group = createRinoToggleGroup();
        const harness = defineComponent({ components: { UIRinoButton }, setup: () => ({ first, second, group, showFirst }), template: '<div><UIRinoButton v-if="showFirst" command="keepSelection" v-model:selected="first" :toggle-group="group" /><UIRinoButton command="showSelected" v-model:selected="second" :toggle-group="group" /></div>' });
        const wrapper = mount(harness, { global });
        await nextTick(); await nextTick();
        expect(first.value).toBe(true);
        expect(second.value).toBe(false);
        first.value = false;
        await nextTick(); await nextTick(); await nextTick();
        expect(first.value).toBe(true);
        showFirst.value = false;
        await nextTick(); await nextTick(); await nextTick();
        expect(second.value).toBe(true);
        expect(wrapper.get('button').attributes('aria-pressed')).toBe('true');
        wrapper.unmount();
        expect(group.members.size).toBe(0);
    });

    it('waits for an available member instead of automatically selecting a disabled/loading button', async () => {
        const group = createRinoToggleGroup();
        const wrapper = mount(UIRinoButton, { global, props: { command: 'keepSelection', toggleGroup: group, disabled: true } });
        await nextTick();
        expect(wrapper.attributes('aria-pressed')).toBe('false');
        await wrapper.setProps({ disabled: false, loading: true });
        expect(wrapper.attributes('aria-pressed')).toBe('false');
        await wrapper.setProps({ loading: false });
        await nextTick(); await nextTick();
        expect(wrapper.attributes('aria-pressed')).toBe('true');
        await wrapper.setProps({ disabled: true });
        expect(wrapper.attributes('aria-pressed')).toBe('true');
        wrapper.unmount();
    });

    it('blocks click and selection while disabled/loading, exposes focus and supports translation params', async () => {
        const wrapper = mount(UIRinoButton, { attachTo: document.body, global, props: { label: 'rinoButtons.context.allValidationMessages', labelParams: { value1: 8 }, toggle: true, disabled: true } });
        expect(wrapper.text()).toBe('Ver todas as 8 mensagens');
        await wrapper.trigger('click');
        expect(wrapper.emitted('click')).toBeUndefined();
        expect(wrapper.attributes('aria-pressed')).toBe('false');
        await wrapper.setProps({ disabled: false, loading: true, type: 'submit' });
        expect(wrapper.attributes('aria-busy')).toBe('true');
        await wrapper.trigger('click');
        expect(wrapper.emitted('update:selected')).toBeUndefined();
        await wrapper.setProps({ loading: false });
        wrapper.vm.focus();
        expect(document.activeElement).toBe(wrapper.element);
        expect(wrapper.attributes('type')).toBe('submit');
        wrapper.unmount();
    });

    it('ships sufficiently sized icon assets for every canonical command', () => {
        for (const definition of Object.values(rinoButtonCommands)) expect(existsSync(`public/assets/icons/${definition.icon}_48.png`)).toBe(true);
    });
});
