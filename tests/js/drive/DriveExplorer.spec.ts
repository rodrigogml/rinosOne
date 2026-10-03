import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DriveExplorer from '../../../resources/js/drive/DriveExplorer.vue';
import { i18n } from '../../../resources/js/i18n';
import type { WorkspaceSurface } from '../../../resources/js/workspace/workspaceTypes';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), isAxiosError: vi.fn((value) => Boolean(value?.response)) } }));

const capabilities = { read: true, edit: true, trash: true };
const rootProjection = {
    location: { kind: 'root', id: null, displayName: 'Meus arquivos', parentFolderId: null },
    breadcrumbs: [],
    folders: [{ id: 7, kind: 'folder', displayName: 'Projetos', parentFolderId: null, logicalSizeBytes: 0, detectedMimeType: null, modifiedAt: null, capabilities }],
    files: [{ id: 8, kind: 'file', displayName: 'Contrato.pdf', parentFolderId: null, logicalSizeBytes: 2048, detectedMimeType: 'application/pdf', modifiedAt: '2026-09-28T10:00:00Z', capabilities }],
    capabilities,
    usage: { workspaceBytes: 2048, systemManagedBytes: 0, trashBytes: 0, totalBytes: 2048 },
};
const personalSurface: WorkspaceSurface = { id: 'personal-drive', destinationId: 'personal.drive', scope: 'personal', tenantId: null, titleKey: 'access.workspace.title', label: 'Rinos Drive Pessoal', icon: 'drive', dirty: false, status: 'active' };
const tenantSurface: WorkspaceSurface = { ...personalSurface, id: 'tenant-drive', destinationId: 'tenant.drive', scope: 'tenant', tenantId: 42, label: 'Rinos Drive Work' };
const globalSurface: WorkspaceSurface = { id: 'global-drive', destinationId: 'global.drive', scope: 'global', tenantId: null, titleKey: 'access.workspace.title', label: 'Rinos Drive', icon: 'drive', dirty: false, status: 'active' };
const globalCatalog = {
    drives: [
        { target: { kind: 'personal', tenantId: null }, displayName: 'Meu Drive', category: 'PERSONAL', usage: rootProjection.usage },
        { target: { kind: 'tenant', tenantId: 42 }, displayName: 'Oficina Rubi', category: 'TENANT', usage: rootProjection.usage },
    ],
    sharedWithMe: { kind: 'shared-with-me', displayName: 'Compartilhados comigo' },
};

function queueInitialLoad(projection: Record<string, unknown> & { folders: unknown[] } = rootProjection): void {
    vi.mocked(axios.get).mockResolvedValueOnce({ data: { folders: projection.folders } });
    vi.mocked(axios.get).mockResolvedValueOnce({ data: projection });
}
function mountDrive(surface: WorkspaceSurface) {
    return mount(DriveExplorer, { attachTo: document.body, props: { surface }, global: { plugins: [i18n] } });
}

