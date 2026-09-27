export interface AuthorizedFolder { id: number; parentFolderId: number | null; displayName: string; }

export function parseAuthorizedFolders(payload: unknown): AuthorizedFolder[] | null {
    if (!payload || typeof payload !== 'object' || !Array.isArray((payload as { folders?: unknown }).folders)) return null;
    const folders = (payload as { folders: unknown[] }).folders;
    return folders.every((folder) => folder && typeof folder === 'object'
        && Number.isInteger((folder as AuthorizedFolder).id) && (folder as AuthorizedFolder).id > 0
        && (typeof (folder as AuthorizedFolder).parentFolderId === 'number' || (folder as AuthorizedFolder).parentFolderId === null)
        && typeof (folder as AuthorizedFolder).displayName === 'string') ? folders as AuthorizedFolder[] : null;
}
