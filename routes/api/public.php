<?php

use App\Http\Controllers\Api\V1\Access\CompleteEmailVerificationController;
use App\Http\Controllers\Api\V1\Access\ConfirmPasswordlessSessionController;
use App\Http\Controllers\Api\V1\Access\CreatePasswordSessionController;
use App\Http\Controllers\Api\V1\Access\PasswordlessSessionController;
use App\Http\Controllers\Api\V1\Access\StartRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static fn () => response()->json(['status' => 'ok']));
Route::post('/auth/registrations', StartRegistrationController::class);
Route::post('/auth/email-verifications', [CompleteEmailVerificationController::class, 'byCode']);
Route::post('/auth/email-verifications/link-confirmations', [CompleteEmailVerificationController::class, 'byLink']);
Route::post('/auth/password-sessions', CreatePasswordSessionController::class);
Route::post('/auth/passwordless-sessions', [PasswordlessSessionController::class, 'request']);
Route::post('/auth/passwordless-sessions/confirmations', ConfirmPasswordlessSessionController::class);
Route::post('/auth/passwordless-sessions/link-confirmations', [ConfirmPasswordlessSessionController::class, 'byLink']);