describe('DriveExplorer', () => {
    beforeEach(() => { vi.clearAllMocks(); window.localStorage.clear(); i18n.global.locale.value = 'pt-BR'; });

    it('renders a personal projection, its tree and a selectable collection', async () => {
        queueInitialLoad();
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        expect(axios.get).toHaveBeenNthCalledWith(1, '/api/v1/drive/personal/tree');
        expect(axios.get).toHaveBeenNthCalledWith(2, '/api/v1/drive/personal/locations/root');
        expect(wrapper.get('main').attributes('aria-label')).toBe('Rinos Drive Pessoal');
        expect(wrapper.text()).not.toContain('Workspace pessoal');
        expect(wrapper.text()).toContain('Projetos');
        expect(wrapper.text()).toContain('Contrato.pdf');
        expect(wrapper.get('.drive-panel__usage').attributes('title')).toBe('2 KB utilizados');
        await wrapper.findAll('.drive-panel__item')[1].trigger('click');
        expect(wrapper.text()).toContain('2 Itens [1|1] | Selecionados: 1 Itens [0|1] 2 KB');
    });

    it('uses the tenant scoped routes and preserves an accessible partial tree', async () => {
        const partial = { ...rootProjection, folders: [{ ...rootProjection.folders[0], parentFolderId: 999 }] };
        queueInitialLoad(partial);
        const wrapper = mountDrive(tenantSurface);
        await flushPromises();

        expect(axios.get).toHaveBeenNthCalledWith(1, '/api/v1/tenants/42/drive/tree');
        expect(axios.get).toHaveBeenNthCalledWith(2, '/api/v1/tenants/42/drive/locations/root');
        expect(wrapper.get('main').attributes('aria-label')).toBe('Rinos Drive Work');
        expect(wrapper.text()).not.toContain('Workspace organizacional');
        expect(wrapper.find('.drive-panel__tree-list').text()).toContain('Projetos');
    });

    it('hides folder creation and upload controls in the trash', async () => {
        const trashProjection = {
            ...rootProjection,
            location: { kind: 'trash', id: null, displayName: 'Lixeira', parentFolderId: null },
            folders: [],
            files: [],
        };
        queueInitialLoad(trashProjection);
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        expect(wrapper.find('[aria-label="Nova pasta"]').exists()).toBe(false);
        expect(wrapper.find('[aria-label="Upload"]').exists()).toBe(false);
    });

    it('opens one global Drive with independently configured panels', async () => {
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/v1/drive/catalog') return Promise.resolve({ data: globalCatalog });
            if (url.endsWith('/tree')) return Promise.resolve({ data: { folders: rootProjection.folders } });
            return Promise.resolve({ data: rootProjection });
        });
        const wrapper = mountDrive(globalSurface);
        await flushPromises();
        await wrapper.get('[aria-label="Abrir segundo painel"]').trigger('click');
        await flushPromises();
        const primary = wrapper.get('[data-panel="primary"]');
        const secondary = wrapper.get('[data-panel="secondary"]');
        expect(wrapper.get('.drive-explorer__panes').classes()).toContain('drive-explorer__panes--split');
        expect(primary.get('.drive-panel__tree-list').text()).toContain('Compartilhados comigo');
        expect(secondary.get('.drive-panel__tree-list').text()).not.toContain('Compartilhados comigo');
        await secondary.get('.drive-panel__window-action [aria-label="Fechar árvore"]').trigger('click');
        expect(secondary.get('.drive-panel__layout').classes()).toContain('drive-panel__layout--tree-hidden');
        expect(primary.get('.drive-panel__layout').classes()).not.toContain('drive-panel__layout--tree-hidden');
        await primary.get('.drive-panel__view-mode[aria-label="Grade"]').trigger('click');
        expect(primary.get('.drive-panel__items').classes()).toContain('drive-panel__items--grid');
        expect(secondary.get('.drive-panel__items').classes()).toContain('drive-panel__items--details');
        const panes = wrapper.get('.drive-explorer__panes');
        Object.defineProperty(panes.element, 'getBoundingClientRect', { configurable: true, value: () => ({ left: 100, width: 1000 }) });
        await wrapper.get('.drive-explorer__pane-divider').trigger('keydown', { key: 'ArrowRight' });
        expect(panes.attributes('style')).toContain('--drive-primary-pane-width: 528px');
        wrapper.unmount();
    });

    it('enables folder creation in the secondary panel for an editable organization drive', async () => {
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/v1/drive/catalog') return Promise.resolve({ data: globalCatalog });
            if (url.endsWith('/tree')) return Promise.resolve({ data: { folders: [] } });
            return Promise.resolve({ data: { ...rootProjection, folders: [], files: [] } });
        });
        const wrapper = mountDrive(globalSurface);
        await flushPromises();
        await wrapper.get('[aria-label="Abrir segundo painel"]').trigger('click');
        await flushPromises();

        const tenantDrive = wrapper.findAll('.drive-panel__drive-header').find((item) => item.text().includes('Oficina Rubi'));
        await tenantDrive!.trigger('click');
        await flushPromises();

        const newFolderButtons = wrapper.findAll('[aria-label="Nova pasta"]');
        const secondaryNewFolder = newFolderButtons.at(-1)!;
        expect(secondaryNewFolder.attributes('disabled')).toBeUndefined();
        await secondaryNewFolder.trigger('click');
        expect(wrapper.get('[role="dialog"]').text()).toContain('Nova pasta');
    });

    it('opens the same transfer confirmation when an item is dragged from the secondary panel to the primary panel', async () => {
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/v1/drive/catalog') return Promise.resolve({ data: globalCatalog });
            if (url.endsWith('/tree')) return Promise.resolve({ data: { folders: rootProjection.folders } });
            return Promise.resolve({ data: rootProjection });
        });
        const wrapper = mountDrive(globalSurface);
        await flushPromises();
        await wrapper.get('[aria-label="Abrir segundo painel"]').trigger('click');
        await flushPromises();

        const secondaryItem = wrapper.findAll('[data-panel="secondary"] .drive-panel__item').at(-1)!;
        await secondaryItem.trigger('click');
        await secondaryItem.trigger('dragstart');
        await wrapper.get('[data-panel="primary"] .drive-panel__tree-item').trigger('drop', { dataTransfer: { files: [] } });
        await flushPromises();

        expect(wrapper.get('[role="dialog"]').text()).toContain('Transferir itens');
    });

    it('clears a personal folder projection before opening an empty organization drive', async () => {
        const personalFolderProjection = {
            ...rootProjection,
            location: { kind: 'folder', id: 7, displayName: 'Projetos', parentFolderId: null },
            breadcrumbs: [{ kind: 'folder', id: 7, displayName: 'Projetos', parentFolderId: null }],
        };
        const emptyOrganizationProjection = {
            ...rootProjection,
            location: { kind: 'root', id: null, displayName: 'Arquivos da organização', parentFolderId: null },
            breadcrumbs: [],
            folders: [],
            files: [],
        };
        vi.mocked(axios.get).mockResolvedValueOnce({ data: globalCatalog });
        queueInitialLoad(personalFolderProjection);
        const wrapper = mountDrive(globalSurface);
        await flushPromises();

        vi.mocked(axios.get).mockResolvedValueOnce({ data: { folders: [] } });
        vi.mocked(axios.get).mockResolvedValueOnce({ data: emptyOrganizationProjection });
        await wrapper.findAll('.drive-panel__drive-header').find((item) => item.text().includes('Oficina Rubi'))!.trigger('click');
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/42/drive/locations/root');
        expect(axios.get).not.toHaveBeenCalledWith('/api/v1/tenants/42/drive/folders/7');
        expect(wrapper.text()).not.toContain('Projetos');
        expect(wrapper.text()).not.toContain('dados exibidos podem estar desatualizados');
    });

    it('opens a directly shared folder at its authorized origin without exposing the origin root', async () => {
        const sharedFolder = { id: 91, kind: 'folder', displayName: 'Contratos compartilhados', originTarget: { kind: 'tenant', tenantId: 42 }, capabilities: { read: true, edit: false, trash: false } };
        const originTreeFolder = { id: sharedFolder.id, kind: sharedFolder.kind, displayName: sharedFolder.displayName, parentFolderId: null, logicalSizeBytes: null, detectedMimeType: null, modifiedAt: null, capabilities: sharedFolder.capabilities };
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/v1/drive/catalog') return Promise.resolve({ data: globalCatalog });
            if (url === '/api/v1/drive/personal/tree') return Promise.resolve({ data: { folders: [] } });
            if (url === '/api/v1/drive/personal/locations/root') return Promise.resolve({ data: { ...rootProjection, folders: [], files: [] } });
            if (url === '/api/v1/drive/shared-with-me') return Promise.resolve({ data: { folders: [sharedFolder], files: [] } });
            if (url === '/api/v1/tenants/42/drive/tree') return Promise.resolve({ data: { folders: [originTreeFolder] } });
            if (url === '/api/v1/tenants/42/drive/folders/91') return Promise.resolve({ data: { ...rootProjection, location: { kind: 'folder', id: 91, displayName: sharedFolder.displayName, parentFolderId: null }, breadcrumbs: [{ kind: 'folder', id: 91, displayName: sharedFolder.displayName, parentFolderId: null }], folders: [], files: [], capabilities: sharedFolder.capabilities } });
            return Promise.reject(new Error(`Unexpected endpoint: ${url}`));
        });
        const wrapper = mountDrive(globalSurface);
        await flushPromises();

        await wrapper.findAll('.drive-panel__tree-item').find((item) => item.text().includes('Compartilhados comigo'))!.trigger('click');
        await flushPromises();
        await wrapper.find('.drive-panel__item').trigger('dblclick');
        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/42/drive/tree');
        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/42/drive/folders/91');
        expect(wrapper.find('.drive-panel__collection-header .sr-only').text()).toBe('Contratos compartilhados');
        expect(wrapper.text()).not.toContain('Contrato.pdf');
    });

    it('removes a revoked drive root and clears its collection instead of retaining unauthorized content', async () => {
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/v1/drive/catalog') return Promise.resolve({ data: globalCatalog });
            if (url === '/api/v1/drive/personal/tree') return Promise.resolve({ data: { folders: [] } });
            if (url === '/api/v1/drive/personal/locations/root') return Promise.resolve({ data: rootProjection });
            if (url === '/api/v1/tenants/42/drive/tree' || url === '/api/v1/tenants/42/drive/locations/root') return Promise.reject({ response: { status: 403 } });
            return Promise.reject(new Error(`Unexpected endpoint: ${url}`));
        });
        const wrapper = mountDrive(globalSurface);
        await flushPromises();

        await wrapper.findAll('.drive-panel__drive-header').find((item) => item.text().includes('Oficina Rubi'))!.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Você não tem mais acesso a este local.');
        expect(wrapper.text()).not.toContain('Contrato.pdf');
        expect(wrapper.findAll('.drive-panel__drive-header').some((item) => item.text().includes('Oficina Rubi'))).toBe(false);
    });

    it('restores one opaque pending transfer and renders progress without reopening its dialog', async () => {
        window.localStorage.setItem('rinos-one.drive.active-transfer.v1', '01JTRANSFER');
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/v1/drive/catalog') return Promise.resolve({ data: globalCatalog });
            if (url === '/api/v1/drive/transfers/01JTRANSFER') return Promise.resolve({ data: { transferId: '01JTRANSFER', state: 'PENDING', mode: 'COPY', totalItems: 2, processedItems: 1, destinationTarget: { kind: 'personal', tenantId: null }, failureCode: null } });
            if (url === '/api/v1/drive/personal/tree') return Promise.resolve({ data: { folders: [] } });
            return Promise.resolve({ data: { ...rootProjection, folders: [], files: [] } });
        });
        const wrapper = mountDrive(globalSurface);
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/drive/transfers/01JTRANSFER');
        expect(wrapper.text()).toContain('Transferência PENDING: 1 de 2.');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('cancels a pending transfer and clears its resumable browser reference', async () => {
        window.localStorage.setItem('rinos-one.drive.active-transfer.v1', '01JCANCEL');
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/v1/drive/catalog') return Promise.resolve({ data: globalCatalog });
            if (url === '/api/v1/drive/transfers/01JCANCEL') return Promise.resolve({ data: { transferId: '01JCANCEL', state: 'PENDING', mode: 'COPY', totalItems: 2, processedItems: 0, destinationTarget: { kind: 'personal', tenantId: null }, failureCode: null } });
            if (url === '/api/v1/drive/personal/tree') return Promise.resolve({ data: { folders: [] } });
            return Promise.resolve({ data: { ...rootProjection, folders: [], files: [] } });
        });
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { transferId: '01JCANCEL', state: 'CANCELLED', mode: 'COPY', totalItems: 2, processedItems: 0, destinationTarget: { kind: 'personal', tenantId: null }, failureCode: null } });
        const wrapper = mountDrive(globalSurface);
        await flushPromises();

        await wrapper.get('.drive-explorer__transfer-progress button').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/v1/drive/transfers/01JCANCEL/cancel');
        expect(wrapper.text()).toContain('Transferência encerrada: CANCELLED.');
        expect(window.localStorage.getItem('rinos-one.drive.active-transfer.v1')).toBeNull();
        wrapper.unmount();
    });

    it('removes a stale resumable transfer reference when access is revoked without disclosing its items', async () => {
        window.localStorage.setItem('rinos-one.drive.active-transfer.v1', '01JREVOKED');
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/v1/drive/catalog') return Promise.resolve({ data: globalCatalog });
            if (url === '/api/v1/drive/transfers/01JREVOKED') return Promise.reject({ response: { status: 404 } });
            if (url === '/api/v1/drive/personal/tree') return Promise.resolve({ data: { folders: [] } });
            return Promise.resolve({ data: { ...rootProjection, folders: [], files: [] } });
        });
        const wrapper = mountDrive(globalSurface);
        await flushPromises();

        expect(wrapper.text()).toContain('O acompanhamento desta transferência não está mais disponível.');
        expect(wrapper.text()).not.toContain('Contrato.pdf');
        expect(window.localStorage.getItem('rinos-one.drive.active-transfer.v1')).toBeNull();
        wrapper.unmount();
    });

    it('announces a completed transfer without moving focus into a modal', async () => {
        window.localStorage.setItem('rinos-one.drive.active-transfer.v1', '01JCOMPLETE');
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/v1/drive/catalog') return Promise.resolve({ data: globalCatalog });
            if (url === '/api/v1/drive/transfers/01JCOMPLETE') return Promise.resolve({ data: { transferId: '01JCOMPLETE', state: 'COMPLETED', mode: 'COPY', totalItems: 1, processedItems: 1, destinationTarget: { kind: 'personal', tenantId: null }, failureCode: null } });
            if (url === '/api/v1/drive/personal/tree') return Promise.resolve({ data: { folders: [] } });
            return Promise.resolve({ data: { ...rootProjection, folders: [], files: [] } });
        });
        const wrapper = mountDrive(globalSurface);
        await flushPromises();

        expect(wrapper.text()).toContain('Transferência concluída. As coleções abertas foram atualizadas.');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(window.localStorage.getItem('rinos-one.drive.active-transfer.v1')).toBeNull();
        wrapper.unmount();
    });

    it('keeps prior content marked stale after an unavailable refresh', async () => {
        queueInitialLoad();
        const wrapper = mountDrive(personalSurface);
        await flushPromises();
        vi.mocked(axios.get).mockRejectedValueOnce(new Error('unavailable'));
        vi.mocked(axios.get).mockRejectedValueOnce(new Error('unavailable'));

        await wrapper.get('[aria-label="Atualizar"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Os dados exibidos podem estar desatualizados.');
        expect(wrapper.text()).toContain('Contrato.pdf');
    });

    it('opens the mobile tree drawer with a native accessible control', async () => {
        vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: true })));
        queueInitialLoad();
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        const trigger = wrapper.find('.drive-panel__window-action button');
        expect(trigger.attributes('aria-label')).toBe('Abrir árvore de pastas');
        await trigger.trigger('click');
        expect(wrapper.find('.drive-panel__tree').classes()).toContain('drive-panel__tree--open');
        expect(wrapper.get('[role="tree"]').attributes('aria-label')).toBe('Árvore');
        const entries = wrapper.findAll('[role="treeitem"]');
        await entries[0].trigger('keydown', { key: 'ArrowDown' });
        expect(document.activeElement).toBe(entries[1].element);
        await wrapper.get('.drive-panel__drawer-close').trigger('click');
        expect(document.activeElement).toBe(trigger.element);
        wrapper.unmount();
        vi.unstubAllGlobals();
    });

    it('keeps the selected collection mode as a local browser preference', async () => {
        window.localStorage.setItem('rinos-one.drive.view-mode.v2.local.primary', 'list');
        queueInitialLoad();
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        expect(wrapper.find('.drive-panel__items').classes()).toContain('drive-panel__items--list');
        const detailsMode = wrapper.find('.drive-panel__view-modes [title="Detalhes"]');
        await detailsMode.trigger('click');
        expect(detailsMode.attributes('aria-pressed')).toBe('true');
        expect(window.localStorage.getItem('rinos-one.drive.view-mode.v2.local.primary')).toBe('details');
    });

    it('offers grid, list and details as the only segmented collection views', async () => {
        queueInitialLoad();
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        const modes = wrapper.findAll('.drive-panel__view-mode');
        expect(modes).toHaveLength(3);
        expect(modes.map((mode) => mode.attributes('title'))).toEqual(['Grade', 'Lista', 'Detalhes']);
        expect(wrapper.text()).not.toContain('Tabela');
    });

    it('opens folders with Enter and returns focus to the current location', async () => {
        queueInitialLoad();
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { ...rootProjection, location: { kind: 'folder', id: 7, displayName: 'Projetos', parentFolderId: null }, breadcrumbs: [{ kind: 'folder', id: 7, displayName: 'Projetos', parentFolderId: null }] } });
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        await wrapper.findAll('.drive-panel__item')[0].trigger('keydown', { key: 'Enter' });
        await flushPromises();

        expect(axios.get).toHaveBeenLastCalledWith('/api/v1/drive/personal/folders/7');
        expect(document.activeElement).toBe(wrapper.get('.drive-panel__collection-header > .sr-only').element);
    });

    it('uses the active application locale for Drive controls', async () => {
        i18n.global.locale.value = 'en';
        queueInitialLoad();
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        expect(wrapper.get('main').attributes('aria-label')).toBe('Rinos Drive Personal');
        expect(wrapper.get('[aria-label="View mode"]').attributes('role')).toBe('group');
    });

    it('provides the Drive export and upload labels in every initial application language', () => {
        for (const currentLocale of ['pt-BR', 'en', 'es', 'fr'] as const) {
            i18n.global.locale.value = currentLocale;
            expect(i18n.global.t('access.drive.exportTitle')).not.toBe('access.drive.exportTitle');
            expect(i18n.global.t('access.drive.cancelUpload')).not.toBe('access.drive.cancelUpload');
        }
    });

    it('creates a folder only from an editable current location and refreshes the projection', async () => {
        queueInitialLoad();
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { id: 9, kind: 'folder', displayName: 'Fiscal', parentFolderId: null } });
        queueInitialLoad({ ...rootProjection, folders: [...rootProjection.folders, { ...rootProjection.folders[0], id: 9, displayName: 'Fiscal' }] });
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        await wrapper.get('[aria-label="Nova pasta"]').trigger('click');
        await wrapper.get('input:not([type="file"])').setValue('Fiscal');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/v1/drive/personal/folders', { displayName: 'Fiscal', parentFolderId: null });
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('confirms a selected-item trash operation before refreshing the collection', async () => {
        queueInitialLoad();
        vi.mocked(axios.post).mockResolvedValueOnce({ data: null });
        queueInitialLoad({ ...rootProjection, files: [] });
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        await wrapper.findAll('.drive-panel__item')[1].trigger('click');
        await wrapper.get('[aria-label="Mover para a lixeira"]').trigger('click');
        expect(wrapper.text()).toContain('Mover itens para a lixeira?');
        await wrapper.get('[role="dialog"] form').trigger('submit');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/v1/drive/personal/items/trash', { items: [{ type: 'file', id: 8 }] });
        expect(wrapper.text()).not.toContain('Contrato.pdf');
    });

    it('moves one editable selection through the authorized destination dialog', async () => {
        queueInitialLoad();
        vi.mocked(axios.post).mockResolvedValueOnce({ data: null });
        queueInitialLoad({ ...rootProjection, files: [] });
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        await wrapper.findAll('.drive-panel__item')[1].trigger('click');
        await wrapper.get('[aria-label="Mover"]').trigger('click');
        await wrapper.get('[role="dialog"] select').setValue('7');
        await wrapper.get('[role="dialog"] form').trigger('submit');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/v1/drive/personal/files/8/move', { destinationFolderId: 7 });
    });

    it('opens only the selected item in the private details panel and closes it with Escape', async () => {
        queueInitialLoad();
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { item: rootProjection.files[0], location: rootProjection.location, capabilities, metadata: [] } });
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        await wrapper.findAll('.drive-panel__item')[1].trigger('click');
        await wrapper.get('[aria-label="Detalhes"]').trigger('click');
        await flushPromises();

        expect(axios.get).toHaveBeenLastCalledWith('/api/v1/drive/personal/items/file/8/details');
        expect(wrapper.get('.drive-details-panel').attributes('aria-label')).toBe('Detalhes do item');
        expect(wrapper.text()).toContain('Contrato.pdf');
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();
        expect(wrapper.find('.drive-details-panel').exists()).toBe(false);
    });

    it('queues a multi-item private export directly from the download command', async () => {
        queueInitialLoad();
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { exportId: '01JEXPORT', state: 'PENDING', expiresAt: '2026-09-29T12:00:00Z' } });
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        expect(wrapper.findAll('.drive-panel__tree-item').some((item) => item.text().includes('Exportações'))).toBe(false);

        await wrapper.findAll('.drive-panel__item')[0].trigger('click');
        await wrapper.findAll('.drive-panel__item')[1].trigger('click', { ctrlKey: true });
        await wrapper.get('[aria-label="Baixar"]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/api/v1/drive/personal/exports', { items: [{ type: 'folder', id: 7 }, { type: 'file', id: 8 }] });

        const exportsRoot = wrapper.findAll('.drive-panel__tree-item').find((item) => item.text().includes('Exportações'))!;
        expect(exportsRoot.exists()).toBe(true);
        await exportsRoot.trigger('click');
        expect(wrapper.text()).toContain('Aguardando processamento');
    });

    it('keeps the collection available when access to a details projection is denied', async () => {
        queueInitialLoad();
        vi.mocked(axios.get).mockRejectedValueOnce({ response: { status: 403 } });
        const wrapper = mountDrive(personalSurface);
        await flushPromises();

        await wrapper.findAll('.drive-panel__item')[1].trigger('click');
        await wrapper.get('[aria-label="Detalhes"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Você não tem mais acesso aos detalhes deste item.');
        expect(wrapper.text()).toContain('Contrato.pdf');
    });
});
