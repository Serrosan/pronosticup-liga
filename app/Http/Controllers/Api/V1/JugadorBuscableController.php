<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;

class JugadorBuscableController extends Controller
{
    public function index()
    {
        $jugadores = Jugador::whereNull('dado_de_baja_en')
            ->whereHas('plantillasTemporada', fn ($q) => $q->whereNull('fecha_salida'))
            ->with(['plantillasTemporada' => fn ($q) => $q->whereNull('fecha_salida')->with('equipo')])
            ->get()
            ->map(fn ($j) => [
                'id' => $j->id,
                'nombre' => $j->nombre_camiseta ?? trim("{$j->nombre} {$j->apellidos}"),
                'foto_url' => $j->foto_url,
                'equipo' => $j->plantillasTemporada->first()?->equipo->nombre_corto ?? '',
            ]);

        return response()->json(['data' => $jugadores]);
    }
}