<?php

use App\Http\Controllers\Api\V1\Authorization\ServiceIdentityAuthorizationController;
use App\Http\Middleware\RestorePersistentAuthentication;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['web', RestorePersistentAuthentication::class])->group(static function (): void {
    require __DIR__.'/api/public.php';

    Route::middleware('service.api-key')->get('service/authorization/check', [ServiceIdentityAuthorizationController::class, 'check']);

    Route::middleware('auth')->group(static function (): void {
        require __DIR__.'/api/authenticated.php';
    });
});
