import { describe, expect, it } from 'vitest';
import axios from 'axios';
import { cancelDriveTransfer, loadDriveCatalog, loadDriveDetails, loadDriveLocation, loadDriveTransfer, loadDriveTree, loadSharedWithMe, parseDriveCatalog, parseDriveDetails, parseDriveExport, parseDriveLocationProjection, parseDriveTransfer, parseDriveTree, parseSharedWithMe, requestDriveTransfer } from '../../../resources/js/drive/driveWorkspaceApi';
import { beforeEach, vi } from 'vitest';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const item = { id: 7, kind: 'folder', displayName: 'Projetos', parentFolderId: null, logicalSizeBytes: null, detectedMimeType: null, modifiedAt: '2026-09-28T10:00:00Z', capabilities: { read: true, edit: false, trash: false } };

describe('Drive workspace API parsers', () => {
    beforeEach(() => vi.clearAllMocks());
    it('accepts only complete safe location projections', () => {
        expect(parseDriveLocationProjection({ location: { kind: 'root', id: null, displayName: 'Meus arquivos', parentFolderId: null }, breadcrumbs: [], folders: [item], files: [], capabilities: item.capabilities, usage: { workspaceBytes: 10, systemManagedBytes: 0, trashBytes: 0, totalBytes: 10 } })).toMatchObject({ folders: [item] });
    });

    it('rejects incomplete or unsafe location projections', () => {
        expect(parseDriveLocationProjection({ location: { kind: 'root', id: null, displayName: 'Meus arquivos', parentFolderId: null }, breadcrumbs: [], folders: [{ ...item, capabilities: { read: true } }], files: [], capabilities: item.capabilities, usage: { workspaceBytes: 10, systemManagedBytes: 0, trashBytes: 0, totalBytes: 10 } })).toBeNull();
        expect(parseDriveTree({ folders: [{ ...item, kind: 'file' }] })).toBeNull();
        expect(parseDriveDetails({ item, location: { kind: 'folder', id: 7, displayName: 'Projetos', parentFolderId: null }, capabilities: item.capabilities, metadata: {} })).toBeNull();
    });

    it('accepts only a known opaque export state projection', () => {
        expect(parseDriveExport({ exportId: '01JEXPORT', state: 'PROCESSING', expiresAt: '2026-09-29T12:00:00Z' })).toEqual({ exportId: '01JEXPORT', state: 'PROCESSING', expiresAt: '2026-09-29T12:00:00Z' });
        expect(parseDriveExport({ exportId: '01JEXPORT', state: 'UNKNOWN', expiresAt: null })).toBeNull();
        expect(parseDriveExport({ exportId: '', state: 'READY', expiresAt: null })).toBeNull();
    });

    it('accepts a typed catalog and direct shares without path reconstruction', () => {
        const catalog = { drives: [{ target: { kind: 'personal', tenantId: null }, displayName: 'Meu Drive', category: 'PERSONAL', usage: { workspaceBytes: 0, systemManagedBytes: 0, trashBytes: 0, totalBytes: 0 } }], sharedWithMe: { kind: 'shared-with-me', displayName: 'Compartilhados comigo' } };
        expect(parseDriveCatalog(catalog)).toMatchObject({ drives: [{ target: { kind: 'personal' } }] });
        expect(parseDriveCatalog({ ...catalog, drives: [{ ...catalog.drives[0], target: { kind: 'tenant', tenantId: null } }] })).toBeNull();
        expect(parseSharedWithMe({ folders: [{ id: 3, kind: 'folder', displayName: 'Contrato', originTarget: { kind: 'tenant', tenantId: 9 }, capabilities: { read: true, edit: false, trash: false } }], files: [] })).toMatchObject({ folders: [{ id: 3 }] });
    });

    it('parses and sends opaque transfer contracts with an idempotency key', async () => {
        const transfer = { transferId: '01JTRANSFER', state: 'PENDING', mode: 'COPY', totalItems: 1, processedItems: 0, destinationTarget: { kind: 'tenant', tenantId: 7 }, failureCode: null };
        expect(parseDriveTransfer(transfer)).toMatchObject({ transferId: '01JTRANSFER', state: 'PENDING' });
        expect(parseDriveTransfer({ ...transfer, state: 'UNKNOWN' })).toBeNull();
        vi.mocked(axios.post).mockResolvedValueOnce({ data: transfer });
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { ...transfer, state: 'PROCESSING' } });
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { ...transfer, state: 'CANCELLED' } });

        await requestDriveTransfer({ sourceTarget: { kind: 'personal', folderId: null }, destinationTarget: { kind: 'tenant', tenantId: 7, folderId: 3 }, items: [{ id: 8, kind: 'file' }], mode: 'COPY' });
        await loadDriveTransfer('01JTRANSFER');
        await cancelDriveTransfer('01JTRANSFER');

        expect(axios.post).toHaveBeenNthCalledWith(1, '/api/v1/drive/transfers', { sourceTarget: { kind: 'personal', tenantId: null, folderId: null }, destinationTarget: { kind: 'tenant', tenantId: 7, folderId: 3 }, items: [{ type: 'file', id: 8 }], mode: 'COPY' }, { headers: { 'Idempotency-Key': expect.any(String) } });
        expect(axios.get).toHaveBeenCalledWith('/api/v1/drive/transfers/01JTRANSFER');
        expect(axios.post).toHaveBeenNthCalledWith(2, '/api/v1/drive/transfers/01JTRANSFER/cancel');
    });

    it('loads catalog and direct shares from their dedicated safe endpoints', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { drives: [], sharedWithMe: { kind: 'shared-with-me', displayName: 'Compartilhados comigo' } } });
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { folders: [], files: [] } });
        await loadDriveCatalog();
        await loadSharedWithMe();
        expect(axios.get).toHaveBeenNthCalledWith(1, '/api/v1/drive/catalog');
        expect(axios.get).toHaveBeenNthCalledWith(2, '/api/v1/drive/shared-with-me');
    });

    it('keeps workspace routes derived from a typed personal or tenant target', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { folders: [] } });
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { location: { kind: 'trash', id: null, displayName: 'Lixeira', parentFolderId: null }, breadcrumbs: [], folders: [], files: [], capabilities: item.capabilities, usage: { workspaceBytes: 0, systemManagedBytes: 0, trashBytes: 0, totalBytes: 0 } } });
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { item, location: { kind: 'root', id: null, displayName: 'Meus arquivos', parentFolderId: null }, capabilities: item.capabilities, metadata: [] } });

        await loadDriveTree({ kind: 'personal' });
        await loadDriveLocation({ kind: 'tenant', tenantId: 7 }, { kind: 'trash', id: null, displayName: 'Lixeira', parentFolderId: null });
        await loadDriveDetails({ kind: 'tenant', tenantId: 7 }, { id: item.id, kind: 'folder' });

        expect(axios.get).toHaveBeenNthCalledWith(1, '/api/v1/drive/personal/tree');
        expect(axios.get).toHaveBeenNthCalledWith(2, '/api/v1/tenants/7/drive/trash');
        expect(axios.get).toHaveBeenNthCalledWith(3, '/api/v1/tenants/7/drive/items/folder/7/details');
    });
});
