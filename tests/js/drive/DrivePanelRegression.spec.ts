import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import axios from 'axios';
import DriveExplorer from '../../../resources/js/drive/DriveExplorer.vue';
import { i18n } from '../../../resources/js/i18n';
import type { WorkspaceSurface } from '../../../resources/js/workspace/workspaceTypes';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), isAxiosError: vi.fn(value => Boolean(value?.response)) } }));
const capabilities = { read: true, edit: true, trash: true };
const usage = { workspaceBytes: 4096, systemManagedBytes: 0, trashBytes: 0, totalBytes: 4096 };
const folder = { id: 7, kind: 'folder', displayName: 'Projetos', parentFolderId: null, logicalSizeBytes: 2048, detectedMimeType: null, modifiedAt: '2026-10-02T12:00:00Z', capabilities };
const file = { ...folder, id: 8, kind: 'file', displayName: 'Documento.pdf', logicalSizeBytes: 1024, detectedMimeType: 'application/pdf' };
const projection = { location: { kind: 'root', id: null, displayName: 'Meu Drive', parentFolderId: null }, breadcrumbs: [], folders: [folder], files: [file], capabilities, usage };
const catalog = { drives: [{ target: { kind: 'personal', tenantId: null }, displayName: 'Meu Drive', category: 'PERSONAL', usage }, { target: { kind: 'tenant', tenantId: 42 }, displayName: 'Empresa', category: 'TENANT', usage }], sharedWithMe: { kind: 'shared-with-me', displayName: 'Compartilhados comigo' } };
const surface: WorkspaceSurface = { id: 'drive', destinationId: 'global.drive', scope: 'global', tenantId: null, titleKey: '', label: 'Rinos Drive', icon: 'drive', dirty: false, status: 'active' };
const wrappers: VueWrapper[] = [];
function respond(url: string): Promise<unknown> {
    if (url === '/api/v1/drive/catalog') return Promise.resolve({ data: catalog });
    if (url.endsWith('/tree')) return Promise.resolve({ data: { folders: [folder] } });
    const isFolder = url.endsWith('/folders/7');
    return Promise.resolve({ data: { ...projection, location: isFolder ? { ...folder, kind: 'folder' } : projection.location, breadcrumbs: isFolder ? [{ ...folder, kind: 'folder' }] : [], files: url.includes('/tenants/') ? [] : [file] } });
}
async function openSplit(owner = 12): Promise<VueWrapper> {
    const wrapper = mount(DriveExplorer, { attachTo: document.body, props: { surface }, global: { plugins: [i18n], provide: { 'authenticated-user-id': ref(owner) } } });
    wrappers.push(wrapper);
    await flushPromises();
    if (wrapper.find('[aria-label="Abrir segundo painel"]').exists()) await wrapper.get('[aria-label="Abrir segundo painel"]').trigger('click');
    await flushPromises();
    return wrapper;
}
beforeEach(() => { vi.clearAllMocks(); localStorage.clear(); i18n.global.locale.value = 'pt-BR'; vi.mocked(axios.get).mockImplementation(respond); });
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.useRealTimers(); vi.unstubAllGlobals(); });

