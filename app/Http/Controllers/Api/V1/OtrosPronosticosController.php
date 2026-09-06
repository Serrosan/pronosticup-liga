<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\Pronostico;
use Illuminate\Http\Request;

class OtrosPronosticosController extends Controller
{
    public function show(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $bloqueada = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->where('estado', '!=', 'Programado')
            ->exists();

        if (! $bloqueada) {
            return response()->json(['message' => 'Los pronósticos de esta jornada aún no se pueden ver.'], 403);
        }

        $idsPartidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->pluck('id');

        $pronosticos = Pronostico::where('id_liga', $liga->id)
            ->whereIn('id_partido', $idsPartidos)
            ->where('id_usuario', '!=', $request->user()->id)
            ->with('usuario')
            ->get()
            ->groupBy('id_partido')
            ->map(fn ($grupo) => $grupo->map(fn ($p) => [
                'usuario' => $p->usuario->nombre_visible ?? $p->usuario->name,
                'avatar_url' => $p->usuario->avatar_url ? url($p->usuario->avatar_url) : null,
                'pronostico' => "{$p->goles_local_predicho}-{$p->goles_visitante_predicho}",
            ]));

        return response()->json(['data' => $pronosticos]);
    }
}