import { mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { describe, expect, it } from 'vitest';
import AppShell from '../../../resources/js/design-system/AppShell.vue';
import AccessFrame from '../../../resources/js/design-system/AccessFrame.vue';
import BrandMark from '../../../resources/js/design-system/BrandMark.vue';
import UIRinoButton from '../../../resources/js/design-system/UIRinoButton.vue';
import UiAlert from '../../../resources/js/design-system/UiAlert.vue';


import UiControlGroup from '../../../resources/js/design-system/UiControlGroup.vue';

import UiDialog from '../../../resources/js/design-system/UiDialog.vue';
import UiField from '../../../resources/js/design-system/UiField.vue';
import { i18n } from '../../../resources/js/i18n';

describe('design system base components', () => {
    it('provides a labelled main shell and derived brand assets', () => {
        const shell = mount(AppShell, { props: { centered: true, labelledBy: 'page-title' }, slots: { default: '<h1 id="page-title">Title</h1>' } });
        const logo = mount(BrandMark, { props: { alt: 'Rinos One' } });
        const icon = mount(BrandMark, { props: { alt: 'Rinos One', variant: 'icon' } });

        expect(shell.get('main').classes()).toContain('app-shell--centered');
        expect(shell.get('main').attributes('aria-labelledby')).toBe('page-title');
        expect(logo.get('img').attributes('src')).toBe('/assets/brand/logo-768.png?v=20260930');
        expect(icon.get('img').attributes('src')).toBe('/assets/brand/crest-192.png?v=20260930');
    });

    it('composes the access identity with a decorative crest above the landscape logo', () => {
        const frame = mount(AccessFrame, { global: { plugins: [createPinia(), i18n] }, slots: { default: 'Access form' } });

        expect(frame.get('.access-frame__crest').attributes('src')).toBe('/assets/brand/crest-192.png?v=20260930');
        expect(frame.get('.access-frame__crest').attributes('alt')).toBe('');
        expect(frame.get('.access-frame__brand').attributes('src')).toBe('/assets/brand/logo-768.png?v=20260930');
    });

    it('exposes field labels, contextual help and errors to the contained control', async () => {
        const wrapper = mount(UiField, {
            props: { id: 'email', label: 'Email', help: 'Use a valid address', error: 'Required', required: true },
            slots: { default: '<template #default="slotProps"><input :aria-describedby="slotProps.describedBy" :aria-invalid="slotProps.invalid"></template>' },
        });

        expect(wrapper.get('label').attributes('for')).toBe('email');
        expect(wrapper.get('input').attributes('aria-describedby')).toBe('email-error');
        expect(wrapper.get('input').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('[role="alert"]').text()).toBe('Required');
        expect(wrapper.get('.ui-field__required').text()).toBe('*');
        expect(wrapper.find('.ui-field__help-popover').exists()).toBe(false);
        expect(wrapper.find('.ui-field__validation-popover').exists()).toBe(false);

        await wrapper.get('.ui-field__validation-trigger').trigger('click');

        expect(wrapper.get('.ui-field__validation-trigger').attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('[role="tooltip"]').text()).toBe('Required');

        await wrapper.get('.ui-field__help-trigger').trigger('click');

        expect(wrapper.get('.ui-field__help-trigger').attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('[role="tooltip"]').text()).toBe('Use a valid address');
    });

    it('represents button loading, destructive actions, alerts and icon labels accessibly', () => {
        const button = mount(UIRinoButton, { props: { command: 'delete', loading: true }, global: { plugins: [i18n] } });
        const alert = mount(UiAlert, { props: { tone: 'error' }, slots: { default: 'Failure' } });
        const icon = mount(UIRinoButton, { props: { icon: 'theme', accessibleLabel: 'access.presentation.visualPreferences' }, global: { plugins: [i18n] } });

        expect(button.get('button').attributes('disabled')).toBeDefined();
        expect(button.get('button').attributes('aria-busy')).toBe('true');
        expect(button.get('button').classes()).toContain('ui-button--destructive');
        expect(alert.get('[role="alert"]').text()).toBe('Failure');
        expect(icon.get('button').attributes('aria-label')).toBe(i18n.global.t('access.presentation.visualPreferences'));
        expect(icon.get('img').attributes('aria-hidden')).toBe('true');
    });

    it('renders canonical commands from one registry and groups controls without a toolbar contract', () => {
        const command = mount(UIRinoButton, { props: { command: 'alter' }, global: { plugins: [i18n] } });
        const listCommand = mount(UIRinoButton, { props: { command: 'keepSelection' }, global: { plugins: [i18n] } });
        const group = mount(UiControlGroup, { props: { label: 'Ações de registro' }, slots: { default: '<button type="button">Ação</button>' } });

        expect(command.get('button').text()).toContain('Alterar');
        expect(command.get('button').classes()).toContain('ui-button--secondary');
        expect(command.get('img').attributes('src')).toBe('/assets/icons/dataEdit_48.png');
        expect(listCommand.get('button').attributes('aria-label')).toBe('Manter seleção');
        expect(listCommand.get('img').attributes('src')).toBe('/assets/icons/tableLockSelection_48.png');
        expect(group.get('[role="group"]').attributes('aria-label')).toBe('Ações de registro');
        expect(group.find('[role="toolbar"]').exists()).toBe(false);
    });

    it('traps tab navigation, supports Escape and restores focus when a dialog closes', async () => {
        const opener = document.createElement('button');
        document.body.appendChild(opener);
        opener.focus();
        const wrapper = mount(UiDialog, {
            attachTo: document.body,
            props: { modelValue: true, title: 'Confirm action', destructive: true },
            slots: { default: '<template #default="{ close }"><button>Cancel</button><button @click="close">Confirm</button></template>' },
        });

        await wrapper.vm.$nextTick();
        const dialog = wrapper.get('[role="alertdialog"]');
        const buttons = dialog.findAll('button');
        buttons[1].element.focus();
        await dialog.trigger('keydown', { key: 'Tab' });
        expect(document.activeElement).toBe(buttons[0].element);
        await dialog.trigger('keydown', { key: 'Escape' });
        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
        await wrapper.setProps({ modelValue: false });
        expect(document.activeElement).toBe(opener);

        wrapper.unmount();
        opener.remove();
    });
});
