<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartidoResource;
use App\Models\CalendarioPartido;
use App\Models\Liga;
use Illuminate\Http\Request;

class PartidoController extends Controller
{
    public function porJornada(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderBy('horario_estimado')
            ->get();

        return PartidoResource::collection($partidos)->additional([
            'meta' => ['ultima_actualizacion' => $partidos->max('updated_at')?->toIso8601String()],
        ]);
    }
}