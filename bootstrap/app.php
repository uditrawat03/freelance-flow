<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->preventRequestForgery(except: [
            'stripe/webhook',
        ]);

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Handle all exceptions that occur in API routes
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                return match (true) {
                    $e instanceof \Illuminate\Auth\AuthenticationException =>
                    response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401),

                    $e instanceof \Illuminate\Auth\Access\AuthorizationException =>
                    response()->json(['success' => false, 'message' => 'Forbidden.'], 403),

                    $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException =>
                    response()->json(['success' => false, 'message' => 'Resource not found.'], 404),

                    $e instanceof \Illuminate\Validation\ValidationException =>
                    response()->json([
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => $e->errors(),
                    ], 422),

                    $e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException =>
                    response()->json(['success' => false, 'message' => 'Too many requests.'], 429),

                    default => response()->json([
                        'success' => false,
                        'message' => app()->isProduction()
                            ? 'An unexpected error occurred.'
                            : $e->getMessage(),
                    ], 500),
                };
            }
        });
    })
    ->withEvents(discover: false)->create();
