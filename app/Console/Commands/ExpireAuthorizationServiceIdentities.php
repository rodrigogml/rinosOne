<?php
namespace App\Console\Commands;
use App\Services\Authorization\Advanced\AuthorizationServiceIdentityService;
use Illuminate\Console\Command;
use Throwable;
class ExpireAuthorizationServiceIdentities extends Command { protected $signature='authorization:expire-service-identities'; protected $description='Expires authorization service identities whose validity ended.'; public function handle(AuthorizationServiceIdentityService $identities): int { try { $count=$identities->expireDue(); } catch (Throwable $exception) { report($exception); $this->components->error('Authorization service identity expiration did not run safely.'); return self::FAILURE; } $this->components->info("{$count} authorization service identity(s) expired."); return self::SUCCESS; } }
