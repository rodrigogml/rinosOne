import { describe, expect, it } from 'vitest';
import { parseDriveDetails, parseDriveLocationProjection, parseDriveTree } from '../../../resources/js/drive/driveWorkspaceApi';

const item = { id: 7, kind: 'folder', displayName: 'Projetos', parentFolderId: null, logicalSizeBytes: null, detectedMimeType: null, modifiedAt: '2026-09-28T10:00:00Z', capabilities: { read: true, edit: false, trash: false } };

describe('Drive workspace API parsers', () => {
    it('accepts only complete safe location projections', () => {
        expect(parseDriveLocationProjection({ location: { kind: 'root', id: null, displayName: 'Meus arquivos', parentFolderId: null }, breadcrumbs: [], folders: [item], files: [], capabilities: item.capabilities, usage: { workspaceBytes: 10, systemManagedBytes: 0, trashBytes: 0, totalBytes: 10 } })).toMatchObject({ folders: [item] });
    });

    it('rejects incomplete or unsafe location projections', () => {
        expect(parseDriveLocationProjection({ location: { kind: 'root', id: null, displayName: 'Meus arquivos', parentFolderId: null }, breadcrumbs: [], folders: [{ ...item, capabilities: { read: true } }], files: [], capabilities: item.capabilities, usage: { workspaceBytes: 10, systemManagedBytes: 0, trashBytes: 0, totalBytes: 10 } })).toBeNull();
        expect(parseDriveTree({ folders: [{ ...item, kind: 'file' }] })).toBeNull();
        expect(parseDriveDetails({ item, location: { kind: 'folder', id: 7, displayName: 'Projetos', parentFolderId: null }, capabilities: item.capabilities, metadata: {} })).toBeNull();
    });
});
