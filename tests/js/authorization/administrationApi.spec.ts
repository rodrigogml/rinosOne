import { describe, expect, it } from 'vitest';
import { AuthorizationAdministrationResponseShapeError, parseAdministrativeAssignment, parseAdministrativeRestriction, parseAdministrativeRole } from '../../../resources/js/authorization/administrationApi';

describe('authorization administration API parsers', () => {
    it('accepts the camelCase role and assignment contracts', () => {
        expect(parseAdministrativeRole({ id: 1, key: 'tenant.billing.viewer', displayName: 'Viewer', description: 'Views.' }).key).toBe('tenant.billing.viewer');
        expect(parseAdministrativeAssignment({ id: 2, roleId: 1, userId: 3, tenantId: 4, state: 'ACTIVE' }).userId).toBe(3);
    });
    it('rejects invalid identifiers and ambiguous restriction subjects', () => {
        expect(() => parseAdministrativeRole({ id: '1', key: 'x', displayName: 'X', description: '' })).toThrow(AuthorizationAdministrationResponseShapeError);
        expect(() => parseAdministrativeRestriction({ id: 1, permissionId: 2, userId: null, groupId: null, active: true })).toThrow(AuthorizationAdministrationResponseShapeError);
    });
});
