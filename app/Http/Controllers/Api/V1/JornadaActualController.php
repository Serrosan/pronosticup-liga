<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use Illuminate\Http\Request;

class JornadaActualController extends Controller
{
    public function show(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $proximoPartido = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('estado', 'Programado')
            ->orderBy('horario_estimado')
            ->first();

        if ($proximoPartido) {
            return response()->json(['data' => ['jornada' => $proximoPartido->jornada]]);
        }

        $ultimaJornada = CalendarioPartido::where('id_temporada', $liga->id_temporada)->max('jornada') ?? 1;

        return response()->json(['data' => ['jornada' => $ultimaJornada]]);
    }
}