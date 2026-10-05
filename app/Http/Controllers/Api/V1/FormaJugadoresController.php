<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FormaJugadoresService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v1/jugadores-forma — goles y titularidades recientes de cada
 * jugador, para la pantalla de elegir goleadores. Es igual para todos los
 * usuarios de la temporada, así que se guarda 10 minutos.
 */
class FormaJugadoresController extends Controller
{
    public function index(Request $request, FormaJugadoresService $servicio)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $idTemporada = (int) $liga->id_temporada;

        $datos = Cache::remember(
            "forma-jugadores:{$idTemporada}",
            now()->addMinutes(10),
            fn () => $servicio->deTemporada($idTemporada)
        );

        return response()->json([
            // (object) para que una temporada sin datos viaje como {} y no como []
            'data' => (object) $datos,
            'meta' => ['partidos_recientes' => FormaJugadoresService::PARTIDOS_RECIENTES],
        ]);
    }
}
