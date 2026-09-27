import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import WorkspaceFoldersSurface from '../../../resources/js/workspace/WorkspaceFoldersSurface.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

describe('WorkspaceFoldersSurface', () => {
    beforeEach(() => vi.clearAllMocks());

    it('renders only folders returned by the authorized API and its empty state', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { folders: [{ id: 7, parentFolderId: null, displayName: 'Shared' }] } });
        const wrapper = mount(WorkspaceFoldersSurface);
        await flushPromises();
        expect(wrapper.text()).toContain('Shared');
        expect(axios.get).toHaveBeenCalledWith('/api/v1/authorization/personal-workspace/folders');
        wrapper.unmount();

        vi.mocked(axios.get).mockResolvedValueOnce({ data: { folders: [] } });
        const empty = mount(WorkspaceFoldersSurface);
        await flushPromises();
        expect(empty.text()).toContain('Não há pastas acessíveis.');
        empty.unmount();
    });

    it('preserves previous folders and identifies them as stale after a reload failure', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { folders: [{ id: 7, parentFolderId: null, displayName: 'Shared' }] } });
        const wrapper = mount(WorkspaceFoldersSurface);
        await flushPromises();
        vi.mocked(axios.get).mockRejectedValueOnce(new Error('unavailable'));
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Os dados exibidos podem estar desatualizados.');
        expect(wrapper.text()).toContain('Shared');
        wrapper.unmount();
    });

    it('keeps the previous list on an offline transition and on a denied response', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { folders: [{ id: 7, parentFolderId: null, displayName: 'Shared' }] } });
        const wrapper = mount(WorkspaceFoldersSurface);
        await flushPromises();
        window.dispatchEvent(new Event('offline'));
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Você está offline.');
        expect(wrapper.text()).toContain('Shared');

        window.dispatchEvent(new Event('online'));
        await nextTick();
        vi.mocked(axios.get).mockRejectedValueOnce({ response: { status: 403 } });
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Não foi possível carregar os arquivos acessíveis.');
        expect(wrapper.text()).toContain('Shared');
        wrapper.unmount();
    });

    it('exposes an accessible folder landmark and keyboard-operable native controls', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { folders: [{ id: 7, parentFolderId: 1, displayName: 'Shared' }] } });
        const wrapper = mount(WorkspaceFoldersSurface);
        await flushPromises();
        expect(wrapper.get('main').attributes('aria-labelledby')).toBe('workspace-folders-title');
        expect(wrapper.get('h2').attributes('tabindex')).toBe('-1');
        expect(wrapper.get('nav').attributes('aria-label')).toBe('Pastas acessíveis');
        expect(wrapper.get('nav button').attributes('type')).toBe('button');
        expect(wrapper.text()).toContain('Pasta compartilhada');
        wrapper.unmount();
    });

    it('rechecks a folder before opening it and removes it on a fresh denial', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { folders: [{ id: 7, parentFolderId: null, displayName: 'Shared' }] } });
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { decisions: [{ allowed: false }] } });
        const wrapper = mount(WorkspaceFoldersSurface);
        await flushPromises();
        await wrapper.get('nav button').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/api/v1/authorization/resource-checks', { checks: [{ permissionKey: 'personal.folder.read', resource: { type: 'personal.folder', id: 7 } }] });
        expect(wrapper.text()).toContain('Não há pastas acessíveis.');
        wrapper.unmount();
    });
});
