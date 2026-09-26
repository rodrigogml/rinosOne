<?php

namespace App\Providers;

use App\Infrastructure\FinancialInstitution\BcbFinancialInstitutionSource;
use App\Infrastructure\FinancialInstitution\FinancialInstitutionSource;
use App\Infrastructure\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FinancialInstitutionSource::class, BcbFinancialInstitutionSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make('migrator')->path(database_path('migrations/core'));

        Session::extend('rinos-database', function ($app): DatabaseSessionHandler {
            return new DatabaseSessionHandler(
                $app['db']->connection(config('session.connection')),
                config('session.table'),
                config('session.lifetime'),
                $app,
            );
        });
    }
}
