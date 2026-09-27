<?php

use App\Bootstrap\LoadUtf8EnvironmentVariables;
use App\Domain\Profile\Exception\AvatarProcessingUnavailableException;
use App\Domain\Profile\Exception\AvatarValidationException;
use App\Http\Middleware\NormalizePersistentAuthenticationSession;
use App\Http\Middleware\RestorePersistentAuthentication;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', NormalizePersistentAuthenticationSession::class);
        $middleware->prependToPriorityList(Authenticate::class, RestorePersistentAuthentication::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AvatarValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'code' => $exception->errorCode,
                'message' => 'A imagem de perfil não pôde ser usada.',
            ], 422);
        });
        $exceptions->render(function (AvatarProcessingUnavailableException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'code' => AvatarProcessingUnavailableException::ERROR_CODE,
                'message' => 'O processamento de imagem está temporariamente indisponível.',
            ], 503);
        });
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Os dados informados não são válidos.',
                'errors' => $exception->errors(),
            ], 422);
        });
    })->create();

$app->bind(LoadEnvironmentVariables::class, LoadUtf8EnvironmentVariables::class);

return $app;
