<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSuscripcion;
use App\Services\PushService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Avisos al móvil, fase 1: activar o desactivar en este dispositivo y mandarse
 * una prueba. Todavía no se envía ningún aviso automático.
 */
class PushController extends Controller
{
    /** GET /push/config — si el servidor tiene claves, y la pública para suscribirse. */
    public function config(Request $request, PushService $push)
    {
        return response()->json(['data' => [
            'configurado' => $push->configurado(),
            'clave_publica' => $push->clavePublica(),
            'dispositivos' => PushSuscripcion::where('id_usuario', $request->user()->id)->count(),
        ]]);
    }

    /** POST /push/suscripciones — guarda (o renueva) este dispositivo. */
    public function suscribir(Request $request)
    {
        $datos = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        // Si ese navegador era de otra cuenta (se cambió de usuario), pasa a ser de esta.
        PushSuscripcion::updateOrCreate(
            ['endpoint_hash' => PushSuscripcion::huella($datos['endpoint'])],
            [
                'id_usuario' => $request->user()->id,
                'endpoint' => $datos['endpoint'],
                'p256dh' => $datos['keys']['p256dh'],
                'auth' => $datos['keys']['auth'],
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ]
        );

        return response()->json(['data' => ['dispositivos' => PushSuscripcion::where('id_usuario', $request->user()->id)->count()]]);
    }

    /** DELETE /push/suscripciones — quita este dispositivo. */
    public function desuscribir(Request $request)
    {
        $datos = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);

        PushSuscripcion::where('id_usuario', $request->user()->id)
            ->where('endpoint_hash', PushSuscripcion::huella($datos['endpoint']))
            ->delete();

        return response()->json(['data' => ['dispositivos' => PushSuscripcion::where('id_usuario', $request->user()->id)->count()]]);
    }

    /** POST /push/prueba — un aviso de prueba a todos tus dispositivos. */
    public function prueba(Request $request, PushService $push)
    {
        if (! $push->configurado()) {
            return response()->json(['message' => 'Los avisos al móvil aún no están configurados en el servidor.'], 409);
        }

        if (! PushSuscripcion::where('id_usuario', $request->user()->id)->exists()) {
            return response()->json(['message' => 'No tienes ningún dispositivo con los avisos activados.'], 422);
        }

        $resultado = $push->enviarA($request->user(), '🔔 ¡Funciona!', 'Así te llegarán los avisos de PronostiCup.', '/dashboard', 'prueba');

        if ($resultado['enviados'] === 0) {
            return response()->json(['message' => 'No se pudo entregar el aviso. Desactiva y vuelve a activar los avisos en este dispositivo.'], 422);
        }

        $n = $resultado['enviados'];

        return response()->json(['message' => $n === 1 ? 'Aviso de prueba enviado a tu dispositivo.' : "Aviso de prueba enviado a tus {$n} dispositivos."]);
    }
}
