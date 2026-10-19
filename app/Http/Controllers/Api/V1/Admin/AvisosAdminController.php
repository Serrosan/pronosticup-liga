<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Liga;
use App\Models\PushSuscripcion;
use App\Models\User;
use App\Notifications\AvisoDelAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Admin → Enviar aviso: un mensaje tuyo a todos o a una liga. Llega a la
 * campana, como aviso al entrar y al móvil de quien tenga los avisos activados.
 */
class AvisosAdminController extends Controller
{
    /** A dónde puede llevar el aviso al tocarlo. */
    public const DESTINOS = [
        '' => 'A ningún sitio (solo el mensaje)',
        '/dashboard' => 'Inicio',
        '/jornadas' => 'La jornada actual',
        '/clasificacion' => 'Clasificación',
        '/pronosticos' => 'Mis pronósticos',
        '/mis-cartas' => 'Mis Cartas',
        '/chat' => 'Chat',
    ];

    /** GET /admin/avisos — a quién se puede enviar y a dónde puede llevar. */
    public function index()
    {
        $ligas = Liga::withCount('usuarios')->orderBy('nombre')->get()
            ->map(fn ($l) => ['id' => $l->id, 'nombre' => $l->nombre, 'personas' => $l->usuarios_count]);

        return response()->json(['data' => [
            'todos' => $this->destinatarios('todos')->count(),
            'ligas' => $ligas,
            'destinos' => collect(self::DESTINOS)->map(fn ($etiqueta, $url) => ['url' => $url, 'etiqueta' => $etiqueta])->values(),
        ]]);
    }

    /** POST /admin/avisos — lo envía. */
    public function enviar(Request $request)
    {
        $datos = $request->validate([
            'para' => ['required', 'string', 'regex:/^(todos|\d+)$/'],
            'titulo' => ['required', 'string', 'max:60'],
            'mensaje' => ['required', 'string', 'max:300'],
            'url' => ['nullable', 'string', 'in:'.implode(',', array_filter(array_keys(self::DESTINOS)))],
        ]);

        $usuarios = $this->destinatarios($datos['para']);

        if ($usuarios->isEmpty()) {
            return response()->json(['message' => 'No hay nadie a quien enviárselo.'], 422);
        }

        Notification::send($usuarios, new AvisoDelAdmin($datos['titulo'], $datos['mensaje'], $datos['url'] ?? null));

        $conMovil = PushSuscripcion::whereIn('id_usuario', $usuarios->pluck('id'))->distinct()->count('id_usuario');

        Log::info('Aviso del admin enviado', ['admin' => $request->user()->id, 'para' => $datos['para'], 'personas' => $usuarios->count()]);

        return response()->json([
            'message' => "Enviado a {$usuarios->count()} persona(s). {$conMovil} lo recibirán también en el móvil (si tienen activados los avisos del admin).",
        ]);
    }

    private function destinatarios(string $para)
    {
        $consulta = $para === 'todos'
            ? User::query()
            : (Liga::find((int) $para)?->usuarios() ?? User::query()->whereRaw('1 = 0'));

        return $consulta->whereNull('desactivada_en')->get();
    }
}
