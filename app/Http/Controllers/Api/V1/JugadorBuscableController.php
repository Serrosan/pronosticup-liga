<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Jugador;

/**
 * GET /api/v1/jugadores-buscables — los jugadores que se pueden elegir como
 * goleadores, con su equipo, posición y dorsal para poder enseñar la
 * plantilla de un equipo entera.
 */
class JugadorBuscableController extends Controller
{
    public function index()
    {
        $jugadores = Jugador::whereNull('dado_de_baja_en')
            ->whereHas('plantillasTemporada', fn ($q) => $q->whereNull('fecha_salida'))
            ->with(['plantillasTemporada' => fn ($q) => $q->whereNull('fecha_salida')->with('equipo')])
            ->get()
            ->map(function ($j) {
                // Si quedara una ficha abierta de otra temporada, manda la más reciente.
                $ficha = $j->plantillasTemporada->sortByDesc('id_temporada')->first();

                return [
                    'id' => $j->id,
                    'nombre' => $j->nombre_camiseta ?? trim("{$j->nombre} {$j->apellidos}"),
                    'foto_url' => $j->foto_url,
                    'equipo' => $ficha?->equipo?->nombre_corto ?? $ficha?->equipo?->nombre ?? '',
                    'id_equipo' => $ficha?->id_equipo,
                    'posicion' => $j->posicion,
                    'dorsal' => $ficha?->dorsal,
                ];
            })
            ->values();

        return response()->json(['data' => $jugadores]);
    }
}
