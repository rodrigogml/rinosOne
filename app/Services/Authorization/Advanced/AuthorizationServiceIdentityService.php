<?php

namespace App\Services\Authorization\Advanced;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationServiceCredential;
use App\Models\AuthorizationServiceIdentity;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\Performance\PolicyVersionService;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

class AuthorizationServiceIdentityService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit, private readonly PolicyVersionService $policyVersions) {}

    public function create(User $owner, string $displayName, string $purpose, AuthorizationScope $scope, ?int $tenantId = null, ?DateTimeInterface $startsAt = null, ?DateTimeInterface $endsAt = null): AuthorizationServiceIdentity
    {
        if (trim($displayName) === '' || trim($purpose) === '' || ($scope === AuthorizationScope::Tenant) !== ($tenantId !== null) || ($scope === AuthorizationScope::Tenant && (! Tenant::query()->whereKey($tenantId)->where('state', TenantState::Active)->exists() || ! TenantMembership::query()->where('idTenant', $tenantId)->where('idUser', $owner->id)->where('state', TenantMembershipState::Active)->exists())) || ($endsAt !== null && ($startsAt === null || $endsAt <= $startsAt))) {
            throw new LogicException('The authorization service identity is invalid.');
        }
        $identity = AuthorizationServiceIdentity::query()->create(['idOwnerUser' => $owner->id, 'idTenant' => $tenantId, 'displayName' => trim($displayName), 'purpose' => trim($purpose), 'scope' => $scope->value, 'state' => 'ACTIVE', 'startsAt' => $startsAt, 'endsAt' => $endsAt]);
        $this->audit->record('authorization.service_identity.created', 'authorization.service_identity', $identity->id, actorUserId: $owner->id, tenantId: $tenantId, after: ['displayName' => $identity->displayName, 'purpose' => $identity->purpose, 'scope' => $identity->scope, 'state' => 'ACTIVE']);

        return $identity;
    }

    public function expireDue(): int
    {
        return DB::transaction(function (): int {
            $identities = AuthorizationServiceIdentity::query()->where('state', 'ACTIVE')->whereNotNull('endsAt')->where('endsAt', '<=', now())->lockForUpdate()->get();
            foreach ($identities as $identity) {
                $identity->update(['state' => 'EXPIRED']);
                $this->audit->record('authorization.service_identity.expired', 'authorization.service_identity', $identity->id, tenantId: $identity->idTenant, before: ['state' => 'ACTIVE'], after: ['state' => 'EXPIRED']);
            }

            return $identities->count();
        });
    }

    /** @param list<string>|null $permissionKeys */
    public function issueCredential(AuthorizationServiceIdentity $identity, string $displayName, ?array $permissionKeys = null, ?DateTimeInterface $expiresAt = null): IssuedServiceCredential
    {
        if (trim($displayName) === '' || $identity->state !== 'ACTIVE' || ($expiresAt !== null && $expiresAt <= now())) {
            throw new LogicException('The authorization service credential is invalid.');
        }
        $publicId = Str::lower(Str::random(12));
        $secret = Str::random(48);
        $apiKey = "rinos_{$publicId}_{$secret}";
        $credential = AuthorizationServiceCredential::query()->create(['idServiceIdentity' => $identity->id, 'displayName' => trim($displayName), 'publicId' => $publicId, 'secretHash' => Hash::make($secret), 'permissionKeys' => $permissionKeys, 'state' => 'ACTIVE', 'expiresAt' => $expiresAt]);
        $this->audit->record('authorization.service_credential.issued', 'authorization.service_credential', $credential->id, actorUserId: $identity->idOwnerUser, tenantId: $identity->idTenant, after: ['idServiceIdentity' => $identity->id, 'displayName' => $credential->displayName, 'publicId' => $publicId, 'state' => 'ACTIVE']);

        return new IssuedServiceCredential($credential->id, $apiKey);
    }

    public function validateApiKey(string $apiKey): ?AuthorizationServiceCredential
    {
        if (! preg_match('/^rinos_([a-z0-9]{12})_(.+)$/', $apiKey, $parts)) {
            return null;
        }
        $credential = AuthorizationServiceCredential::query()->where('publicId', $parts[1])->where('state', 'ACTIVE')->where(fn ($q) => $q->whereNull('expiresAt')->orWhere('expiresAt', '>', now()))->first();
        if ($credential === null || ! Hash::check($parts[2], $credential->secretHash)) {
            return null;
        }
        $identity = AuthorizationServiceIdentity::query()->whereKey($credential->idServiceIdentity)->where('state', 'ACTIVE')->where(fn ($q) => $q->whereNull('startsAt')->orWhere('startsAt', '<=', now()))->where(fn ($q) => $q->whereNull('endsAt')->orWhere('endsAt', '>', now()))->exists();
        if (! $identity) {
            return null;
        }
        $credential->update(['lastUsedAt' => now()]);

        return $credential;
    }

    public function revokeCredential(AuthorizationServiceCredential $credential): void
    {
        if ($credential->state !== 'ACTIVE') {
            return;
        } $credential->update(['state' => 'REVOKED']);
        $this->audit->record('authorization.service_credential.revoked', 'authorization.service_credential', $credential->id, tenantId: $credential->identity?->idTenant, after: ['state' => 'REVOKED']);
        $this->policyVersions->invalidate(AuthorizationScope::from($credential->identity->scope), $credential->identity->idTenant);
    }
}
