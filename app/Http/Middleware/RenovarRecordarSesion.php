<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Mantener la sesión iniciada".
 *
 * Laravel cuenta los 30 días del recuérdame desde el login. Aquí los renovamos
 * en cada petición de alguien que ya lo tiene, para que solo caduque tras
 * 30 días SIN entrar.
 *
 * Se encola ANTES de ejecutar la petición: si la petición es el logout, su
 * "borrar cookie" se encola después y pisa a esta, así que cerrar sesión
 * sigue borrando el recuérdame.
 */
class RenovarRecordarSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard && $guard->check()) {
            $nombre = $guard->getRecallerName();
            $valor = $request->cookies->get($nombre);

            if (is_string($valor) && $valor !== '') {
                $minutos = (int) config('auth.guards.web.remember', 43200);
                Cookie::queue(Cookie::make($nombre, $valor, $minutos));
            }
        }

        return $next($request);
    }
}
