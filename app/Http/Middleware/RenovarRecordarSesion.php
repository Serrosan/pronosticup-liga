<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Recaller;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as CookieSymfony;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Mantener la sesión iniciada".
 *
 * 1) Laravel cuenta los 30 días del recuérdame desde el login. Aquí los
 *    renovamos en cada petición, para que solo caduque tras 30 días SIN entrar.
 *    Solo se renueva si la cookie es válida para el usuario conectado: nunca se
 *    "resucita" una cookie vieja.
 *
 * 2) Limpieza: hay navegadores con una cookie de recuérdame antigua guardada
 *    solo para www.pronosticup.es, además de la buena (.pronosticup.es). Llegan
 *    las dos con el mismo nombre, PHP se queda con la primera (la vieja) y el
 *    recuérdame no funciona. Si llegan duplicadas, se borra la antigua.
 *
 * La renovación se encola ANTES de ejecutar la petición: si la petición es el
 * logout, su "borrar cookie" se encola después y pisa a esta.
 */
class RenovarRecordarSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $nombre = $guard instanceof SessionGuard ? $guard->getRecallerName() : null;

        if ($nombre && $guard->check()) {
            $valor = $request->cookies->get($nombre);

            if (is_string($valor) && $this->esDelUsuario($valor, $guard->user())) {
                $minutos = (int) config('auth.guards.web.remember', 43200);
                Cookie::queue(Cookie::make($nombre, $valor, $minutos));
            }
        }

        $respuesta = $next($request);

        if ($nombre && $this->llegaDuplicada($request, $nombre)) {
            // Borra la copia guardada solo para el host (sin dominio). La buena,
            // con dominio .pronosticup.es, no se toca.
            $respuesta->headers->setCookie(new CookieSymfony(
                $nombre, '', 1, '/', null, $request->isSecure(), true, false, CookieSymfony::SAMESITE_LAX
            ));
        }

        return $respuesta;
    }

    private function esDelUsuario(string $valor, $usuario): bool
    {
        if (! $usuario) {
            return false;
        }

        $recaller = new Recaller($valor);
        $token = $usuario->getRememberToken();

        return $recaller->valid()
            && (string) $recaller->id() === (string) $usuario->getAuthIdentifier()
            && is_string($token) && $token !== ''
            && hash_equals($token, (string) $recaller->token());
    }

    private function llegaDuplicada(Request $request, string $nombre): bool
    {
        $cabecera = (string) $request->headers->get('cookie', '');

        return preg_match_all('/(?:^|;\s*)'.preg_quote($nombre, '/').'=/', $cabecera) > 1;
    }
}
