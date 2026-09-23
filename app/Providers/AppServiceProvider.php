<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
