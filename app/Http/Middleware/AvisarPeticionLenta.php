<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apunta en el registro (como aviso, no como error) las peticiones de la API
 * que tardan más de un segundo. Se ven en Sistema → Errores y versión con
 * "mostrar avisos", agrupadas por pantalla: si una empieza a pesar, se nota.
 *
 * Quedan fuera las que tardan a propósito (descargas de copias, consultas a
 * football-data, que esperan entre una y otra).
 */
class AvisarPeticionLenta
{
    /** Milisegundos a partir de los cuales se avisa. Los tests lo bajan. */
    public static int $umbralMs = 1000;

    private const LENTAS_A_PROPOSITO = ['admin/copia-local', 'admin/comparador-plantillas/equipos'];

    public function handle(Request $request, Closure $next): Response
    {
        $inicio = microtime(true);
        $respuesta = $next($request);
        $ms = (int) round((microtime(true) - $inicio) * 1000);

        if ($ms >= static::$umbralMs && ! $this->lentaAProposito($request)) {
            // La ruta con sus {parámetros}, no la URL real: así /jornadas/8 y
            // /jornadas/9 cuentan como la misma pantalla en el visor.
            $ruta = $request->route()?->uri() ?? $request->path();
            Log::warning("Petición lenta: {$request->method()} /{$ruta}", [
                'ms' => $ms,
                'url' => $request->getRequestUri(),
                'usuario' => $request->user()?->id,
            ]);
        }

        return $respuesta;
    }

    private function lentaAProposito(Request $request): bool
    {
        foreach (self::LENTAS_A_PROPOSITO as $trozo) {
            if (str_contains($request->path(), $trozo)) {
                return true;
            }
        }

        return false;
    }
}
