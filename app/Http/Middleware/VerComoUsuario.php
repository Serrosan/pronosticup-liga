<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Ver como otro usuario", en solo lectura.
 *
 * Si el superadmin manda la cabecera X-Ver-Como con el id de un usuario, esta
 * petición se atiende como si la hiciera ese usuario: así las pantallas de
 * siempre (portada, jornada, mis cartas…) enseñan exactamente lo que ve él,
 * sin duplicar nada.
 *
 * Reglas:
 *  - Solo funciona si quien está conectado de verdad es superadmin. Para
 *    cualquier otro, la cabecera se ignora.
 *  - Solo GET/HEAD/OPTIONS. Cualquier otra petición con la cabecera se
 *    rechaza (403): en este modo no se puede cambiar nada en su nombre.
 *  - Queda apuntado en el log quién miró a quién.
 */
class VerComoUsuario
{
    public const CABECERA = 'X-Ver-Como';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header(self::CABECERA);

        if (! $id) {
            return $next($request);
        }

        $real = $request->user();

        if (! $real || ! $real->es_superadmin) {
            return $next($request);
        }

        if (! $request->isMethodSafe()) {
            return response()->json([
                'message' => 'Estás viendo la app como otro usuario: en este modo no se puede cambiar nada.',
            ], 403);
        }

        $otro = User::find((int) $id);

        if (! $otro) {
            return response()->json(['message' => 'Ese usuario ya no existe.'], 404);
        }

        if ($otro->id !== $real->id) {
            Log::info('Ver como usuario', ['admin' => $real->id, 'como' => $otro->id, 'ruta' => $request->path()]);
        }

        // Para el resto de la petición, el usuario es él: en el guard por
        // defecto, en el de Sanctum y en $request->user().
        Auth::setUser($otro);
        Auth::guard('sanctum')->setUser($otro);
        $request->setUserResolver(fn () => $otro);

        return $next($request);
    }
}
