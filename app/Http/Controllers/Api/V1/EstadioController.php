<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\Estadio;

class EstadioController extends Controller
{
    public function index()
    {
        $estadios = Estadio::with('equipo')
            ->orderByDesc('capacidad')
            ->get()
            ->map(function ($e) {
                $proximoPartido = null;

                if ($e->equipo) {
                    $partido = CalendarioPartido::where('id_equipo_local', $e->equipo->id)
                        ->where('estado', 'Programado')
                        ->with('equipoVisitante')
                        ->orderBy('horario_estimado')
                        ->first();

                    if ($partido) {
                        $proximoPartido = [
                            'jornada' => $partido->jornada,
                            'rival' => $partido->equipoVisitante->nombre_corto ?? $partido->equipoVisitante->nombre,
                            'escudo_rival' => $partido->equipoVisitante->escudo_url,
                            'horario_estimado' => $partido->horario_estimado?->toIso8601String(),
                        ];
                    }
                }

                return [
                    'id' => $e->id,
                    'nombre' => $e->nombre,
                    'ciudad' => $e->ciudad,
                    'capacidad' => $e->capacidad,
                    'tamanio_campo' => $e->tamanio_campo,
                    'anio_construccion' => $e->anio_construccion,
                    'anio_ult_remodelacion' => $e->anio_ult_remodelacion,
                    'foto_url' => $e->foto_url,
                    'proximo_partido' => $proximoPartido,
                    'equipo' => $e->equipo ? [
                        'id' => $e->equipo->id,
                        'nombre' => $e->equipo->nombre_corto ?? $e->equipo->nombre,
                        'escudo_url' => $e->equipo->escudo_url,
                        'color_primario' => $e->equipo->color_primario,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => $estadios,
            'meta' => ['capacidad_maxima' => $estadios->max('capacidad')],
        ]);
    }
}