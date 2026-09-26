<?php

namespace App\Providers;

use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Domain\Authorization\AuthorizationScope;
use App\Infrastructure\FileStorage\Authorization\WorkspaceFolderAuthorizationResourceAdapter;
use App\Services\Authorization\Resource\AuthorizationResourceRegistry;
use App\Services\FileStorage\FileStorageV1Service;
use Illuminate\Support\ServiceProvider;

class FileStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FileStorageV1::class, FileStorageV1Service::class);
        $this->app->singleton(AuthorizationResourceRegistry::class, function (): AuthorizationResourceRegistry {
            $registry = new AuthorizationResourceRegistry;
            $registry->register(new WorkspaceFolderAuthorizationResourceAdapter(AuthorizationScope::Personal));
            $registry->register(new WorkspaceFolderAuthorizationResourceAdapter(AuthorizationScope::Tenant));

            return $registry;
        });
    }
}
