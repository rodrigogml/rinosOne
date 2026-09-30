<?php

use App\Domain\Person\Exception\PersonDeletionConflictException;
use App\Domain\Person\Exception\PersonDocumentConflictException;
use App\Domain\Person\Exception\PersonInUseException;
use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\Exception\PersonVersionConflictException;
use App\Domain\Profile\Exception\AvatarProcessingUnavailableException;
use App\Domain\Profile\Exception\AvatarValidationException;
use App\Http\Middleware\AuthenticateServiceApiKey;
use App\Http\Middleware\EnforceJsonRequestSize;
use App\Http\Middleware\LimitAuthenticatedApiRequests;
use App\Http\Middleware\MeasurePersonApiRequest;
use App\Http\Middleware\NormalizePersistentAuthenticationSession;
use App\Http\Middleware\RequireIdempotencyKey;
use App\Http\Middleware\ResolvePersonTenantContext;
use App\Http\Middleware\RestorePersistentAuthentication;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Validation\ValidationException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.json-size' => EnforceJsonRequestSize::class,
            'api.rate-limit' => LimitAuthenticatedApiRequests::class,
            'api.idempotency' => RequireIdempotencyKey::class,
            'person.tenant-context' => ResolvePersonTenantContext::class,
            'person.metrics' => MeasurePersonApiRequest::class,
            'service.api-key' => AuthenticateServiceApiKey::class,
        ]);
        $middleware->appendToGroup('web', NormalizePersistentAuthenticationSession::class);
        $middleware->prependToPriorityList(Authenticate::class, RestorePersistentAuthentication::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/v1/tenants/*/people*')) {
                return null;
            }

            return ApiErrorResponse::json(
                $request,
                'PERSON_AUTHENTICATION_REQUIRED',
                'Autenticação necessária para acessar Pessoas.',
                401,
            );
        });
        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/v1/tenants/*/people*')) {
                return null;
            }

            return ApiErrorResponse::json(
                $request,
                'PERSON_ACCESS_DENIED',
                'Ação não permitida para esta organização.',
                403,
            );
        });
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/v1/tenants/*/people*')) {
                return null;
            }

            return ApiErrorResponse::json(
                $request,
                'PERSON_NOT_FOUND',
                'Pessoa não encontrada nesta organização.',
                404,
            );
        });
        $exceptions->render(function (PersonValidationException $exception, Request $request) {
            if (! $request->is('api/v1/tenants/*/people*')) {
                return null;
            }

            $code = match (true) {
                ($exception->errors['person'] ?? null) === 'not_found' => 'PERSON_NOT_FOUND',
                array_key_exists('relationship', $exception->errors) => 'PERSON_RELATIONSHIP_CONFLICT',
                default => 'PERSON_VALIDATION_FAILED',
            };
            $status = match ($code) {
                'PERSON_NOT_FOUND' => 404,
                'PERSON_RELATIONSHIP_CONFLICT' => 409,
                default => 400,
            };

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => match ($code) {
                        'PERSON_NOT_FOUND' => 'Pessoa não encontrada nesta organização.',
                        'PERSON_RELATIONSHIP_CONFLICT' => 'O relacionamento informado não pode ser registrado para esta organização.',
                        default => 'Os dados da Pessoa não são válidos.',
                    },
                    'fields' => $exception->errors,
                ],
            ], $status);
        });
        $exceptions->render(function (PersonDocumentConflictException $exception, Request $request) {
            if ($request->is('api/v1/tenants/*/people*')) {
                return response()->json(['error' => ['code' => 'PERSON_DOCUMENT_CONFLICT', 'message' => 'CPF ou CNPJ já pertence a outra Pessoa desta organização.']], 409);
            }

            return null;
        });
        $exceptions->render(function (PersonVersionConflictException $exception, Request $request) {
            if ($request->is('api/v1/tenants/*/people*')) {
                return response()->json(['error' => ['code' => 'PERSON_VERSION_CONFLICT', 'message' => 'Os dados foram alterados. Recarregue a Pessoa e refaça a alteração.']], 409);
            }

            return null;
        });
        $exceptions->render(function (PersonInUseException $exception, Request $request) {
            if ($request->is('api/v1/tenants/*/people*')) {
                return response()->json(['error' => [
                    'code' => 'PERSON_IN_USE',
                    'message' => 'A Pessoa está em uso por outro módulo e não pode ser excluída.',
                    'usages' => array_map(static fn ($usage): array => ['module' => $usage->module, 'description' => $usage->description], $exception->usages),
                ]], 409);
            }

            return null;
        });
        $exceptions->render(function (PersonDeletionConflictException $exception, Request $request) {
            if ($request->is('api/v1/tenants/*/people*')) {
                return response()->json(['error' => ['code' => 'PERSON_DELETE_CONFLICT', 'message' => 'A Pessoa não pode ser excluída neste momento.']], 409);
            }

            return null;
        });
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

return $app;
