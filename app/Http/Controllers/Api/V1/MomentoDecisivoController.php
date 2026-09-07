<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use Illuminate\Http\Request;

class MomentoDecisivoController extends Controller
{
    public function show(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->with(['equipoLocal', 'equipoVisitante'])
            ->get();

        if ($partidos->isEmpty() || $partidos->contains(fn ($p) => $p->estado !== 'Jugado')) {
            return response()->json(['data' => null]);
        }

        $antes = LaLigaStandingsController::clasificacionGeneral($liga->id_temporada, $jornada - 1);
        $despues = LaLigaStandingsController::clasificacionGeneral($liga->id_temporada, $jornada);

        $posicionAntes = $antes->pluck('id')->flip()->map(fn ($i) => $i + 1);
        $posicionDespues = $despues->pluck('id')->flip()->map(fn ($i) => $i + 1);

        $mejor = null;
        $mejorImpacto = -1;

        foreach ($partidos as $partido) {
            $posAntesLocal = $posicionAntes->get($partido->id_equipo_local);
            $posDespuesLocal = $posicionDespues->get($partido->id_equipo_local);
            $posAntesVisitante = $posicionAntes->get($partido->id_equipo_visitante);
            $posDespuesVisitante = $posicionDespues->get($partido->id_equipo_visitante);

            if (is_null($posAntesLocal) || is_null($posDespuesLocal) || is_null($posAntesVisitante) || is_null($posDespuesVisitante)) {
                continue;
            }

            $impacto = abs($posAntesLocal - $posDespuesLocal) + abs($posAntesVisitante - $posDespuesVisitante);

            if ($impacto > $mejorImpacto) {
                $mejorImpacto = $impacto;
                $mejor = [
                    'partido_id' => $partido->id,
                    'equipo_local' => $partido->equipoLocal->nombre_corto ?? $partido->equipoLocal->nombre,
                    'escudo_local' => $partido->equipoLocal->escudo_url,
                    'equipo_visitante' => $partido->equipoVisitante->nombre_corto ?? $partido->equipoVisitante->nombre,
                    'escudo_visitante' => $partido->equipoVisitante->escudo_url,
                    'goles_casa' => $partido->goles_casa,
                    'goles_fuera' => $partido->goles_fuera,
                    'local_posicion_antes' => $posAntesLocal,
                    'local_posicion_despues' => $posDespuesLocal,
                    'visitante_posicion_antes' => $posAntesVisitante,
                    'visitante_posicion_despues' => $posDespuesVisitante,
                    'impacto' => $impacto,
                ];
            }
        }

        if (! $mejor || $mejor['impacto'] === 0) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $mejor]);
    }
}