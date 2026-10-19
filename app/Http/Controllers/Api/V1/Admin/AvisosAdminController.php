<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvisoProgramado;
use App\Models\Liga;
use App\Services\AvisosDelAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Admin → Enviar aviso: un mensaje tuyo a todos o a una liga, ahora o más
 * tarde. Llega a la campana, como aviso al entrar y al móvil de quien tenga
 * los avisos activados.
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

    public function __construct(private AvisosDelAdminService $avisos) {}

    /** GET /admin/avisos — a quién se puede enviar, a dónde puede llevar y lo programado. */
    public function index()
    {
        $ligas = Liga::withCount('usuarios')->orderBy('nombre')->get();

        return response()->json(['data' => [
            'todos' => $this->avisos->destinatarios('todos')->count(),
            'ligas' => $ligas->map(fn ($l) => ['id' => $l->id, 'nombre' => $l->nombre, 'personas' => $l->usuarios_count])->values(),
            'destinos' => collect(self::DESTINOS)->map(fn ($etiqueta, $url) => ['url' => $url, 'etiqueta' => $etiqueta])->values(),
            'programados' => AvisoProgramado::whereNull('enviado_en')->orderBy('enviar_en')->get()->map(fn ($a) => [
                'id' => $a->id,
                'titulo' => $a->titulo,
                'mensaje' => $a->mensaje,
                'enviar_en' => $a->enviar_en->toIso8601String(),
                'para' => $a->para === 'todos' ? 'Todo el mundo' : 'Liga '.($ligas->firstWhere('id', (int) $a->para)?->nombre ?? '¿?'),
            ])->values(),
        ]]);
    }

    /** POST /admin/avisos — lo envía ahora o, con enviar_en, lo deja programado. */
    public function enviar(Request $request)
    {
        $datos = $request->validate([
            'para' => ['required', 'string', 'regex:/^(todos|\d+)$/'],
            'titulo' => ['required', 'string', 'max:60'],
            'mensaje' => ['required', 'string', 'max:300'],
            'url' => ['nullable', 'string', 'in:'.implode(',', array_filter(array_keys(self::DESTINOS)))],
            'enviar_en' => ['nullable', 'date', 'after:now', 'before:+31 days'],
        ], [
            'enviar_en.after' => 'La hora de envío tiene que ser en el futuro.',
            'enviar_en.before' => 'Como mucho se puede programar con un mes de antelación.',
        ]);

        if ($this->avisos->destinatarios($datos['para'])->isEmpty()) {
            return response()->json(['message' => 'No hay nadie a quien enviárselo.'], 422);
        }

        if (! empty($datos['enviar_en'])) {
            $aviso = AvisoProgramado::create([
                'id_admin' => $request->user()->id,
                'para' => $datos['para'],
                'titulo' => $datos['titulo'],
                'mensaje' => $datos['mensaje'],
                'url' => $datos['url'] ?? null,
                // Se guarda en la zona horaria de la app, que es con la que se compara al enviar.
                'enviar_en' => Carbon::parse($datos['enviar_en'])->timezone(config('app.timezone')),
            ]);

            return response()->json(['message' => 'Aviso programado.', 'data' => ['id' => $aviso->id, 'enviar_en' => $aviso->enviar_en->toIso8601String()]]);
        }

        $resultado = $this->avisos->enviar($datos['para'], $datos['titulo'], $datos['mensaje'], $datos['url'] ?? null);

        Log::info('Aviso del admin enviado', ['admin' => $request->user()->id, 'para' => $datos['para'], 'personas' => $resultado['personas']]);

        return response()->json([
            'message' => "Enviado a {$resultado['personas']} persona(s). {$resultado['con_movil']} lo recibirán también en el móvil (si tienen activados los avisos del admin).",
        ]);
    }

    /** DELETE /admin/avisos/{aviso} — cancela uno programado que aún no ha salido. */
    public function cancelar(AvisoProgramado $aviso)
    {
        if ($aviso->enviado_en) {
            return response()->json(['message' => 'Ese aviso ya se ha enviado.'], 422);
        }

        $aviso->delete();

        return response()->json(['message' => 'Aviso programado cancelado.']);
    }
}
