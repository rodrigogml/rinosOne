import axios from 'axios';
import type { PeopleCapabilities, TenantContext, TenantCreation, TenantProvisioning, TenantState, TenantSummary } from './tenantTypes';
import { TENANT_STATES } from './tenantTypes';

const PROVISIONING_STATES = ['QUEUED', 'RUNNING', 'SUCCEEDED', 'FAILED'] as const;

export class TenantResponseShapeError extends Error {
    public constructor() {
        super('A resposta de tenants não possui o formato esperado.');
        this.name = 'TenantResponseShapeError';
    }
}

function object(value: unknown): Record<string, unknown> | null {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
        ? value as Record<string, unknown>
        : null;
}

function nonEmptyString(value: unknown): string | null {
    return typeof value === 'string' && value.trim().length > 0 ? value : null;
}

function positiveInteger(value: unknown): number | null {
    return typeof value === 'number' && Number.isSafeInteger(value) && value > 0 ? value : null;
}

function oneOf<T extends readonly string[]>(value: unknown, values: T): T[number] | null {
    return typeof value === 'string' && values.includes(value) ? value as T[number] : null;
}

function requiredObject(value: unknown): Record<string, unknown> {
    const parsed = object(value);

    if (!parsed) throw new TenantResponseShapeError();

    return parsed;
}

function peopleCapabilities(source: Record<string, unknown>): PeopleCapabilities {
    return {
        ...(typeof source.canReadPeople === 'boolean' ? { canReadPeople: source.canReadPeople } : {}),
        ...(typeof source.canCreatePeople === 'boolean' ? { canCreatePeople: source.canCreatePeople } : {}),
        ...(typeof source.canUpdatePeople === 'boolean' ? { canUpdatePeople: source.canUpdatePeople } : {}),
        ...(typeof source.canDuplicatePeople === 'boolean' ? { canDuplicatePeople: source.canDuplicatePeople } : {}),
        ...(typeof source.canInactivatePeople === 'boolean' ? { canInactivatePeople: source.canInactivatePeople } : {}),
        ...(typeof source.canReactivatePeople === 'boolean' ? { canReactivatePeople: source.canReactivatePeople } : {}),
        ...(typeof source.canDeletePeople === 'boolean' ? { canDeletePeople: source.canDeletePeople } : {}),
    };
}

export function parseTenantSummary(value: unknown): TenantSummary {
    const source = requiredObject(value);
    const id = positiveInteger(source.id);
    const displayName = nonEmptyString(source.displayName);
    const state = oneOf(source.state, TENANT_STATES) as TenantState | null;

    if (!id || !displayName || !state || typeof source.selectable !== 'boolean' || typeof source.canManageAvailability !== 'boolean' || typeof source.canReadAuthorization !== 'boolean') {
        throw new TenantResponseShapeError();
    }

    return { id, displayName, state, selectable: source.selectable, canManageAvailability: source.canManageAvailability, canReadAuthorization: source.canReadAuthorization, ...peopleCapabilities(source) };
}

export function parseTenantContext(value: unknown): TenantContext {
    const source = requiredObject(value);
    const tenant = requiredObject(source.tenant);
    const membership = requiredObject(source.membership);
    const capabilities = requiredObject(source.capabilities);
    const id = positiveInteger(tenant.id);
    const displayName = nonEmptyString(tenant.displayName);
    const membershipId = positiveInteger(membership.id);

    if (!id || !displayName || !membershipId || typeof capabilities.canManageAvailability !== 'boolean' || typeof capabilities.canReadAuthorization !== 'boolean' || !Array.isArray(source.availableModules) || !source.availableModules.every((module) => typeof module === 'string')) {
        throw new TenantResponseShapeError();
    }

    return { tenant: { id: id, displayName }, membership: { id: membershipId }, capabilities: { canManageAvailability: capabilities.canManageAvailability, canReadAuthorization: capabilities.canReadAuthorization, ...peopleCapabilities(capabilities) }, availableModules: [...source.availableModules] };
}

function parseProvisioning(value: unknown): TenantProvisioning {
    const source = requiredObject(value);
    const id = positiveInteger(source.id);
    const state = oneOf(source.state, PROVISIONING_STATES);

    if (!id || !state) throw new TenantResponseShapeError();

    return { id, state };
}

export function parseTenantList(value: unknown): TenantSummary[] {
    const source = requiredObject(value);

    if (!Array.isArray(source.tenants)) throw new TenantResponseShapeError();

    return source.tenants.map((tenant) => parseTenantSummary(tenant));
}

export function parseTenantCreation(value: unknown): TenantCreation {
    const source = requiredObject(value);

    return { tenant: parseTenantSummary(source.tenant), provisioning: parseProvisioning(source.provisioning) };
}

export const tenantApi = {
    async list(): Promise<TenantSummary[]> {
        return parseTenantList((await axios.get('/api/v1/tenants')).data);
    },
    async create(displayName: string, idempotencyKey: string): Promise<TenantCreation> {
        return parseTenantCreation((await axios.post('/api/v1/tenants', { displayName }, { headers: { 'Idempotency-Key': idempotencyKey } })).data);
    },
    async startContext(tenantId: number): Promise<TenantContext> {
        const response = await axios.post(`/api/v1/tenants/${encodeURIComponent(tenantId)}/contexts`);
        const source = requiredObject(response.data);

        return parseTenantContext(source.context);
    },
    async endContext(tenantId: number): Promise<void> {
        await axios.delete(`/api/v1/tenants/${encodeURIComponent(tenantId)}/contexts`);
    },
    async changeAvailability(tenantId: number, state: Extract<TenantState, 'ACTIVE' | 'INACTIVE'>): Promise<TenantSummary> {
        const response = await axios.post(`/api/v1/tenants/${encodeURIComponent(tenantId)}/availability`, { state });
        const source = requiredObject(response.data);

        return parseTenantSummary(source.tenant);
    },
};
