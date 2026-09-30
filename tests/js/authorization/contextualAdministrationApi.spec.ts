import { describe, expect, it } from 'vitest';
import {
    ContextualAuthorizationAdministrationResponseShapeError,
    parseContextualAdministrationAuditEvent,
    parseContextualAdministrationCatalogItem,
    parseContextualAdministrationContext,
    parseContextualAdministrationResourceShare,
    parseContextualAdministrationSubject,
} from '../../../resources/js/authorization/contextualAdministrationApi';

const context = {
    scope: 'TENANT', tenantId: 42, displayName: 'Empresa Exemplo', workspaceKind: 'TENANT',
    capabilities: { canReadAccess: true, canManageRoles: true, canManageSharing: false, canUseAdvancedControls: true },
};
const subject = {
    subjectId: 7, subjectType: 'USER', displayName: 'Ana', effectiveCapabilities: ['tenant.authorization.read'], expiresAt: null,
    accessSources: [{ type: 'ROLE', displayName: 'Administradores', scope: 'TENANT', expiresAt: '2026-01-01T00:00:00+00:00', resource: null }],
};

describe('contextual authorization administration parsers', () => {
    it('accepts all contextual projection shapes', () => {
        expect(parseContextualAdministrationContext(context).tenantId).toBe(42);
        expect(parseContextualAdministrationSubject(subject).accessSources[0].type).toBe('ROLE');
        expect(parseContextualAdministrationCatalogItem({ id: 3, catalogType: 'ROLE', key: 'tenant.admin', displayName: 'Administrador', description: '', scope: 'TENANT', systemManaged: true, active: true }).key).toBe('tenant.admin');
        expect(parseContextualAdministrationResourceShare({ id: 5, resourceType: 'FOLDER', resourceId: 9, grantee: subject, relation: 'EDIT', origin: 'INHERITED', inheritedFrom: { resourceType: 'FOLDER', resourceId: 8 } }).inheritedFrom?.resourceId).toBe(8);
        expect(parseContextualAdministrationAuditEvent({ id: 11, occurredAt: '2026-01-01T00:00:00+00:00', actorUserId: 7, operation: 'ROLE_ASSIGNED', targetType: 'ROLE', targetId: 3 }).targetId).toBe(3);
    });

    it('rejects missing fields, divergent types, unknown enums and unsafe BIGINT values', () => {
        expect(() => parseContextualAdministrationContext({ ...context, capabilities: {} })).toThrow(ContextualAuthorizationAdministrationResponseShapeError);
        expect(() => parseContextualAdministrationSubject({ ...subject, subjectId: Number.MAX_SAFE_INTEGER + 1 })).toThrow(ContextualAuthorizationAdministrationResponseShapeError);
        expect(() => parseContextualAdministrationCatalogItem({ id: 3, catalogType: 'UNKNOWN', key: 'x', displayName: 'X', description: '', scope: 'TENANT', systemManaged: true, active: true })).toThrow(ContextualAuthorizationAdministrationResponseShapeError);
        expect(() => parseContextualAdministrationResourceShare({ id: 5, resourceType: 'FOLDER', resourceId: '9', grantee: subject, relation: 'EDIT', origin: 'DIRECT', inheritedFrom: null })).toThrow(ContextualAuthorizationAdministrationResponseShapeError);
        expect(() => parseContextualAdministrationAuditEvent({ id: 11, occurredAt: null, actorUserId: 7, operation: 'ROLE_ASSIGNED', targetType: 'ROLE', targetId: 3 })).toThrow(ContextualAuthorizationAdministrationResponseShapeError);
    });
});
