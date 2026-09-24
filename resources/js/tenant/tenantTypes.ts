export const TENANT_STATES = ['PROVISIONING', 'ACTIVE', 'INACTIVE', 'FAILED'] as const;
export const TENANT_ROLES = ['OWNER'] as const;

export type TenantState = (typeof TENANT_STATES)[number];
export type TenantRole = (typeof TENANT_ROLES)[number];

export interface TenantSummary {
    id: string;
    displayName: string;
    state: TenantState;
    selectable: boolean;
    role?: TenantRole;
}

export interface TenantMembershipContext {
    id: string;
    role: TenantRole;
}

export interface TenantContext {
    tenant: Pick<TenantSummary, 'id' | 'displayName'>;
    membership: TenantMembershipContext;
    availableModules: string[];
}

export interface TenantProvisioning {
    id: string;
    state: 'QUEUED' | 'RUNNING' | 'SUCCEEDED' | 'FAILED';
}

export interface TenantCreation {
    tenant: TenantSummary;
    provisioning: TenantProvisioning;
}
