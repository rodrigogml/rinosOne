export const TENANT_STATES = ['PROVISIONING', 'ACTIVE', 'INACTIVE', 'FAILED'] as const;
export type TenantState = (typeof TENANT_STATES)[number];

export interface PeopleCapabilities {
    canReadPeople?: boolean;
    canCreatePeople?: boolean;
    canUpdatePeople?: boolean;
    canDuplicatePeople?: boolean;
    canInactivatePeople?: boolean;
    canReactivatePeople?: boolean;
    canDeletePeople?: boolean;
}

export interface TenantSummary extends PeopleCapabilities {
    id: number;
    displayName: string;
    state: TenantState;
    selectable: boolean;
    canManageAvailability: boolean;
    canReadAuthorization: boolean;
}

export interface TenantMembershipContext { id: number; }

export interface TenantContext {
    tenant: Pick<TenantSummary, 'id' | 'displayName'>;
    membership: TenantMembershipContext;
    capabilities: Pick<TenantSummary, 'canManageAvailability' | 'canReadAuthorization' | keyof PeopleCapabilities>;
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
