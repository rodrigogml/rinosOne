import { describe, expect, it } from 'vitest';
import { invalidDriveDrop, sameDrive, type DrivePanelContext, type DriveDropDestination } from '../../../resources/js/drive/drivePanelTypes';
import type { DriveItem } from '../../../resources/js/drive/driveWorkspaceApi';

const item: DriveItem = { id: 7, kind: 'folder', displayName: 'Pasta', parentFolderId: null,
    logicalSizeBytes: 0, detectedMimeType: null, modifiedAt: null, capabilities: { read: true, edit: true, trash: true } };
const source: DrivePanelContext = { panelId: 'primary', target: { kind: 'personal' }, items: [item], tree: [item],
    projection: { location: { kind: 'root', id: null, displayName: 'Meu Drive', parentFolderId: null },
        breadcrumbs: [], folders: [item], files: [], capabilities: item.capabilities,
        usage: { workspaceBytes: 0, systemManagedBytes: 0, trashBytes: 0, totalBytes: 0 } } };
const destination: DriveDropDestination = { target: { kind: 'personal' }, folderId: 9, displayName: 'Destino', editable: true };

describe('Drive drop validation', () => {
    it('distinguishes personal and organization targets, including organization ids', () => {
        expect(sameDrive({ kind: 'personal' }, { kind: 'personal' })).toBe(true);
        expect(sameDrive({ kind: 'personal' }, { kind: 'tenant', tenantId: 7 })).toBe(false);
        expect(sameDrive({ kind: 'tenant', tenantId: 7 }, { kind: 'tenant', tenantId: 7 })).toBe(true);
        expect(sameDrive({ kind: 'tenant', tenantId: 7 }, { kind: 'tenant', tenantId: 8 })).toBe(false);
    });
    it('rejects empty selection, read-only destinations and trash sources', () => {
        expect(invalidDriveDrop({ ...source, items: [] }, destination)).toBe(true);
        expect(invalidDriveDrop(source, { ...destination, editable: false })).toBe(true);
        expect(invalidDriveDrop({ ...source, projection: { ...source.projection, location: { ...source.projection.location, kind: 'trash' } } }, destination)).toBe(true);
    });
    it('rejects the source parent, folder itself and its descendants', () => {
        expect(invalidDriveDrop(source, { ...destination, folderId: null })).toBe(true);
        expect(invalidDriveDrop(source, { ...destination, folderId: item.id })).toBe(true);
        expect(invalidDriveDrop({ ...source, tree: [item, { ...item, id: 9, parentFolderId: item.id }] }, destination)).toBe(true);
        const nested = { ...source, projection: { ...source.projection, location: { kind: 'folder' as const, id: 9, parentFolderId: null, displayName: 'Origem' } } };
        expect(invalidDriveDrop(nested, destination)).toBe(true);
    });
    it('does not confuse folder ids from different drives', () => {
        expect(invalidDriveDrop(source, { ...destination, target: { kind: 'tenant', tenantId: 7 }, folderId: item.id })).toBe(false);
    });
    it('accepts unrelated locations and files without interpreting file ids as folders', () => {
        expect(invalidDriveDrop(source, destination)).toBe(false);
        expect(invalidDriveDrop({ ...source, items: [{ ...item, kind: 'file' }] }, { ...destination, folderId: item.id })).toBe(false);
    });
    it('terminates safely if a stale tree contains a cyclic parent relation', () => {
        expect(invalidDriveDrop({ ...source, tree: [{ ...item, id: 9, parentFolderId: 10 }, { ...item, id: 10, parentFolderId: 9 }] }, destination)).toBe(false);
    });
});
