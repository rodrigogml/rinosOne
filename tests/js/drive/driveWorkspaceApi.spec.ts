import { describe, expect, it } from 'vitest';
import axios from 'axios';
import { loadDriveDetails, loadDriveLocation, loadDriveTree, parseDriveDetails, parseDriveExport, parseDriveLocationProjection, parseDriveTree } from '../../../resources/js/drive/driveWorkspaceApi';
import { beforeEach, vi } from 'vitest';

vi.mock('axios', () => ({ default: { get: vi.fn() } }));

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
