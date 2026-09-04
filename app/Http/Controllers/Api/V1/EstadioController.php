<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Estadio;

class EstadioController extends Controller
{
    public function index()
    {
        $estadios = Estadio::with('equipo')
            ->orderByDesc('capacidad')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'nombre' => $e->nombre,
                'ciudad' => $e->ciudad,
                'capacidad' => $e->capacidad,
                'tamanio_campo' => $e->tamanio_campo,
                'anio_construccion' => $e->anio_construccion,
                'anio_ult_remodelacion' => $e->anio_ult_remodelacion,
                'foto_url' => $e->foto_url,
                'equipo' => $e->equipo ? [
                    'id' => $e->equipo->id,
                    'nombre' => $e->equipo->nombre_corto ?? $e->equipo->nombre,
                    'escudo_url' => $e->equipo->escudo_url,
                    'color_primario' => $e->equipo->color_primario,
                ] : null,
            ]);

        return response()->json([
            'data' => $estadios,
            'meta' => ['capacidad_maxima' => $estadios->max('capacidad')],
        ]);
    }
}