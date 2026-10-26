<?php

namespace App\Services;

use App\Models\PushSuscripcion;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Cerrar sesión en los demás dispositivos": deja dentro solo al dispositivo
 * desde el que se pide.
 *
 *  1. Borra las demás sesiones guardadas en la base de datos.
 *  2. Cambia el token de "Mantener la sesión iniciada": las cookies de 30 días
 *     de los otros dispositivos dejan de valer. Si este dispositivo tenía la suya,
 *     se le da una nueva para que no tenga que volver a entrar.
 *  3. Quita los avisos al móvil de los demás dispositivos (un móvil perdido no
 *     debe seguir recibiendo "Te han jugado una Falta…"). Los de este se quedan.
 *
 * Se usa desde el botón del perfil y, automáticamente, al cambiar la contraseña.
 */
class SesionesUsuarioService
{
    /** @return array{sesiones:int,avisos:int} cuántas sesiones y dispositivos con avisos se han quitado */
    public function cerrarLasDemas(Request $request, User $usuario, ?string $endpointDeEsteDispositivo = null): array
    {
        $sesiones = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $guard = Auth::guard('web');
        $nombreCookie = $guard instanceof SessionGuard ? $guard->getRecallerName() : null;
        $teniaRecordar = $nombreCookie && $request->cookies->has($nombreCookie);

        $usuario->setRememberToken(Str::random(60));
        $usuario->save();

        if ($teniaRecordar) {
            $valor = $usuario->getAuthIdentifier().'|'.$usuario->getRememberToken().'|'.$usuario->getAuthPassword();
            Cookie::queue(Cookie::make($nombreCookie, $valor, (int) config('auth.guards.web.remember', 43200)));
        }

        $avisos = PushSuscripcion::where('id_usuario', $usuario->id)
            ->when($endpointDeEsteDispositivo, fn ($q) => $q->where('endpoint_hash', '!=', PushSuscripcion::huella($endpointDeEsteDispositivo)))
            ->delete();

        return ['sesiones' => $sesiones, 'avisos' => $avisos];
    }

    /**
     * Tras cambiar la contraseña, esta sesión guarda la huella de la nueva: si no,
     * la comprobación de Sanctum (AuthenticateSession) echaría también a este dispositivo.
     */
    public function recordarContrasenaNueva(Request $request, User $usuario): void
    {
        foreach (['web', 'sanctum'] as $nombre) {
            $request->session()->put('password_hash_'.$nombre, $usuario->getAuthPassword());
        }
    }

    /** Frase para el jugador con lo que se ha hecho. */
    public function resumen(array $resultado): string
    {
        $avisos = $resultado['avisos'] > 0
            ? ' Los avisos al móvil de '.($resultado['avisos'] === 1 ? 'otro dispositivo se han' : "{$resultado['avisos']} dispositivos se han").' desactivado.'
            : '';

        return 'Se ha cerrado la sesión en tus demás dispositivos.'.$avisos;
    }
}
