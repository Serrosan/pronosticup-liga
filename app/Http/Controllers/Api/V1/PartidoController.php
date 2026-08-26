<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartidoResource;
use App\Models\CalendarioPartido;
use App\Models\Liga;
use Illuminate\Http\Request;

class PartidoController extends Controller
{
    public function porJornada(Request $request, Liga $liga, int $jornada)
    {
        $esMiembro = $liga->usuarios()->where('id_usuario', $request->user()->id)->exists();

        if (! $esMiembro) {
            return response()->json(['message' => 'No perteneces a esta liga.'], 403);
        }

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderBy('horario_estimado')
            ->get();

        return PartidoResource::collection($partidos);
    }
}