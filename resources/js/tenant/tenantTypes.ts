export const TENANT_STATES = ['PROVISIONING', 'ACTIVE', 'INACTIVE', 'FAILED'] as const;
export type TenantState = (typeof TENANT_STATES)[number];

export interface TenantSummary {
    id: number;
    displayName: string;
    state: TenantState;
    selectable: boolean;
    canManageAvailability: boolean;
}

export interface TenantMembershipContext { id: number; }

export interface TenantContext {
    tenant: Pick<TenantSummary, 'id' | 'displayName'>;
    membership: TenantMembershipContext;
    capabilities: Pick<TenantSummary, 'canManageAvailability'>;
    availableModules: string[];
}

export interface TenantProvisioning {
    id: number;
    state: 'QUEUED' | 'RUNNING' | 'SUCCEEDED' | 'FAILED';
}

export interface TenantCreation {
    tenant: TenantSummary;
    provisioning: TenantProvisioning;
}
