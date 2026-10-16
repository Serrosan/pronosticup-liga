<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AyudaGoleadoresService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v1/jornadas/{jornada}/goleadores-ayuda — partidos de la jornada con
 * los goles que marca y encaja cada equipo, para sugerir goleadores. Es igual
 * para todos los usuarios de la temporada, así que se guarda 10 minutos.
 */
class AyudaGoleadoresController extends Controller
{
    public function show(Request $request, int $jornada, AyudaGoleadoresService $servicio)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $idTemporada = (int) $liga->id_temporada;

        $partidos = Cache::remember(
            "ayuda-goleadores:{$idTemporada}:{$jornada}",
            now()->addMinutes(10),
            fn () => $servicio->partidos($idTemporada, $jornada)
        );

        return response()->json(['data' => ['partidos' => $partidos]]);
    }
}
