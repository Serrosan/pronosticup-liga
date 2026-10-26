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
        $middleware->trustProxies(at: '*');
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

            // Errores "con código" (abort(403, '...'), token caducado, archivo demasiado
            // grande, mantenimiento…): se devuelven con SU código y su mensaje, no como 500.
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $MENSAJES_HTTP = [
                    403 => 'No tienes permiso para hacer esto.',
                    413 => 'El archivo es demasiado grande.',
                    419 => 'La página ha caducado. Recarga e inténtalo de nuevo.',
                    503 => 'La app está en mantenimiento. Vuelve en un rato.',
                ];
                $codigo = $e->getStatusCode();
                // Mensaje propio (abort(403, 'Solo el admin…')) si lo hay; si no, o si es el texto
                // por defecto de Laravel en inglés, el nuestro en español.
                $propio = ! in_array($e->getMessage(), ['', 'This action is unauthorized.', 'CSRF token mismatch.'], true);
                $mensaje = $propio && $codigo !== 419 ? $e->getMessage() : ($MENSAJES_HTTP[$codigo] ?? 'No se ha podido completar la petición.');

                return response()->json(['message' => $mensaje], $codigo, $e->getHeaders());
            }

            if (config('app.debug')) {
                return null;
            }

            return response()->json(['message' => 'Ha ocurrido un error inesperado. Inténtalo de nuevo.'], 500);
        });
    })->create();