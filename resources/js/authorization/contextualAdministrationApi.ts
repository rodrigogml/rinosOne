export type AuthorizationAdministrationScope = 'PERSONAL' | 'TENANT' | 'PLATFORM';
export type AuthorizationAdministrationSubjectType = 'USER' | 'SERVICE_IDENTITY' | 'GROUP';
export type AuthorizationAdministrationAccessSourceType = 'ROLE' | 'GROUP' | 'DIRECT_GRANT' | 'SHARE' | 'DELEGATION' | 'POLICY';
export type AuthorizationAdministrationCatalogType = 'ROLE' | 'GROUP' | 'PERMISSION';
export type AuthorizationAdministrationRelation = 'READ' | 'EDIT' | 'ADMIN';
export type AuthorizationAdministrationShareOrigin = 'DIRECT' | 'INHERITED';

export interface ContextualAdministrationCapabilities {
    canReadAccess: boolean;
    canManageRoles: boolean;
    canManageSharing: boolean;
    canUseAdvancedControls: boolean;
}

export interface ContextualAdministrationContext {
    scope: AuthorizationAdministrationScope;
    tenantId: number | null;
    displayName: string;
    workspaceKind: 'PERSONAL' | 'TENANT' | null;
    capabilities: ContextualAdministrationCapabilities;
}

export interface ContextualAdministrationAccessSource {
    type: AuthorizationAdministrationAccessSourceType;
    displayName: string;
    scope: AuthorizationAdministrationScope;
    expiresAt: string | null;
    resource: ContextualAdministrationResourceReference | null;
}

export interface ContextualAdministrationSubject {
    subjectId: number;
    subjectType: AuthorizationAdministrationSubjectType;
    displayName: string;
    accessSources: ContextualAdministrationAccessSource[];
    effectiveCapabilities: string[];
    expiresAt: string | null;
}

export interface ContextualAdministrationCatalogItem {
    id: number;
    catalogType: AuthorizationAdministrationCatalogType;
    key: string;
    displayName: string;
    description: string;
    scope: AuthorizationAdministrationScope;
    systemManaged: boolean;
    active: boolean;
}

export interface ContextualAdministrationResourceReference {
    resourceType: string;
    resourceId: number;
}

export interface ContextualAdministrationResourceShare {
    id: number;
    resourceType: string;
    resourceId: number;
    grantee: ContextualAdministrationSubject;
    relation: AuthorizationAdministrationRelation;
    origin: AuthorizationAdministrationShareOrigin;
    inheritedFrom: ContextualAdministrationResourceReference | null;
}

export interface ContextualAdministrationWorkspaceResponsible { type: 'USER' | 'TENANT'; id: number; displayName: string; }

export interface ContextualAdministrationAuditEvent {
    id: number;
    occurredAt: string;
    actorUserId: number | null;
    operation: string;
    targetType: string;
    targetId: number;
}

export class ContextualAuthorizationAdministrationResponseShapeError extends Error {
    public constructor() {
        super('A resposta contextual de administração de autorização não possui o formato esperado.');
        this.name = 'ContextualAuthorizationAdministrationResponseShapeError';
    }
}

function source(value: unknown): Record<string, unknown> {
    if (!value || typeof value !== 'object' || Array.isArray(value)) throw new ContextualAuthorizationAdministrationResponseShapeError();
    return value as Record<string, unknown>;
}

function id(value: unknown): number {
    if (typeof value !== 'number' || !Number.isSafeInteger(value) || value < 1) throw new ContextualAuthorizationAdministrationResponseShapeError();
    return value;
}

function nullableId(value: unknown): number | null {
    return value === null ? null : id(value);
}

function text(value: unknown, allowEmpty = false): string {
    if (typeof value !== 'string' || (!allowEmpty && value.trim() === '')) throw new ContextualAuthorizationAdministrationResponseShapeError();
    return value;
}

function nullableIsoTimestamp(value: unknown): string | null {
    if (value === null) return null;
    const timestamp = text(value);
    if (Number.isNaN(Date.parse(timestamp))) throw new ContextualAuthorizationAdministrationResponseShapeError();
    return timestamp;
}

function enumValue<T extends string>(value: unknown, allowed: readonly T[]): T {
    if (typeof value !== 'string' || !allowed.includes(value as T)) throw new ContextualAuthorizationAdministrationResponseShapeError();
    return value as T;
}

function stringList(value: unknown): string[] {
    if (!Array.isArray(value) || value.some((item) => typeof item !== 'string' || item.trim() === '')) throw new ContextualAuthorizationAdministrationResponseShapeError();
    return value as string[];
}

function boolean(value: unknown): boolean {
    if (typeof value !== 'boolean') throw new ContextualAuthorizationAdministrationResponseShapeError();
    return value;
}

