import { describe, expect, it } from 'vitest';
import { parseAuthorizedFolders } from '../../../resources/js/workspace/authorizedFoldersApi';

describe('authorized folders API parser', () => {
    it('accepts only the protected folder contract', () => {
        expect(parseAuthorizedFolders({ folders: [{ id: 7, parentFolderId: null, displayName: 'Shared' }] })).toEqual([{ id: 7, parentFolderId: null, displayName: 'Shared' }]);
        expect(parseAuthorizedFolders({ folders: [{ id: '7', parentFolderId: null, displayName: 'Shared' }] })).toBeNull();
    });
});
