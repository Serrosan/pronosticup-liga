<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->alias(['admin' => \App\Http\Middleware\EnsureIsAdmin::class]);
        $middleware->convertEmptyStringsToNull();
        $middleware->trimStrings();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json(['message' => 'No autenticado.'], 401);
        });

        $exceptions->render(function (AuthorizationException $e, $request) {
            return response()->json(['message' => 'No tienes permiso para hacer esto.'], 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, $request) {
            return response()->json(['message' => 'No se ha encontrado lo que buscas.'], 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            return response()->json(['message' => 'Esta ruta no existe.'], 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            return response()->json(['message' => 'Método no permitido para esta ruta.'], 405);
        });

        $exceptions->render(function (ValidationException $e, $request) {
            return response()->json([
                'message' => 'Hay algún dato no válido.',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            return response()->json(['message' => 'Demasiadas peticiones seguidas. Espera un momento e inténtalo de nuevo.'], 429);
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if (config('app.debug')) {
                return null;
            }

            return response()->json(['message' => 'Ha ocurrido un error inesperado. Inténtalo de nuevo.'], 500);
        });
    })->create();