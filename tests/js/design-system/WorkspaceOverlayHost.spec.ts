import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import WorkspaceOverlayHost from '../../../resources/js/design-system/WorkspaceOverlayHost.vue';
import type { WorkspaceDialog } from '../../../resources/js/workspace/workspaceTypes';
import { i18n } from '../../../resources/js/i18n';

const discardDialog: WorkspaceDialog = {
    id: 'dialog-1', kind: 'confirmation', closePolicy: 'explicit', originSurfaceId: 'surface-1',
    action: { type: 'discard-surface', surfaceId: 'surface-1' },
};

describe('WorkspaceOverlayHost', () => {
    beforeEach(() => { i18n.global.locale.value = 'pt-BR'; });

    it('requires an explicit decision to discard and keeps focus inside the confirmation', async () => {
        const opener = document.createElement('button');
        document.body.append(opener);
        opener.focus();
        const wrapper = mount(WorkspaceOverlayHost, { attachTo: document.body, props: { dialogs: [discardDialog] }, global: { plugins: [i18n] } });
        await wrapper.vm.$nextTick();

        const dialog = wrapper.get('[role="alertdialog"]');
        await dialog.trigger('keydown', { key: 'Escape' });
        await wrapper.get('.ui-dialog-backdrop').trigger('mousedown');
        expect(wrapper.emitted('resolve')).toBeUndefined();

        await wrapper.get('.ui-button--secondary').trigger('click');
        expect(wrapper.emitted('resolve')).toEqual([['dialog-1', false]]);
        wrapper.unmount();
        opener.remove();
    });

    it('allows Escape for a dismissible topmost dialog only', async () => {
        const wrapper = mount(WorkspaceOverlayHost, {
            props: { dialogs: [{ id: 'dialog-2', kind: 'information', closePolicy: 'dismissible', originSurfaceId: null, action: null }] },
            global: { plugins: [i18n] },
        });

        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.emitted('resolve')).toEqual([['dialog-2', false]]);
    });
});
