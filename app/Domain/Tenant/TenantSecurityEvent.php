<?php

namespace App\Domain\Tenant;

enum TenantSecurityEvent: string
{
    case Created = 'tenant_created';
    case ContextSelected = 'tenant_context_selected';
    case ContextEnded = 'tenant_context_ended';
    case ContextDenied = 'tenant_context_denied';
    case AvailabilityChanged = 'tenant_availability_changed';
}
