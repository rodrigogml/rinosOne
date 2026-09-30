import axios from 'axios';

export type DriveWorkspaceTarget = { kind: 'personal' } | { kind: 'tenant'; tenantId: number };
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
export interface DriveCatalogEntry { target: DriveWorkspaceTarget; displayName: string; category: 'PERSONAL' | 'TENANT'; usage: DriveUsage; }
export interface DriveCatalogProjection { drives: DriveCatalogEntry[]; sharedWithMe: { kind: 'shared-with-me'; displayName: string }; }
export interface SharedDriveItem { id: number; kind: 'folder' | 'file'; displayName: string; logicalSizeBytes?: number | null; detectedMimeType?: string | null; originTarget: DriveWorkspaceTarget; capabilities: DriveCapabilities; }
export interface SharedWithMeProjection { folders: SharedDriveItem[]; files: SharedDriveItem[]; }
export type DriveTransferMode = 'COPY' | 'MOVE';
export type DriveTransferTarget = DriveWorkspaceTarget & { folderId: number | null; };
export interface DriveTransferRequest { sourceTarget: DriveTransferTarget; destinationTarget: DriveTransferTarget; items: Array<Pick<DriveItem, 'id' | 'kind'>>; mode: DriveTransferMode; }
export interface DriveTransfer { transferId: string; state: 'PENDING' | 'PROCESSING' | 'COMPLETED' | 'FAILED' | 'CANCELLED'; mode: DriveTransferMode; totalItems: number; processedItems: number; destinationTarget: DriveWorkspaceTarget; failureCode: string | null; }

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

function parseTarget(value: unknown): DriveWorkspaceTarget | null {
    if (!isRecord(value) || (value.kind !== 'personal' && value.kind !== 'tenant')) return null;
    if (value.kind === 'personal') return value.tenantId === null ? { kind: 'personal' } : null;
    return isPositiveInteger(value.tenantId) ? { kind: 'tenant', tenantId: value.tenantId } : null;
}

export function parseDriveCatalog(value: unknown): DriveCatalogProjection | null {
    if (!isRecord(value) || !Array.isArray(value.drives) || !isRecord(value.sharedWithMe) || value.sharedWithMe.kind !== 'shared-with-me' || typeof value.sharedWithMe.displayName !== 'string') return null;
    const drives = value.drives.map((entry): DriveCatalogEntry | null => {
        if (!isRecord(entry) || typeof entry.displayName !== 'string' || (entry.category !== 'PERSONAL' && entry.category !== 'TENANT')) return null;
        const target = parseTarget(entry.target);
        const usage = parseUsage(entry.usage);
        if (target === null || usage === null || (entry.category === 'PERSONAL') !== (target.kind === 'personal')) return null;

        return { target, displayName: entry.displayName, category: entry.category, usage };
    });
    return drives.some((entry) => entry === null) ? null : { drives: drives as DriveCatalogEntry[], sharedWithMe: { kind: 'shared-with-me', displayName: value.sharedWithMe.displayName } };
}

function parseSharedItem(value: unknown): SharedDriveItem | null {
    if (!isRecord(value) || !isPositiveInteger(value.id) || (value.kind !== 'folder' && value.kind !== 'file') || typeof value.displayName !== 'string') return null;
    const originTarget = parseTarget(value.originTarget);
    const capabilities = parseCapabilities(value.capabilities);
    if (originTarget === null || capabilities === null || !capabilities.read) return null;
    if (value.kind === 'file' && (!isNullableNonNegativeInteger(value.logicalSizeBytes) || !isNullableString(value.detectedMimeType))) return null;

    return {
        id: value.id, kind: value.kind, displayName: value.displayName, originTarget, capabilities,
        ...(value.kind === 'file' ? { logicalSizeBytes: value.logicalSizeBytes as number | null, detectedMimeType: value.detectedMimeType as string | null } : {}),
    };
}

export function parseSharedWithMe(value: unknown): SharedWithMeProjection | null {
    if (!isRecord(value) || !Array.isArray(value.folders) || !Array.isArray(value.files)) return null;
    const folders = value.folders.map(parseSharedItem);
    const files = value.files.map(parseSharedItem);
    if (folders.some((item) => item === null || item.kind !== 'folder') || files.some((item) => item === null || item.kind !== 'file')) return null;

    return { folders: folders as SharedDriveItem[], files: files as SharedDriveItem[] };
}

