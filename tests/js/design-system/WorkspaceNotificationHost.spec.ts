import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import WorkspaceNotificationHost from '../../../resources/js/design-system/WorkspaceNotificationHost.vue';
import type { WorkspaceNotification } from '../../../resources/js/workspace/workspaceTypes';
import { i18n } from '../../../resources/js/i18n';

const notifications: WorkspaceNotification[] = [
    { id: 'notice-1', kind: 'information', messageKey: 'access.workspace.notification.contextChanged', persistent: false },
    { id: 'notice-2', kind: 'error', messageKey: 'access.workspace.notification.contextChanged', persistent: true },
];

describe('WorkspaceNotificationHost', () => {
    beforeEach(() => { i18n.global.locale.value = 'pt-BR'; });

    it('presents the queue head, announces errors assertively and does not move focus', () => {
        const opener = document.createElement('button');
        document.body.append(opener);
        opener.focus();
        const wrapper = mount(WorkspaceNotificationHost, { attachTo: document.body, props: { notifications: [notifications[1]!], dismissLabel: 'Dispensar' }, global: { plugins: [i18n] } });

        expect(wrapper.get('[role="alert"]').attributes('aria-live')).toBe('assertive');
        expect(document.activeElement).toBe(opener);
        opener.remove();
    });

    it('dismisses only nonpersistent notifications after the configured timeout', async () => {
        vi.useFakeTimers();
        const wrapper = mount(WorkspaceNotificationHost, { props: { notifications: [notifications[0]!], dismissLabel: 'Dispensar', timeout: 100 }, global: { plugins: [i18n] } });

        await vi.advanceTimersByTimeAsync(100);
        expect(wrapper.emitted('dismiss')).toEqual([['notice-1']]);
        wrapper.unmount();
        vi.useRealTimers();
    });
});