export function parseContextualAdministrationContext(value: unknown): ContextualAdministrationContext {
    const item = source(value);
    const scope = enumValue(item.scope, ['PERSONAL', 'TENANT', 'PLATFORM'] as const);
    const tenantId = nullableId(item.tenantId);
    const workspaceKind = item.workspaceKind === null ? null : enumValue(item.workspaceKind, ['PERSONAL', 'TENANT'] as const);
    const capabilities = source(item.capabilities);

    if ((scope === 'TENANT') !== (tenantId !== null) || (scope === 'TENANT' && workspaceKind !== 'TENANT') || (scope === 'PERSONAL' && workspaceKind !== 'PERSONAL') || (scope === 'PLATFORM' && workspaceKind !== null)) {
        throw new ContextualAuthorizationAdministrationResponseShapeError();
    }

    return {
        scope,
        tenantId,
        displayName: text(item.displayName),
        workspaceKind,
        capabilities: {
            canReadAccess: boolean(capabilities.canReadAccess),
            canManageRoles: boolean(capabilities.canManageRoles),
            canManageSharing: boolean(capabilities.canManageSharing),
            canUseAdvancedControls: boolean(capabilities.canUseAdvancedControls),
        },
    };
}

export function parseContextualAdministrationAccessSource(value: unknown): ContextualAdministrationAccessSource {
    const item = source(value);
    return {
        type: enumValue(item.type, ['ROLE', 'GROUP', 'DIRECT_GRANT', 'SHARE', 'DELEGATION', 'POLICY'] as const),
        displayName: text(item.displayName),
        scope: enumValue(item.scope, ['PERSONAL', 'TENANT', 'PLATFORM'] as const),
        expiresAt: nullableIsoTimestamp(item.expiresAt),
        resource: item.resource === undefined || item.resource === null ? null : parseContextualAdministrationResourceReference(item.resource),
    };
}

export function parseContextualAdministrationSubject(value: unknown): ContextualAdministrationSubject {
    const item = source(value);
    if (!Array.isArray(item.accessSources)) throw new ContextualAuthorizationAdministrationResponseShapeError();
    return {
        subjectId: id(item.subjectId),
        subjectType: enumValue(item.subjectType, ['USER', 'SERVICE_IDENTITY', 'GROUP'] as const),
        displayName: text(item.displayName),
        accessSources: item.accessSources.map(parseContextualAdministrationAccessSource),
        effectiveCapabilities: stringList(item.effectiveCapabilities),
        expiresAt: nullableIsoTimestamp(item.expiresAt),
    };
}

export function parseContextualAdministrationCatalogItem(value: unknown): ContextualAdministrationCatalogItem {
    const item = source(value);
    return {
        id: id(item.id),
        catalogType: enumValue(item.catalogType, ['ROLE', 'GROUP', 'PERMISSION'] as const),
        key: text(item.key),
        displayName: text(item.displayName),
        description: text(item.description, true),
        scope: enumValue(item.scope, ['PERSONAL', 'TENANT', 'PLATFORM'] as const),
        systemManaged: boolean(item.systemManaged),
        active: boolean(item.active),
    };
}

export function parseContextualAdministrationResourceShare(value: unknown): ContextualAdministrationResourceShare {
    const item = source(value);
    const origin = enumValue(item.origin, ['DIRECT', 'INHERITED'] as const);
    const inheritedFrom = item.inheritedFrom === null ? null : parseContextualAdministrationResourceReference(item.inheritedFrom);
    if ((origin === 'DIRECT') !== (inheritedFrom === null)) throw new ContextualAuthorizationAdministrationResponseShapeError();

    return {
        id: id(item.id),
        resourceType: text(item.resourceType),
        resourceId: id(item.resourceId),
        grantee: parseContextualAdministrationSubject(item.grantee),
        relation: enumValue(item.relation, ['READ', 'EDIT', 'ADMIN'] as const),
        origin,
        inheritedFrom,
    };
}

export function parseContextualAdministrationResourceReference(value: unknown): ContextualAdministrationResourceReference {
    const item = source(value);
    return { resourceType: text(item.resourceType), resourceId: id(item.resourceId) };
}

export function parseContextualAdministrationWorkspaceResponsible(value: unknown): ContextualAdministrationWorkspaceResponsible {
    const item = source(value);
    return { type: enumValue(item.type, ['USER', 'TENANT'] as const), id: id(item.id), displayName: text(item.displayName) };
}

export function parseContextualAdministrationAuditEvent(value: unknown): ContextualAdministrationAuditEvent {
    const item = source(value);
    const occurredAt = nullableIsoTimestamp(item.occurredAt);
    if (occurredAt === null) throw new ContextualAuthorizationAdministrationResponseShapeError();

    return {
        id: id(item.id),
        occurredAt,
        actorUserId: nullableId(item.actorUserId),
        operation: text(item.operation),
        targetType: text(item.targetType),
        targetId: id(item.targetId),
    };
}