export function parseDriveTransfer(value: unknown): DriveTransfer | null {
    if (!isRecord(value) || typeof value.transferId !== 'string' || !['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'CANCELLED'].includes(String(value.state)) || (value.mode !== 'COPY' && value.mode !== 'MOVE') || !isNullableNonNegativeInteger(value.totalItems) || !isNullableNonNegativeInteger(value.processedItems) || !isNullableString(value.failureCode)) return null;
    const destinationTarget = parseTarget(value.destinationTarget);
    if (destinationTarget === null || value.totalItems === null || value.processedItems === null || value.processedItems > value.totalItems) return null;

    return { transferId: value.transferId, state: value.state as DriveTransfer['state'], mode: value.mode, totalItems: value.totalItems, processedItems: value.processedItems, destinationTarget, failureCode: value.failureCode };
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

function workspacePrefix(target: DriveWorkspaceTarget): string {
    return target.kind === 'personal'
        ? '/api/v1/drive/personal'
        : `/api/v1/tenants/${target.tenantId}/drive`;
}

async function getProjection<T>(path: string, parser: (value: unknown) => T | null): Promise<T> {
    const response = await axios.get(path);
    const parsed = parser(response.data);
    if (parsed === null) throw new Error('A resposta do Rinos Drive não possui o formato esperado.');

    return parsed;
}

/** Carrega raízes disponíveis sem confiar em contexto de tenant mantido na interface. */
export function loadDriveCatalog(): Promise<DriveCatalogProjection> {
    return getProjection('/api/v1/drive/catalog', parseDriveCatalog);
}

/** Carrega somente concessões diretas, sem reconstruir o caminho de origem. */
export function loadSharedWithMe(): Promise<SharedWithMeProjection> {
    return getProjection('/api/v1/drive/shared-with-me', parseSharedWithMe);
}

function transferTarget(target: DriveTransferTarget): { kind: DriveWorkspaceTarget['kind']; tenantId: number | null; folderId?: number | null } {
    return { kind: target.kind, tenantId: target.kind === 'tenant' ? target.tenantId : null, ...(Object.hasOwn(target, 'folderId') ? { folderId: target.folderId } : {}) };
}
function idempotencyKey(): string { return globalThis.crypto?.randomUUID?.() ?? `00000000-0000-4000-8000-${Date.now().toString().padStart(12, '0').slice(-12)}`; }

/** Starts an opaque, idempotent logical transfer. The server resolves every target again. */
export async function requestDriveTransfer(request: DriveTransferRequest): Promise<DriveTransfer> {
    const parsed = parseDriveTransfer((await axios.post('/api/v1/drive/transfers', { sourceTarget: transferTarget(request.sourceTarget), destinationTarget: transferTarget(request.destinationTarget), items: request.items.map((item) => ({ type: item.kind, id: item.id })), mode: request.mode }, { headers: { 'Idempotency-Key': idempotencyKey() } })).data);
    if (parsed === null) throw new Error('A resposta de transferência do Rinos Drive não possui o formato esperado.');
    return parsed;
}

export function loadDriveTransfer(transferId: string): Promise<DriveTransfer> { return getProjection(`/api/v1/drive/transfers/${transferId}`, parseDriveTransfer); }
export async function cancelDriveTransfer(transferId: string): Promise<DriveTransfer> {
    const parsed = parseDriveTransfer((await axios.post(`/api/v1/drive/transfers/${transferId}/cancel`)).data);
    if (parsed === null) throw new Error('A resposta de transferência do Rinos Drive não possui o formato esperado.');
    return parsed;
}

/** Carrega somente as pastas que podem integrar a árvore visível do alvo atual. */
export function loadDriveTree(target: DriveWorkspaceTarget): Promise<DriveItem[]> {
    return getProjection(`${workspacePrefix(target)}/tree`, parseDriveTree);
}

/** Carrega uma coleção sem aceitar caminho, proprietário ou backend vindos da interface. */
export function loadDriveLocation(target: DriveWorkspaceTarget, location: DriveLocation): Promise<DriveLocationProjection> {
    const prefix = workspacePrefix(target);
    const path = location.kind === 'root'
        ? `${prefix}/locations/root`
        : location.kind === 'trash'
            ? `${prefix}/trash`
            : `${prefix}/folders/${location.id}`;

    return getProjection(path, parseDriveLocationProjection);
}

/** Consulta os detalhes sanitizados de um item já visível na coleção atual. */
export function loadDriveDetails(target: DriveWorkspaceTarget, item: Pick<DriveItem, 'id' | 'kind'>): Promise<DriveDetailsProjection> {
    return getProjection(`${workspacePrefix(target)}/items/${item.kind}/${item.id}/details`, parseDriveDetails);
}

/** Cria uma pasta somente na localização autorizada já resolvida pelo servidor. */
export async function createDriveFolder(target: DriveWorkspaceTarget, displayName: string, parentFolderId: number | null): Promise<void> {
    await axios.post(`${workspacePrefix(target)}/folders`, { displayName, parentFolderId });
}

/** Moves the selected, server-authorized items to the workspace trash. */
export async function trashDriveItems(target: DriveWorkspaceTarget, items: Array<Pick<DriveItem, 'id' | 'kind'>>): Promise<void> {
    await axios.post(`${workspacePrefix(target)}/items/trash`, {
        items: items.map((item) => ({ type: item.kind, id: item.id })),
    });
}

/** Moves one item; the API remains responsible for authorization, cycles and name conflicts. */
export async function moveDriveItem(target: DriveWorkspaceTarget, item: Pick<DriveItem, 'id' | 'kind'>, destinationFolderId: number | null): Promise<void> {
    const resource = item.kind === 'folder' ? 'folders' : 'files';
    await axios.post(`${workspacePrefix(target)}/${resource}/${item.id}/move`, { destinationFolderId });
}

export async function restoreDriveItems(target: DriveWorkspaceTarget, items: Array<Pick<DriveItem, 'id' | 'kind'>>): Promise<void> {
    await axios.post(`${workspacePrefix(target)}/items/restore`, { items: items.map((item) => ({ type: item.kind, id: item.id })) });
}

export async function releaseDriveItems(target: DriveWorkspaceTarget, items: Array<Pick<DriveItem, 'id' | 'kind'>>): Promise<void> {
    await axios.post(`${workspacePrefix(target)}/items/release`, { items: items.map((item) => ({ type: item.kind, id: item.id })), confirmation: true });
}

export async function uploadDriveFile(target: DriveWorkspaceTarget, file: File, parentFolderId: number | null, signal: AbortSignal, onProgress: (percent: number) => void): Promise<void> {
    const form = new FormData();
    form.append('files[]', file);
    if (parentFolderId !== null) form.append('parentFolderId', String(parentFolderId));
    await axios.post(`${workspacePrefix(target)}/uploads`, form, { signal, onUploadProgress: (event) => onProgress(event.total ? Math.round((event.loaded / event.total) * 100) : 0) });
}

/** Returns the private, authenticated download route for one visible file possession. */
export function driveDownloadUrl(target: DriveWorkspaceTarget, possessionId: number): string {
    return `${workspacePrefix(target)}/files/${possessionId}/download`;
}

export interface DriveExportProjection { exportId: string; state: string; expiresAt: string | null; }

export function parseDriveExport(value: unknown): DriveExportProjection | null {
    if (!isRecord(value) || typeof value.exportId !== 'string' || value.exportId.length === 0 || !isNullableString(value.expiresAt)) return null;
    if (value.state !== 'PENDING' && value.state !== 'PROCESSING' && value.state !== 'READY' && value.state !== 'FAILED' && value.state !== 'CANCELLED') return null;

    return { exportId: value.exportId, state: value.state, expiresAt: value.expiresAt };
}

export async function requestDriveExport(target: DriveWorkspaceTarget, items: Array<Pick<DriveItem, 'id' | 'kind'>>): Promise<DriveExportProjection> {
    const response = await axios.post(`${workspacePrefix(target)}/exports`, { items: items.map((item) => ({ type: item.kind, id: item.id })) });
    const parsed = parseDriveExport(response.data);
    if (parsed === null) throw new Error('A resposta de exportação do Rinos Drive não possui o formato esperado.');
    return parsed;
}

export async function cancelDriveExport(target: DriveWorkspaceTarget, exportId: string): Promise<DriveExportProjection> {
    const response = await axios.post(`${workspacePrefix(target)}/exports/${exportId}/cancel`);
    const parsed = parseDriveExport(response.data);
    if (parsed === null) throw new Error('A resposta de exportação do Rinos Drive não possui o formato esperado.');
    return parsed;
}

export async function loadDriveExport(target: DriveWorkspaceTarget, exportId: string): Promise<DriveExportProjection> {
    const response = await axios.get(`${workspacePrefix(target)}/exports/${exportId}`);
    const parsed = parseDriveExport(response.data);
    if (parsed === null) throw new Error('A resposta de exportação do Rinos Drive não possui o formato esperado.');
    return parsed;
}

export function driveExportDownloadUrl(target: DriveWorkspaceTarget, exportId: string): string {
    return `${workspacePrefix(target)}/exports/${exportId}/download`;
}
