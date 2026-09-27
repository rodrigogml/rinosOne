export interface AdministrativeRole { id: number; key: string; displayName: string; description: string; }
export interface AdministrativeGroup { id: number; displayName: string; }
export interface AdministrativeAssignment { id: number; roleId: number; userId: number; tenantId: number; state: 'ACTIVE' | 'INACTIVE'; }
export interface AdministrativeRestriction { id: number; permissionId: number; userId: number | null; groupId: number | null; active: boolean; }

export class AuthorizationAdministrationResponseShapeError extends Error {
    public constructor() { super('A resposta da administração de autorização não possui o formato esperado.'); this.name = 'AuthorizationAdministrationResponseShapeError'; }
}

function source(value: unknown): Record<string, unknown> {
    if (!value || typeof value !== 'object' || Array.isArray(value)) throw new AuthorizationAdministrationResponseShapeError();
    return value as Record<string, unknown>;
}
function id(value: unknown): number { if (typeof value !== 'number' || !Number.isSafeInteger(value) || value < 1) throw new AuthorizationAdministrationResponseShapeError(); return value; }
function text(value: unknown): string { if (typeof value !== 'string' || value.trim() === '') throw new AuthorizationAdministrationResponseShapeError(); return value; }

export function parseAdministrativeRole(value: unknown): AdministrativeRole { const item = source(value); return { id: id(item.id), key: text(item.key), displayName: text(item.displayName), description: typeof item.description === 'string' ? item.description : '' }; }
export function parseAdministrativeGroup(value: unknown): AdministrativeGroup { const item = source(value); return { id: id(item.id), displayName: text(item.displayName) }; }
export function parseAdministrativeAssignment(value: unknown): AdministrativeAssignment { const item = source(value); if (item.state !== 'ACTIVE' && item.state !== 'INACTIVE') throw new AuthorizationAdministrationResponseShapeError(); return { id: id(item.id), roleId: id(item.roleId), userId: id(item.userId), tenantId: id(item.tenantId), state: item.state }; }
export function parseAdministrativeRestriction(value: unknown): AdministrativeRestriction { const item = source(value); if (typeof item.active !== 'boolean' || (item.userId !== null && (typeof item.userId !== 'number' || item.userId < 1)) || (item.groupId !== null && (typeof item.groupId !== 'number' || item.groupId < 1)) || (item.userId === null) === (item.groupId === null)) throw new AuthorizationAdministrationResponseShapeError(); return { id: id(item.id), permissionId: id(item.permissionId), userId: item.userId as number | null, groupId: item.groupId as number | null, active: item.active }; }
