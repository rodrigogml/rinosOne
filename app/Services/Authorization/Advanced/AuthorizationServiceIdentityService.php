<?php
namespace App\Services\Authorization\Advanced;
use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationServiceIdentity;
use App\Models\User;
use App\Services\Authorization\AuthorizationAuditLogger;
use DateTimeInterface;
use LogicException;
use Illuminate\Support\Facades\DB;
class AuthorizationServiceIdentityService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit) {}
    public function create(User $owner, string $displayName, string $purpose, AuthorizationScope $scope, ?int $tenantId = null, ?DateTimeInterface $startsAt = null, ?DateTimeInterface $endsAt = null): AuthorizationServiceIdentity
    {
        if (trim($displayName)==='' || trim($purpose)==='' || ($scope === AuthorizationScope::Tenant) !== ($tenantId !== null) || ($endsAt !== null && ($startsAt === null || $endsAt <= $startsAt))) throw new LogicException('The authorization service identity is invalid.');
        $identity=AuthorizationServiceIdentity::query()->create(['idOwnerUser'=>$owner->id,'idTenant'=>$tenantId,'displayName'=>trim($displayName),'purpose'=>trim($purpose),'scope'=>$scope->value,'state'=>'ACTIVE','startsAt'=>$startsAt,'endsAt'=>$endsAt]);
        $this->audit->record('authorization.service_identity.created','authorization.service_identity',$identity->id,actorUserId:$owner->id,tenantId:$tenantId,after:['displayName'=>$identity->displayName,'purpose'=>$identity->purpose,'scope'=>$identity->scope,'state'=>'ACTIVE']);
        return $identity;
    }

    public function expireDue(): int
    {
        return DB::transaction(function (): int {
            $identities = AuthorizationServiceIdentity::query()->where('state','ACTIVE')->whereNotNull('endsAt')->where('endsAt','<=',now())->lockForUpdate()->get();
            foreach ($identities as $identity) { $identity->update(['state'=>'EXPIRED']); $this->audit->record('authorization.service_identity.expired','authorization.service_identity',$identity->id,tenantId:$identity->idTenant,before:['state'=>'ACTIVE'],after:['state'=>'EXPIRED']); }
            return $identities->count();
        });
    }
}
