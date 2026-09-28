export interface DriveCapabilities { read: boolean; edit: boolean; trash: boolean; }
export interface DriveItem {
    id: number;
    kind: 'folder' | 'file';
    displayName: string;
    parentFolderId: number | null;
    logicalSizeBytes: number | null;
    detectedMimeType: string | null;
    modifiedAt: string | null;
    capabilities: DriveCapabilities;
}
export interface DriveLocation { kind: 'root' | 'folder' | 'trash'; id: number | null; displayName: string; parentFolderId: number | null; }
export interface DriveUsage { workspaceBytes: number; systemManagedBytes: number; trashBytes: number; totalBytes: number; }
export interface DriveLocationProjection { location: DriveLocation; breadcrumbs: DriveLocation[]; folders: DriveItem[]; files: DriveItem[]; capabilities: DriveCapabilities; usage: DriveUsage; purgeAfter?: string | null; }
export interface DriveDetailsProjection { item: DriveItem; location: DriveLocation; capabilities: DriveCapabilities; metadata: unknown[]; }

function isPositiveInteger(value: unknown): value is number { return typeof value === 'number' && Number.isInteger(value) && value > 0; }
function isNullablePositiveInteger(value: unknown): value is number | null { return value === null || isPositiveInteger(value); }
function isNullableNonNegativeInteger(value: unknown): value is number | null { return value === null || (typeof value === 'number' && Number.isInteger(value) && value >= 0); }
function isNullableString(value: unknown): value is string | null { return value === null || typeof value === 'string'; }
function isRecord(value: unknown): value is Record<string, unknown> { return value !== null && typeof value === 'object' && !Array.isArray(value); }

function parseCapabilities(value: unknown): DriveCapabilities | null {
    if (!isRecord(value)) return null;
    const { read, edit, trash } = value;
    if (typeof read !== 'boolean' || typeof edit !== 'boolean' || typeof trash !== 'boolean') return null;
    return { read, edit, trash };
}

export function parseDriveItem(value: unknown): DriveItem | null {
    if (!isRecord(value)) return null;
    const { id, kind, displayName, parentFolderId, logicalSizeBytes, detectedMimeType, modifiedAt } = value;
    if (!isPositiveInteger(id) || (kind !== 'folder' && kind !== 'file') || typeof displayName !== 'string'
        || !isNullablePositiveInteger(parentFolderId) || !isNullableNonNegativeInteger(logicalSizeBytes)
        || !isNullableString(detectedMimeType) || !isNullableString(modifiedAt)) return null;
    const capabilities = parseCapabilities(value.capabilities);
    return capabilities === null ? null : { id, kind, displayName, parentFolderId, logicalSizeBytes, detectedMimeType, modifiedAt, capabilities };
}

export function parseDriveLocation(value: unknown): DriveLocation | null {
    if (!isRecord(value) || (value.kind !== 'root' && value.kind !== 'folder' && value.kind !== 'trash') || !isNullablePositiveInteger(value.id)
        || typeof value.displayName !== 'string' || !isNullablePositiveInteger(value.parentFolderId)) return null;
    return { kind: value.kind, id: value.id, displayName: value.displayName, parentFolderId: value.parentFolderId };
}

function parseUsage(value: unknown): DriveUsage | null {
    if (!isRecord(value)) return null;
    const { workspaceBytes, systemManagedBytes, trashBytes, totalBytes } = value;
    if (!Number.isInteger(workspaceBytes) || !Number.isInteger(systemManagedBytes) || !Number.isInteger(trashBytes) || !Number.isInteger(totalBytes)) return null;
    return { workspaceBytes: workspaceBytes as number, systemManagedBytes: systemManagedBytes as number, trashBytes: trashBytes as number, totalBytes: totalBytes as number };
}

export function parseDriveLocationProjection(value: unknown): DriveLocationProjection | null {
    if (!isRecord(value) || !Array.isArray(value.breadcrumbs) || !Array.isArray(value.folders) || !Array.isArray(value.files)) return null;
    const location = parseDriveLocation(value.location);
    const capabilities = parseCapabilities(value.capabilities);
    const usage = parseUsage(value.usage);
    const breadcrumbs = value.breadcrumbs.map(parseDriveLocation);
    const folders = value.folders.map(parseDriveItem);
    const files = value.files.map(parseDriveItem);
    if (location === null || capabilities === null || usage === null || breadcrumbs.some((item) => item === null) || folders.some((item) => item === null) || files.some((item) => item === null)) return null;
    if (!isNullableString(value.purgeAfter) && value.purgeAfter !== undefined) return null;
    return { location, breadcrumbs: breadcrumbs as DriveLocation[], folders: folders as DriveItem[], files: files as DriveItem[], capabilities, usage, ...(value.purgeAfter === undefined ? {} : { purgeAfter: value.purgeAfter }) };
}

export function parseDriveTree(value: unknown): DriveItem[] | null {
    if (!isRecord(value) || !Array.isArray(value.folders)) return null;
    const folders = value.folders.map(parseDriveItem);
    return folders.some((item) => item === null || item.kind !== 'folder') ? null : folders as DriveItem[];
}

export function parseDriveDetails(value: unknown): DriveDetailsProjection | null {
    if (!isRecord(value) || !Array.isArray(value.metadata)) return null;
    const item = parseDriveItem(value.item);
    const location = parseDriveLocation(value.location);
    const capabilities = parseCapabilities(value.capabilities);
    return item === null || location === null || capabilities === null ? null : { item, location, capabilities, metadata: value.metadata };
}
