import type { DriveItem, DriveLocationProjection, DriveWorkspaceTarget } from './driveWorkspaceApi';

export type DriveViewMode = 'grid' | 'list' | 'details';
export type DrivePanelId = 'primary' | 'secondary';
export interface DrivePanelContext {
    panelId: DrivePanelId;
    target: DriveWorkspaceTarget;
    projection: DriveLocationProjection;
    items: DriveItem[];
    tree: DriveItem[];
}
export interface DriveDropDestination {
    target: DriveWorkspaceTarget;
    folderId: number | null;
    displayName: string;
    editable: boolean;
}
export interface SessionExport {
    exportId: string;
    target: DriveWorkspaceTarget;
    state: string;
    expiresAt: string | null;
    createdAt: string;
}
export function sameDrive(left: DriveWorkspaceTarget, right: DriveWorkspaceTarget): boolean {
    return left.kind === right.kind && (left.kind !== 'tenant' || right.kind !== 'tenant' || left.tenantId === right.tenantId);
}
/** Reject self/descendant and no-op drops without conflating ids from different workspaces. */
export function invalidDriveDrop(source: DrivePanelContext, destination: DriveDropDestination): boolean {
    if (!destination.editable || !source.items.length || source.projection.location.kind === 'trash') return true;
    if (!sameDrive(source.target, destination.target)) return false;
    const sourceParent = source.projection.location.kind === 'folder' ? source.projection.location.id : null;
    if (sourceParent === destination.folderId) return true;
    const parents = new Map(source.tree.map(folder => [folder.id, folder.parentFolderId]));
    return source.items.filter(item => item.kind === 'folder').some(folder => {
        let candidate = destination.folderId;
        const visited = new Set<number>();
        while (candidate !== null && !visited.has(candidate)) {
            if (candidate === folder.id) return true;
            visited.add(candidate);
            candidate = parents.get(candidate) ?? null;
        }
        return false;
    });
}