describe('independent instances of the same Drive panel', () => {
    it('renders date and time for files and folders in both details views', async () => {
        const wrapper = await openSplit();
        for (const id of ['primary', 'secondary']) {
            const dates = wrapper.findAll(`[data-panel="${id}"] .drive-panel__item-date`);
            expect(dates).toHaveLength(2);
            dates.forEach(date => { expect(date.text()).toContain('02/10/2026'); expect(date.text()).toMatch(/\d{2}:\d{2}/); });
        }
    });
    it('toggles only its own tree from one left-aligned control', async () => {
        const wrapper = await openSplit();
        for (const id of ['primary', 'secondary']) {
            const panel = wrapper.get(`[data-panel="${id}"]`);
            expect(panel.findAll('.drive-panel__window-action [aria-label="Fechar árvore"]')).toHaveLength(1);
            expect(panel.find('.drive-panel__operation-actions [aria-label="Fechar árvore"]').exists()).toBe(false);
        }
        await wrapper.get('[data-panel="primary"] .drive-panel__window-action [aria-label="Fechar árvore"]').trigger('click');
        expect(wrapper.get('[data-panel="primary"] .drive-panel__layout').classes()).toContain('drive-panel__layout--tree-hidden');
        expect(wrapper.get('[data-panel="secondary"] .drive-panel__layout').classes()).not.toContain('drive-panel__layout--tree-hidden');
    });
    it.each(['primary', 'secondary'])('refreshes only %s, preserving its current folder', async id => {
        const wrapper = await openSplit();
        const panel = wrapper.get(`[data-panel="${id}"]`);
        await panel.findAll('.drive-panel__item')[0].trigger('dblclick'); await flushPromises();
        vi.mocked(axios.get).mockClear();
        await panel.get('[aria-label="Atualizar"]').trigger('click'); await flushPromises();
        expect(axios.get).toHaveBeenCalledTimes(2);
        expect(axios.get).toHaveBeenCalledWith('/api/v1/drive/personal/folders/7');
        expect(axios.get).not.toHaveBeenCalledWith('/api/v1/drive/personal/locations/root');
    });
    it.each(['primary', 'secondary'])('navigates the selected drive text back to its root in %s', async id => {
        const wrapper = await openSplit(); const panel = wrapper.get(`[data-panel="${id}"]`);
        await panel.findAll('.drive-panel__item')[0].trigger('dblclick'); await flushPromises();
        vi.mocked(axios.get).mockClear();
        await panel.get('.drive-panel__drive-header').trigger('click'); await flushPromises();
        expect(axios.get).toHaveBeenCalledWith('/api/v1/drive/personal/locations/root');
        expect(axios.get).toHaveBeenCalledTimes(2);
    });
    it('saves independent per-user per-panel view preferences and restores them', async () => {
        let wrapper = await openSplit();
        await wrapper.get('[data-panel="primary"] .drive-panel__view-mode[aria-label="Grade"]').trigger('click');
        await wrapper.get('[data-panel="secondary"] .drive-panel__view-mode[aria-label="Lista"]').trigger('click');
        expect(localStorage.getItem('rinos-one.drive.view-mode.v2.12.primary')).toBe('grid');
        expect(localStorage.getItem('rinos-one.drive.view-mode.v2.12.secondary')).toBe('list');
        wrapper.unmount(); wrappers.pop(); localStorage.removeItem('rinos-one.drive.secondary-pane.v1');
        wrapper = await openSplit();
        expect(wrapper.get('[data-panel="primary"] .drive-panel__items').classes()).toContain('drive-panel__items--grid');
        expect(wrapper.get('[data-panel="secondary"] .drive-panel__items').classes()).toContain('drive-panel__items--list');
        const other = await openSplit(99);
        expect(other.get('[data-panel="primary"] .drive-panel__items').classes()).toContain('drive-panel__items--details');
    });
    it('uses square brackets, folder/file totals and recursive selected sizes', async () => {
        const wrapper = await openSplit();
        const panel = wrapper.get('[data-panel="primary"]');
        const items = panel.findAll('.drive-panel__item');
        await items[0].trigger('click'); await items[1].trigger('click', { ctrlKey: true });
        expect(panel.get('.drive-panel__status-bar').text()).toBe('2 Itens [1|1] | Selecionados: 2 Itens [1|1] 3 KB');
    });
    it('synchronizes mutation data while preserving the other panel location and view', async () => {
        const wrapper = await openSplit(); const secondary = wrapper.get('[data-panel="secondary"]');
        await secondary.findAll('.drive-panel__item')[0].trigger('dblclick'); await flushPromises();
        await secondary.get('.drive-panel__view-mode[aria-label="Lista"]').trigger('click');
        vi.mocked(axios.post).mockResolvedValue({ data: {} }); vi.mocked(axios.get).mockClear();
        const primary = wrapper.get('[data-panel="primary"]');
        await primary.get('[aria-label="Nova pasta"]').trigger('click');
        await wrapper.get('input:not([type="file"])').setValue('Nova');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(axios.get).toHaveBeenCalledWith('/api/v1/drive/personal/folders/7');
        expect(secondary.get('.drive-panel__breadcrumbs').text()).toContain('Projetos');
        expect(secondary.get('.drive-panel__items').classes()).toContain('drive-panel__items--list');
    });
    it('ignores delayed responses from a previously selected drive', async () => {
        const wrapper = await openSplit(); const primary = wrapper.get('[data-panel="primary"]');
        let release!: (value: unknown) => void;
        vi.mocked(axios.get).mockImplementation(url => url === '/api/v1/tenants/42/drive/locations/root' ? new Promise(resolve => { release = resolve; }) : respond(url));
        await primary.findAll('.drive-panel__drive-header')[1].trigger('click');
        await primary.findAll('.drive-panel__drive-header')[0].trigger('click'); await flushPromises();
        release({ data: { ...projection, files: [{ ...file, displayName: 'Resposta antiga' }] } }); await flushPromises();
        expect(primary.text()).toContain('Documento.pdf'); expect(primary.text()).not.toContain('Resposta antiga');
    });
    it('retains multi-selection when dragging and confirms a folder destination across panels', async () => {
        const wrapper = await openSplit(); const primary = wrapper.get('[data-panel="primary"]');
        const items = primary.findAll('.drive-panel__item');
        await items[0].trigger('click'); await items[1].trigger('click', { ctrlKey: true });
        await items[1].trigger('dragstart');
        const secondary = wrapper.get('[data-panel="secondary"]');
        await secondary.findAll('.drive-panel__drive-header')[1].trigger('click'); await flushPromises();
        await secondary.get('.drive-panel__collection').trigger('drop', { dataTransfer: { files: [] } });
        await flushPromises();
        expect(wrapper.get('[role="dialog"]').text()).toContain('2');
        expect(wrapper.get('input[value="COPY"]').element).toHaveProperty('checked', true);
    });
    it('separates file and folder selection even when database ids match', async () => {
        vi.mocked(axios.get).mockImplementation(async url => {
            const result = await respond(url) as { data: typeof projection };
            if (url.endsWith('/locations/root')) result.data = { ...projection, files: [{ ...file, id: folder.id }] };
            return result;
        });
        const wrapper = await openSplit(); const primary = wrapper.get('[data-panel="primary"]');
        await primary.findAll('.drive-panel__item')[0].trigger('click');
        expect(primary.get('.drive-panel__status-bar').text()).toContain('Selecionados: 1 Itens [1|0]');
        expect(primary.findAll('.drive-panel__item--selected')).toHaveLength(1);
    });
    it('hides shared/export drives and the split button only in the secondary panel', async () => {
        const wrapper = await openSplit();
        vi.mocked(axios.post).mockResolvedValue({ data: { exportId: 'ZIP-1', state: 'PENDING', expiresAt: '2026-10-03T12:00:00Z' } });
        const secondary = wrapper.get('[data-panel="secondary"]');
        await secondary.findAll('.drive-panel__item')[0].trigger('click');
        await secondary.get('[aria-label="Baixar"]').trigger('click'); await flushPromises();
        expect(wrapper.get('[data-panel="primary"] .drive-panel__tree-list').text()).toContain('Exportações');
        expect(secondary.get('.drive-panel__tree-list').text()).not.toContain('Exportações');
        expect(secondary.find('[aria-label="Abrir segundo painel"]').exists()).toBe(false);
    });
    it('uses a tree folder as a transfer source without changing collection selection', async () => {
        const wrapper = await openSplit();
        const primary = wrapper.get('[data-panel="primary"]');
        await primary.get('.drive-panel__tree-row .drive-panel__tree-item').trigger('dragstart');
        const secondary = wrapper.get('[data-panel="secondary"]');
        await secondary.findAll('.drive-panel__drive-header')[1].trigger('click'); await flushPromises();
        await secondary.get('.drive-panel__collection').trigger('drop', { dataTransfer: { files: [] } });
        await flushPromises();
        expect(wrapper.findComponent({ name: 'DriveTransferDialog' }).exists()).toBe(true);
        expect(wrapper.get('input[value="COPY"]').element).toHaveProperty('checked', true);
        expect(primary.find('.drive-panel__item--selected').exists()).toBe(false);
    });
    it('rejects dropping a folder onto itself', async () => {
        const wrapper = await openSplit(); const primary = wrapper.get('[data-panel="primary"]');
        const item = primary.findAll('.drive-panel__item')[0];
        await item.trigger('click'); await item.trigger('dragstart');
        await primary.get('.drive-panel__tree-row .drive-panel__tree-item').trigger('drop', { dataTransfer: { files: [] } });
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(axios.post).not.toHaveBeenCalled();
    });
    it('disables Move for a read-only source, while retaining Copy', async () => {
        vi.mocked(axios.get).mockImplementation(async url => {
            const result = await respond(url) as { data: typeof projection };
            if (url.endsWith('/locations/root')) result.data = { ...projection, files: [{ ...file, capabilities: { read: true, edit: false, trash: false } }] };
            return result;
        });
        const wrapper = await openSplit(); const primary = wrapper.get('[data-panel="primary"]');
        const item = primary.findAll('.drive-panel__item')[1];
        await item.trigger('click'); await item.trigger('dragstart');
        const secondary = wrapper.get('[data-panel="secondary"]');
        await secondary.findAll('.drive-panel__drive-header')[1].trigger('click'); await flushPromises();
        await secondary.get('.drive-panel__collection').trigger('drop', { dataTransfer: { files: [] } });
        await flushPromises();
        expect(wrapper.get('input[value="MOVE"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('input[value="COPY"]').element).toHaveProperty('checked', true);
    });
    it('keeps export polling bound to its origin after closing its source panel', async () => {
        vi.useFakeTimers();
        vi.mocked(axios.post).mockResolvedValue({ data: { exportId: 'ZIP-WORK', state: 'PENDING', expiresAt: null } });
        vi.mocked(axios.get).mockImplementation(url => url.endsWith('/exports/ZIP-WORK')
            ? Promise.resolve({ data: { exportId: 'ZIP-WORK', state: 'PROCESSING', expiresAt: null } }) : respond(url));
        const wrapper = await openSplit(); const secondary = wrapper.get('[data-panel="secondary"]');
        await secondary.findAll('.drive-panel__drive-header')[1].trigger('click'); await flushPromises();
        await secondary.findAll('.drive-panel__item')[0].trigger('click');
        await secondary.get('[aria-label="Baixar"]').trigger('click'); await flushPromises();
        await wrapper.get('[aria-label="Fechar segundo painel"]').trigger('click');
        await vi.advanceTimersByTimeAsync(3000); await flushPromises();
        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/42/drive/exports/ZIP-WORK');
        expect(axios.get).not.toHaveBeenCalledWith('/api/v1/drive/personal/exports/ZIP-WORK');
        const primary = wrapper.get('[data-panel="primary"]');
        await primary.findAll('.drive-panel__tree-item').find(item => item.text() === 'Exportações')!.trigger('click');
        expect(primary.text()).toContain('Compactando');
    });
    it('retries transient export failures, but stops on expiration', async () => {
        vi.useFakeTimers(); let attempts = 0;
        vi.mocked(axios.post).mockResolvedValue({ data: { exportId: 'ZIP-RETRY', state: 'PENDING', expiresAt: null } });
        vi.mocked(axios.get).mockImplementation(url => {
            if (url.endsWith('/exports/ZIP-RETRY')) return Promise.reject(++attempts === 1 ? new Error('Offline') : { response: { status: 404 } });
            return respond(url);
        });
        const wrapper = await openSplit(); const primary = wrapper.get('[data-panel="primary"]');
        await primary.findAll('.drive-panel__item')[0].trigger('click');
        await primary.get('[aria-label="Baixar"]').trigger('click'); await flushPromises();
        await vi.advanceTimersByTimeAsync(3000); expect(attempts).toBe(1);
        await vi.advanceTimersByTimeAsync(6000); expect(attempts).toBe(2);
        await primary.findAll('.drive-panel__tree-item').find(item => item.text() === 'Exportações')!.trigger('click');
        expect(primary.text()).toContain('Exportação expirada');
        await vi.advanceTimersByTimeAsync(20000); expect(attempts).toBe(2);
    });
    it('never restores the second panel on mobile, and opens its own tree drawer', async () => {
        vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: true })));
        localStorage.setItem('rinos-one.drive.secondary-pane.v1', 'open');
        const wrapper = await openSplit();
        expect(wrapper.find('[data-panel="secondary"]').exists()).toBe(false);
        expect(wrapper.find('[aria-label="Abrir segundo painel"]').exists()).toBe(false);
        await wrapper.get('.drive-panel__window-action button').trigger('click');
        expect(wrapper.get('.drive-panel__tree').classes()).toContain('drive-panel__tree--open');
    });
    it('retains a root-drop source while permission lookup completes after dragend', async () => {
        const wrapper = await openSplit(); const primary = wrapper.get('[data-panel="primary"]');
        const item = primary.findAll('.drive-panel__item')[1];
        await item.trigger('click'); await item.trigger('dragstart');
        let resolveRoot!: (result: unknown) => void;
        vi.mocked(axios.get).mockImplementation(url => url === '/api/v1/tenants/42/drive/locations/root'
            ? new Promise(resolve => { resolveRoot = resolve; }) : respond(url));
        await wrapper.get('[data-panel="secondary"]').findAll('.drive-panel__drive-header')[1].trigger('drop', { dataTransfer: { files: [] } });
        await item.trigger('dragend');
        resolveRoot({ data: projection }); await flushPromises();
        expect(wrapper.get('[role="dialog"]').text()).toContain('Copiar');
        expect(wrapper.get('input[value="COPY"]').element).toHaveProperty('checked', true);
    });
    it('registers an export even if its source panel closes before the request returns', async () => {
        const wrapper = await openSplit();
        let finish!: (result: unknown) => void;
        vi.mocked(axios.post).mockImplementation(() => new Promise(resolve => { finish = resolve; }));
        const secondary = wrapper.get('[data-panel="secondary"]');
        await secondary.findAll('.drive-panel__item')[0].trigger('click');
        await secondary.get('[aria-label="Baixar"]').trigger('click');
        await wrapper.get('[aria-label="Fechar segundo painel"]').trigger('click');
        finish({ data: { exportId: 'ZIP-LATE', state: 'PENDING', expiresAt: null } }); await flushPromises();
        expect(wrapper.get('[data-panel="primary"] .drive-panel__tree-list').text()).toContain('Exportações');
    });
});
