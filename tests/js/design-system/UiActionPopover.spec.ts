import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import UiActionPopover from '../../../resources/js/design-system/UiActionPopover.vue';
import UIRinoButton from '../../../resources/js/design-system/UIRinoButton.vue';
import { i18n } from '../../../resources/js/i18n';

const wrappers: VueWrapper[] = [];
function createPopover(): VueWrapper {
    const wrapper = mount(UiActionPopover, { attachTo: document.body, props: { label: 'access.drive.moreActions' }, slots: { default: '<UIRinoButton accessible-label="access.drive.refresh" icon="fileRefresh" />' }, global: { plugins: [i18n], components: { UIRinoButton } } });
    wrappers.push(wrapper); return wrapper;
}
afterEach(() => wrappers.splice(0).forEach(wrapper => wrapper.unmount()));
describe('central action popover', () => {
    it.each(['pointerdown', 'click'])('closes on an outside %s', async eventName => {
        const wrapper = createPopover(); await wrapper.get('button').trigger('click'); await flushPromises();
        expect(document.querySelector('.ui-action-popover__panel')).not.toBeNull();
        document.body.dispatchEvent(new Event(eventName, { bubbles: true })); await flushPromises();
        expect(document.querySelector('.ui-action-popover__panel')).toBeNull();
    });
    it('keeps internal interactions open, but closes after an action or Escape', async () => {
        const wrapper = createPopover(); await wrapper.get('button').trigger('click'); await flushPromises();
        document.querySelector('.ui-action-popover__panel')!.dispatchEvent(new Event('pointerdown', { bubbles: true })); await flushPromises();
        expect(document.querySelector('.ui-action-popover__panel')).not.toBeNull();
        (document.querySelector('.ui-action-popover__panel button') as HTMLButtonElement).click(); await flushPromises();
        expect(wrapper.get('button').attributes('aria-expanded')).toBe('false');
        await wrapper.get('button').trigger('click'); await flushPromises();
        document.querySelector('.ui-action-popover__panel')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })); await flushPromises();
        expect(document.querySelector('.ui-action-popover__panel')).toBeNull();
        expect(document.activeElement).toBe(wrapper.get('button').element);
    });
});
