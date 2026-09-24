import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AppShell from '../../../resources/js/design-system/AppShell.vue';
import BrandMark from '../../../resources/js/design-system/BrandMark.vue';
import IconButton from '../../../resources/js/design-system/IconButton.vue';
import UiAlert from '../../../resources/js/design-system/UiAlert.vue';
import UiButton from '../../../resources/js/design-system/UiButton.vue';
import UiDialog from '../../../resources/js/design-system/UiDialog.vue';
import UiField from '../../../resources/js/design-system/UiField.vue';

describe('design system base components', () => {
    it('provides a labelled main shell and derived brand assets', () => {
        const shell = mount(AppShell, { props: { centered: true, labelledBy: 'page-title' }, slots: { default: '<h1 id="page-title">Title</h1>' } });
        const logo = mount(BrandMark, { props: { alt: 'Rinos One' } });
        const icon = mount(BrandMark, { props: { alt: 'Rinos One', variant: 'icon' } });

        expect(shell.get('main').classes()).toContain('app-shell--centered');
        expect(shell.get('main').attributes('aria-labelledby')).toBe('page-title');
        expect(logo.get('img').attributes('src')).toBe('/assets/brand/logo-768.png');
        expect(icon.get('img').attributes('src')).toBe('/assets/brand/icon-192.png');
    });

    it('exposes field labels, descriptions and errors to the contained control', () => {
        const wrapper = mount(UiField, {
            props: { id: 'email', label: 'Email', help: 'Use a valid address', error: 'Required', required: true },
            slots: { default: '<template #default="slotProps"><input :aria-describedby="slotProps.describedBy" :aria-invalid="slotProps.invalid"></template>' },
        });

        expect(wrapper.get('label').attributes('for')).toBe('email');
        expect(wrapper.get('input').attributes('aria-describedby')).toBe('email-help email-error');
        expect(wrapper.get('input').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('[role="alert"]').text()).toBe('Required');
    });

    it('represents button loading, destructive actions, alerts and icon labels accessibly', () => {
        const button = mount(UiButton, { props: { loading: true, variant: 'destructive' }, slots: { default: 'Delete' } });
        const alert = mount(UiAlert, { props: { tone: 'error' }, slots: { default: 'Failure' } });
        const icon = mount(IconButton, { props: { label: 'Open preferences' }, slots: { default: '<svg viewBox="0 0 1 1" />' } });

        expect(button.get('button').attributes('disabled')).toBeDefined();
        expect(button.get('button').attributes('aria-busy')).toBe('true');
        expect(button.get('button').classes()).toContain('ui-button--destructive');
        expect(alert.get('[role="alert"]').text()).toBe('Failure');
        expect(icon.get('button').attributes('aria-label')).toBe('Open preferences');
        expect(icon.get('span').attributes('aria-hidden')).toBe('true');
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
