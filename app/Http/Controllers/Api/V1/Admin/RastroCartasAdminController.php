<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\Liga;
use App\Services\RastroCartasService;
use App\Services\TareasAdmin;
use Illuminate\Http\Request;

/**
 * /admin/rastro-cartas — la historia de cada carta, solo lectura.
 */
class RastroCartasAdminController extends Controller
{
    public function index(Request $request, RastroCartasService $rastro)
    {
        $ligas = Liga::where('tipo', 'ConExtras')->orderBy('id')->get(['id', 'nombre', 'id_temporada']);

        if ($ligas->isEmpty()) {
            return response()->json(['data' => ['ligas' => [], 'id_liga' => null, 'vista' => 'jornada', 'jornada' => null, 'total_jornadas' => TareasAdmin::TOTAL_JORNADAS, 'cartas' => []]]);
        }

        $liga = $ligas->firstWhere('id', (int) $request->query('id_liga')) ?? $ligas->first();
        $vista = $request->query('vista') === 'mano' ? 'mano' : 'jornada';

        // Por defecto, la próxima jornada por jugar: es donde están las cartas "vivas".
        $porDefecto = CalendarioPartido::proximaJornadaPorJugar((int) $liga->id_temporada) ?? 1;
        $jornada = max(1, min(TareasAdmin::TOTAL_JORNADAS, (int) $request->query('jornada', $porDefecto)));

        return response()->json(['data' => [
            'ligas' => $ligas->map(fn ($l) => ['id' => $l->id, 'nombre' => $l->nombre])->values(),
            'id_liga' => $liga->id,
            'vista' => $vista,
            'jornada' => $jornada,
            'total_jornadas' => TareasAdmin::TOTAL_JORNADAS,
            'cartas' => $rastro->cartas($liga, $vista, $jornada),
        ]]);
    }
}
